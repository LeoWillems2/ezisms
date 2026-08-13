<?php

namespace App\Console\Commands;

use App\Models\Auditobject;
use App\Models\Auditplan;
use App\Models\Auditprogramma;
use App\Models\AuditprogrammaDekking;
use App\Models\Auditronde;
use App\Models\Maatregel;
use App\Models\SoaRegel;
use Database\Seeders\AuditobjectClausuleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bereidt een volledige interne-auditcyclus voor (plan 11b, §9.2.2), zodat de
 * CISO dit maar één keer per cyclus hoeft te doen. Het command:
 *   - actualiseert de audit-universe (clausules + maatregel-objecten uit de SoA);
 *   - maakt een auditprogramma met een jaarplan per cyclusjaar;
 *   - verdeelt ALLE actieve objecten over de jaren (volledige dekking, eenmaal
 *     per cyclus), zowel als dekkingsplanning als in een geplande ronde per jaar;
 *   - laat de interne auditor bewust OPEN — die wijst de CISO per ronde toe.
 *
 * Productie-tool (geen demo-guard). Herhaald draaien met dezelfde naam wordt
 * geweigerd, zodat er geen dubbele cyclus ontstaat.
 */
class BereidAuditcyclusVoor extends Command
{
    protected $signature = 'isms:bereid-auditcyclus-voor
        {--start= : Startdatum van de cyclus (jjjj-mm-dd; een jaartal wordt 1 januari). Standaard vandaag}
        {--jaren= : Aantal jaren in de cyclus (standaard 3, of 1 bij --voorbereiding)}
        {--voorbereiding : Zet de opstartfase op: één plan met een nulmeting over alles, geen dekkingsverdeling}
        {--naam= : Naam van het programma (standaard afgeleid van aard en venster)}
        {--activeer : Zet het programma meteen op actief i.p.v. concept}
        {--forceer : Ga door ook als de SoA nog niet volledig is beslist}
        {--vervang : Ruim een botsende bestaande cyclus (zelfde naam of overlappend venster) eerst op}';

    protected $description = 'Bereidt een volledige interne-auditcyclus voor: programma, jaarplannen, dekking en geplande rondes (auditor open)';

    public function handle(): int
    {
        $voorbereiding = (bool) $this->option('voorbereiding');

        $startDatum = $this->leesStartdatum();
        if ($startDatum === null) {
            return self::FAILURE;
        }

        // Een voorbereidingsprogramma loopt tot de certificering en is dus kort;
        // een cyclus is standaard driejarig, gelijk aan de certificeringscyclus.
        $jaren = (int) ($this->option('jaren') ?: ($voorbereiding ? 1 : 3));

        if ($jaren < 1 || $jaren > 6) {
            $this->error('--jaren moet tussen 1 en 6 liggen.');

            return self::FAILURE;
        }

        $eindDatum = $startDatum->copy()->addYears($jaren)->subDay();
        $venster = Auditprogramma::maandJaar($startDatum).' – '.Auditprogramma::maandJaar($eindDatum);
        $naam = (string) ($this->option('naam') ?: ($voorbereiding
            ? 'Auditvoorbereiding '.$startDatum->year
            : "Interne auditcyclus {$startDatum->year}–{$eindDatum->year}"));
        $vervang = (bool) $this->option('vervang');

        $naamConflict = Auditprogramma::where('naam', $naam)->first();
        if ($naamConflict !== null && ! $vervang) {
            $this->error("Er bestaat al een programma '{$naam}'. Kies een andere --naam/--start, of gebruik --vervang.");

            return self::FAILURE;
        }

        // 1. Audit-universe actueel maken: clausules borgen + maatregel-objecten
        //    synchroniseren met de van-toepassing-verklaarde SoA.
        if (Auditobject::where('soort', 'clausule')->doesntExist()) {
            (new AuditobjectClausuleSeeder)->setContainer($this->laravel)->run();
        }
        $this->call('isms:sync-auditobjecten');

        // Op volgorde: zo blijven bij elkaar horende clausules/controls samen en
        // vallen de groepen in natuurlijke hoofdstuk-/themavolgorde.
        $objecten = Auditobject::actief()->orderBy('volgorde')->get();

        if ($objecten->isEmpty()) {
            $this->error('Geen actieve auditobjecten. Seed de clausules of verklaar controls van toepassing.');

            return self::FAILURE;
        }

        // 2. SoA-volledigheid: alleen van-toepassing-verklaarde controls gaan mee.
        //    Staan er nog controls op 'onbeslist', dan valt een deel van de Annex
        //    A buiten de cyclus zonder dat de CISO dat per se doorheeft.
        if (($fout = $this->waarschuwBijOnvolledigeSoa($voorbereiding)) !== null) {
            return $fout;
        }

        // 3. Conflictcheck. Sinds plan 11c is `auditplannen.jaar` niet meer
        //    globaal uniek — in de opstartfase liggen er juist meerdere plannen in
        //    hetzelfde kalenderjaar. De botsing die overblijft is inhoudelijk: een
        //    bestaande cyclus die hetzelfde venster beslaat.
        //    Een voorbereidingsprogramma mag bewust wél overlappen: het loopt tot
        //    de certificering, en de cyclus die daarna begint kan er tegenaan of
        //    overheen liggen.
        $overlappend = $voorbereiding
            ? collect()
            : Auditprogramma::query()
                ->where('aard', 'certificeringscyclus')
                ->where('start_datum', '<=', $eindDatum->copy()->endOfDay()->toDateTimeString())
                ->get()
                ->filter(fn (Auditprogramma $p) => $p->eindDatum() >= $startDatum);

        if ($overlappend->isNotEmpty() && ! $vervang) {
            $this->error('Er loopt al een cyclus over dit venster: '
                .$overlappend->map(fn (Auditprogramma $p) => "{$p->naam} ({$p->venster()})")->implode(', ')
                .'. Gebruik --vervang om die te vervangen.');

            return self::FAILURE;
        }

        // De cycli die --vervang mag opruimen: het gelijknamige programma én de
        // programma's die een jaarplan in dit venster bezetten.
        $teVervangen = collect([$naamConflict?->id])
            ->merge($overlappend->pluck('id'))
            ->filter()
            ->unique()
            ->values();

        // Collateral: --vervang ruimt de botsende cyclus als geheel op, dus ook
        // jaren buiten dit venster (bv. een lopende 2026 bij een nieuwe start in
        // 2027). Waarschuw daarvoor en vraag bevestiging vóór er iets wijzigt.
        if ($vervang && ($fout = $this->waarschuwVoorCollateral($teVervangen, $startDatum, $eindDatum)) !== null) {
            return $fout;
        }

        // Verdeel de groepen gelijkmatig over de programmajaren (volledige
        // dekking, eenmaal per cyclus). De groep bepaalt het jaar, zodat verwante
        // items in dezelfde ronde vallen. Bij een voorbereiding gebeurt dat niet:
        // daar gaat álles in één nulmeting.
        $groepJaar = $voorbereiding ? [] : $this->verdeelGroepenOverProgrammajaren($objecten, $jaren);

        DB::transaction(function () use ($naam, $startDatum, $jaren, $objecten, $groepJaar, $vervang, $teVervangen, $voorbereiding) {
            if ($vervang && $teVervangen->isNotEmpty()) {
                $this->verwijderCycli($teVervangen);
            }

            $programma = Auditprogramma::create([
                'naam' => $naam,
                'start_datum' => $startDatum,
                'aantal_jaren' => $jaren,
                'aard' => $voorbereiding ? 'voorbereiding' : 'certificeringscyclus',
                'status' => $this->option('activeer') ? 'actief' : 'concept',
            ]);

            // Eén jaarplan per programmajaar, verankerd op het feitelijke venster.
            // Bewust `create` en geen `updateOrCreate` op `jaar`: sinds plan 11c
            // mogen er meerdere plannen in hetzelfde kalenderjaar bestaan, en een
            // bestaand plan van iemand anders overnemen zou stil datadiefstal zijn.
            $plannen = [];
            foreach ($programma->programmajaren() as $jaar) {
                $plannen[$jaar['nummer']] = Auditplan::create([
                    'auditprogramma_id' => $programma->id,
                    'programmajaar' => $jaar['nummer'],
                    'jaar' => $jaar['start']->year,
                    'periode_start' => $jaar['start'],
                    'periode_eind' => $jaar['eind'],
                ]);
            }

            if ($voorbereiding) {
                // De nulmeting: één ronde over álle actieve objecten, die per
                // definitie niet meetelt voor de dekking. Geen dekkingsplanning —
                // een opstartfase kent geen meerjarige dekkingsverplichting.
                $ronde = Auditronde::create([
                    'auditplan_id' => $plannen[1]->id,
                    'type' => 'intern_nulmeting',
                    'status' => 'gepland',
                    'auditor_gebruiker_id' => null,
                ]);
                $ronde->auditobjecten()->sync($objecten->pluck('id')->all());

                return;
            }

            foreach ($objecten as $object) {
                // Dekkingsplanning: eenmaal per cyclus, in het toegewezen jaar.
                AuditprogrammaDekking::create([
                    'auditprogramma_id' => $programma->id,
                    'auditobject_id' => $object->id,
                    'interval_jaren' => $jaren,
                    'gepland_start_programmajaar' => $groepJaar[$object->groep],
                ]);
            }

            // Eén geplande ronde per jaar, auditor OPEN, met de objecten van dat jaar.
            foreach ($plannen as $nummer => $plan) {
                $ronde = Auditronde::create([
                    'auditplan_id' => $plan->id,
                    'type' => 'intern',
                    'status' => 'gepland',
                    'auditor_gebruiker_id' => null,
                ]);

                $ronde->auditobjecten()->sync(
                    $objecten->filter(fn (Auditobject $o) => $groepJaar[$o->groep] === $nummer)->pluck('id')->all()
                );
            }
        });

        if ($voorbereiding) {
            $this->info("Voorbereidingsprogramma '{$naam}' opgezet ({$venster}): één jaarplan met een geplande "
                ."nulmeting over alle {$objecten->count()} objecten.");
            $this->line('De nulmeting telt niet mee voor de dekkingsmatrix — dat is de bedoeling; hij meet de startsituatie.');
            $this->line('Sluit dit programma af zodra het certificaat er is, en start dan de cyclus op de certificaatdatum.');
        } else {
            $this->info("Auditcyclus '{$naam}' voorbereid ({$venster}): {$jaren} jaarplannen, "
                ."{$objecten->count()} objecten verdeeld over {$jaren} geplande rondes.");
        }

        $this->line('De interne auditor is per ronde nog open — wijs die toe en plan de datum voordat je een ronde start.');

        return self::SUCCESS;
    }

    /**
     * `--start` accepteert een datum, maar blijft een jaartal begrijpen als
     * 1 januari van dat jaar — zo breken bestaande scripts en documentatie niet.
     */
    private function leesStartdatum(): ?Carbon
    {
        $ruw = trim((string) $this->option('start'));

        if ($ruw === '') {
            return now()->startOfDay();
        }

        if (preg_match('/^\d{4}$/', $ruw) === 1) {
            return Carbon::create((int) $ruw, 1, 1);
        }

        try {
            return Carbon::parse($ruw)->startOfDay();
        } catch (\Exception) {
            $this->error("--start '{$ruw}' is geen datum (jjjj-mm-dd) en geen jaartal.");

            return null;
        }
    }

    /**
     * Verwijdert de opgegeven cycli volledig: hun jaarplannen (met rondes,
     * bevindingen en koppelingen via DB-cascade) en de programma's zelf (met
     * dekkingen via cascade). Plannen éérst, dan de programma's. Mass-delete, dus
     * zonder audit-trail-events — een bewuste vervang-actie, geen mutatie om te
     * loggen.
     *
     * @param  Collection<int, int>  $programmaIds
     */
    private function verwijderCycli($programmaIds): void
    {
        $plannen = Auditplan::whereIn('auditprogramma_id', $programmaIds)->count();
        Auditplan::whereIn('auditprogramma_id', $programmaIds)->delete();

        $programmas = Auditprogramma::whereIn('id', $programmaIds)->count();
        Auditprogramma::whereIn('id', $programmaIds)->delete();

        $this->warn("Vervangen: {$programmas} bestaand programma('s) en {$plannen} jaarplan(nen) opgeruimd.");
    }

    /**
     * Waarschuwt als --vervang jaren buiten het nieuwe venster [start, eind] mee
     * zou opruimen (de botsende cyclus wordt als geheel verwijderd, niet per
     * jaar), en vraagt bevestiging. Zonder bevestiging: afbreken, nog vóór er
     * iets wijzigt. Geen collateral → geen vraag, gewoon door.
     *
     * @param  Collection<int, int>  $programmaIds
     * @return int|null een exitcode als er afgebroken moet worden, anders null
     */
    private function waarschuwVoorCollateral($programmaIds, Carbon $start, Carbon $eind): ?int
    {
        if ($programmaIds->isEmpty()) {
            return null;
        }

        // Buiten het nieuwe venster = het plan begint vóór de nieuwe start of
        // eindigt erna. Op de periode en niet op het kalenderjaar, anders klopt
        // het bij een cyclus die niet op 1 januari begint niet.
        //
        // Vergelijk op datum+tijd en niet op 'Y-m-d': de date-cast schrijft
        // '2028-12-31 00:00:00' weg, en dat is als string groter dan
        // '2028-12-31'. Een plan dat precies op de laatste dag eindigt zou
        // daardoor als collateral gelden en onnodig om bevestiging vragen.
        $collateralJaren = Auditplan::whereIn('auditprogramma_id', $programmaIds)
            ->where(fn ($q) => $q->whereNull('periode_start')
                ->orWhere('periode_start', '<', $start->copy()->startOfDay()->toDateTimeString())
                ->orWhere('periode_eind', '>', $eind->copy()->endOfDay()->toDateTimeString()))
            ->orderBy('jaar')
            ->pluck('jaar');

        if ($collateralJaren->isEmpty()) {
            return null;
        }

        $venster = Auditprogramma::maandJaar($start).' – '.Auditprogramma::maandJaar($eind);
        $this->warn("--vervang verwijdert de botsende cyclus in zijn geheel, inclusief jaar(en) buiten dit venster ({$venster}): "
            .$collateralJaren->implode(', ').'.');
        $this->line('De rondes, bevindingen en dekkingen van die jaren gaan daarbij verloren.');

        if (! $this->confirm('Doorgaan en die jaren ook verwijderen?', false)) {
            $this->info('Afgebroken; niets gewijzigd.');

            return self::FAILURE;
        }

        return null;
    }

    /**
     * Toont de SoA-triage en breekt af als er nog controls op 'onbeslist' staan,
     * tenzij --forceer. Zo legt niemand per ongeluk een cyclus vast die het
     * grootste deel van Bijlage A mist omdat de SoA nog niet is afgerond.
     *
     * @return int|null een exitcode als er afgebroken moet worden, anders null
     */
    private function waarschuwBijOnvolledigeSoa(bool $voorbereiding = false): ?int
    {
        $totaal = Maatregel::count();

        if ($totaal === 0) {
            return null; // Geen maatregelen geseed (bijv. in tests): niets te melden.
        }

        $inScope = SoaRegel::where('van_toepassing', true)->count();
        $uitgesloten = SoaRegel::where('van_toepassing', false)->count();
        // Onbeslist = NULL-regels én maatregelen zonder SoA-regel.
        $onbeslist = $totaal - $inScope - $uitgesloten;

        if ($onbeslist <= 0) {
            return null;
        }

        $this->warn("De SoA is nog niet volledig beslist: {$onbeslist} van {$totaal} controls staan op 'onbeslist' en vallen buiten deze cyclus.");
        $this->line("In scope: {$inScope} · Uitgesloten: {$uitgesloten} · Onbeslist: {$onbeslist}");

        // Bij een nulmeting is een onvolledige SoA de normale toestand — dat is
        // juist wat je gaat meten. Zonder deze uitzondering is het command
        // onbruikbaar op het moment waarop je hem het hardst nodig hebt.
        if ($voorbereiding) {
            $this->line('Bij een voorbereiding is dat geen blokkade: de nulmeting meet juist wat er nog niet staat.');

            return null;
        }

        if (! $this->option('forceer')) {
            $this->line('Vul de SoA eerst af, of gebruik --forceer om toch door te gaan met alleen de in-scope controls.');

            return self::FAILURE;
        }

        $this->line('--forceer opgegeven: de cyclus wordt aangemaakt met alleen de in-scope controls.');

        return null;
    }

    /**
     * Verdeelt de groepen (hoofdstukken H4-H10 en Bijlage-A-thema's) gelijkmatig
     * over de cyclusjaren. Groep i → jaar start + floor(i * jaren / groepen).
     *
     * @param  Collection<int, Auditobject>  $objecten
     * @return array<string, int> groep => peiljaar
     */
    private function verdeelGroepenOverProgrammajaren($objecten, int $jaren): array
    {
        $groepen = $objecten->pluck('groep')->unique()->values();
        $aantal = max(1, $groepen->count());

        $verdeling = [];
        foreach ($groepen as $index => $groep) {
            $verdeling[$groep] = 1 + intdiv($index * $jaren, $aantal);
        }

        return $verdeling;
    }
}
