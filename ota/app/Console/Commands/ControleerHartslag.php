<?php

namespace App\Console\Commands;

use App\Models\Gebruiker;
use App\Models\KpiDefinitie;
use App\Models\Systeemhartslag;
use App\Models\Taak;
use App\Support\Meetbronnen;
use App\Support\TaakPlanner;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event as GeplandeTaak;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Leidt uit de hartslag af welke geplande momenten gemist zijn, en maakt van de
 * onherstelbare gaten een taak (implementatie/00m §4).
 *
 * **Geen tweede lijst van wat er hoort te draaien.** De verwachte momenten komen
 * uit `Schedule::events()` zelf; `routes/console.php` blijft de enige bron, en
 * een nieuw gepland commando wordt vanzelf meegenomen (00m §0.1).
 *
 * **Geen inhaalslag.** Er wordt niets alsnog gedraaid. `isms:meet-kpis` is
 * onveranderlijk van opzet — elke meting is een nieuw meetpunt, nooit een
 * herberekening — en een meetpunt met terugwerkende kracht zou precies dat
 * breken. Het gat wordt zichtbaar gemaakt, niet weggepoetst (00m §13).
 *
 * **Tijdzone.** De scheduler rekent in `config('app.timezone')`, dat op `UTC`
 * staat. Het wegschrijven en het opsommen gebruiken dezelfde klok, dus dit is
 * consistent — maar het betekent wel dat `dailyAt('01:00')` 01:00 UTC is en niet
 * 01:00 Amsterdam. Bestaand gedrag; het staat hier omdat het bij het lezen van
 * een gatenrapport verwarrend is.
 */
class ControleerHartslag extends Command
{
    protected $signature = 'isms:controleer-hartslag
        {--stil : Alleen de samenvatting, voor de planner en de uitrol}
        {--geen-taken : Rapporteer wat er gemist is, maar maak niets aan}';

    protected $description = 'Controleert of de geplande bewaking heeft gedraaid en meldt de gaten';

    /** Taaksoort voor een gat zonder natuurlijke entiteit (00m §7). */
    private const SOORT_ONDERBROKEN = 'bewaking-onderbroken';

    /** Taaksoort voor een KPI-meetpunt dat niet meer te maken is (00m §7). */
    private const SOORT_MEETPUNT_GEMIST = 'kpi-meetpunt-gemist';

    /**
     * Het blok waar deze taken onder vallen. Niet iets nieuws:
     * `MeetaanpakOverzicht::BLOK` gebruikt diezelfde code, en er is geen apart
     * metrics-blok in `BlokSeeder` (00m §7).
     */
    private const BLOK = 'management-review-verbetercyclus';

    public function handle(): int
    {
        $stil = (bool) $this->option('stil');
        $gaten = [];

        foreach (app(Schedule::class)->events() as $taak) {
            $gat = $this->gatVoor($taak);

            if ($gat !== null) {
                $gaten[] = $gat;
            }
        }

        $this->rapporteer($gaten, $stil);

        if (! $this->option('geen-taken')) {
            $this->maakTaken($gaten);
        }

        $this->ruimOp();

        // Altijd 0. Een gat in de bewaking is een bevinding, geen storing in dit
        // commando — en dit draait midden in `deploy.sh`, dat op de eerste
        // exitcode ≠ 0 de hele uitrol afbreekt. De uitkomst hoort in het
        // slotscherm te staan, niet de uitrol te blokkeren.
        return self::SUCCESS;
    }

    /**
     * Het gat van één gepland commando, of `null` als er niets te melden valt.
     *
     * @return array{
     *     sleutel: string, naam: string, momenten: list<CarbonInterface>,
     *     afgekapt: bool, klasse: string, let_op: ?string, gat_uren: float
     * }|null
     */
    private function gatVoor(GeplandeTaak $taak): ?array
    {
        $sleutel = Systeemhartslag::sleutelVoor($taak);

        if ($sleutel === null) {
            return null;
        }

        $laatste = Systeemhartslag::query()->laatsteVoor($sleutel)->first();

        // Geen enkele rij: dit commando is nieuw en heeft nog geen startlijn
        // (00m §3, §9.1). Er wordt niets verwacht — de eerstvolgende `db:seed`
        // geeft hem zijn nulpunt. Met terugwerkende kracht een gat melden dat
        // nooit bestond is erger dan één ronde niets zeggen.
        if ($laatste === null) {
            return null;
        }

        $nu = now();

        // §9.3 — de klok is verzet. Een gat is een verschil tussen twee
        // tijdstempels; ligt de laatste hartslag in de toekomst, dan liegt de
        // klok en niet de bewaking. Geen melding en geen taak: er is niets
        // gemist, er is alleen niets zinnigs te berekenen.
        if ($laatste->gedraaid_op->greaterThan($nu)) {
            Log::warning("Hartslag {$sleutel}: de laatste hartslag ligt in de toekomst; de klok van deze host is verzet.");

            return null;
        }

        $momenten = $this->gemisteMomenten($taak, $laatste->gedraaid_op, $nu);

        if ($momenten === []) {
            return null;
        }

        $gatUren = $laatste->gedraaid_op->diffInMinutes($nu) / 60;

        // §7 — een kort gat is een `compose up` van tien minuten. Dat hoort geen
        // ruis te maken en zeker geen taak; één regel in het log volstaat, zodat
        // het achteraf wél na te lopen is.
        if ($gatUren < (float) config('hartslag.drempel_uren')) {
            Log::info(sprintf(
                'Hartslag %s: %d moment(en) gemist binnen %s uur; onder de meldgrens.',
                $sleutel,
                count($momenten),
                round($gatUren, 1),
            ));

            return null;
        }

        $instelling = config('hartslag.commandos.'.$sleutel, []);

        return [
            'sleutel' => $sleutel,
            'naam' => $taak->getSummaryForDisplay(),
            'momenten' => $momenten,
            'afgekapt' => count($momenten) >= (int) config('hartslag.maximum_momenten'),
            // Een commando dat niet in `config/hartslag.php` staat geldt als
            // onherstelbaar. Met opzet de veilige kant: een nieuw gepland
            // commando waarvan niemand de klasse heeft bepaald moet ruis maken
            // en niet stilzwijgend als onschuldig gelden (00m §5).
            'klasse' => $instelling['klasse'] ?? 'onherstelbaar',
            'let_op' => $instelling['let_op'] ?? null,
            'gat_uren' => $gatUren,
        ];
    }

    /**
     * De momenten die tussen de laatste hartslag en nu hadden moeten vallen.
     *
     * De opsomming stopt bij `maximum_momenten`: een installatie die een jaar
     * stil heeft gelegen levert er honderden op, en de eerste handeling na een
     * lange stilstand hoort geen berg taken te zijn maar overzicht (00m §9.2).
     *
     * @return list<CarbonInterface>
     */
    private function gemisteMomenten(GeplandeTaak $taak, CarbonInterface $vanaf, CarbonInterface $tot): array
    {
        $maximum = (int) config('hartslag.maximum_momenten');
        $momenten = [];
        $cursor = $vanaf;

        while (count($momenten) < $maximum) {
            // `false` voor $allowCurrentDate: het moment waarop de taak zelf
            // draaide telt niet als gemist.
            $volgende = $taak->nextRunDate($cursor, 0, false);

            if ($volgende->greaterThan($tot)) {
                break;
            }

            $momenten[] = $volgende;
            $cursor = $volgende;
        }

        return $momenten;
    }

    /**
     * Drie uitgangen, oplopend in gewicht (00m §7). Het korte gat is er hierboven
     * al uit; wat hier binnenkomt is minstens meldenswaardig.
     *
     * @param  list<array<string, mixed>>  $gaten
     */
    private function rapporteer(array $gaten, bool $stil): void
    {
        if ($gaten === []) {
            $this->info('Bewaking: geen gaten; alle geplande commando\'s hebben gedraaid.');

            return;
        }

        $this->warn(sprintf(
            'Bewaking onderbroken: %d van de geplande commando\'s hebben momenten gemist.',
            count($gaten),
        ));

        foreach ($gaten as $gat) {
            $this->line('   '.$this->regelVoor($gat));

            if ($gat['let_op'] !== null) {
                $this->line('      let op: '.$gat['let_op']);
            }

            if (! $stil && $gat['klasse'] === 'gemengd') {
                foreach ($this->kpiToelichting($gat) as $regel) {
                    $this->line('      '.$regel);
                }
            }
        }
    }

    /** @param  array<string, mixed>  $gat */
    private function regelVoor(array $gat): string
    {
        $aantal = count($gat['momenten']);

        $wanneer = $gat['afgekapt']
            ? 'meer dan '.$aantal.' momenten'
            : $aantal.' moment'.($aantal === 1 ? '' : 'en').' gemist ('.$this->momentenlijst($gat['momenten']).')';

        return sprintf('%s — %s [%s]', $gat['sleutel'], $wanneer, $gat['klasse']);
    }

    /** @param  list<CarbonInterface>  $momenten */
    private function momentenlijst(array $momenten): string
    {
        // Bij veel momenten is de volledige lijst geen informatie meer; de
        // eerste en de laatste zeggen waar het gat begon en eindigde.
        if (count($momenten) > 6) {
            return $momenten[0]->format('d-m-Y').' t/m '.end($momenten)->format('d-m-Y');
        }

        return implode(', ', array_map(fn (CarbonInterface $m) => $m->format('d-m'), $momenten));
    }

    /**
     * De KPI-nuance (00m §6). `MeetKpis` doet dit al deels goed, en dat maakt de
     * melding preciezer dan "de maandmeting is gemist":
     *
     * - **gebeurtenis-KPI**: opgevangen. Het venster rekt op (12g §3). Wat je
     *   verliest is het detailniveau — één lange periode in plaats van twee
     *   maandpunten — niet de gebeurtenissen zelf.
     * - **toestand-KPI**: verloren. Die meet de stand op het meetmoment, en de
     *   stand van 1 september is op 20 oktober niet meer op te vragen.
     * - **handmatige KPI**: niet van toepassing; die voert de CISO zelf in.
     *
     * @param  array<string, mixed>  $gat
     * @return list<string>
     */
    private function kpiToelichting(array $gat): array
    {
        [$toestand, $gebeurtenis, $handmatig] = $this->kpisNaarSoort();
        $regels = [];

        if ($toestand->isNotEmpty()) {
            $regels[] = $this->telWoord($toestand->count(), 'toestand-KPI').' zonder meetpunt: '
                .$this->eersteNamen($toestand->pluck('sleutel')->all())
                .' → historie heeft een gat, niet te herstellen';
        }

        if ($gebeurtenis->isNotEmpty()) {
            $regels[] = $this->telWoord($gebeurtenis->count(), 'gebeurtenis-KPI')
                .' opgevangen door een langer venster (12g §3)';
        }

        if ($handmatig->isNotEmpty()) {
            $regels[] = $this->telWoord($handmatig->count(), 'handmatige KPI').': niet van toepassing';
        }

        return $regels;
    }

    /**
     * "1 toestand-KPI" en "20 toestand-KPI's". Een apostrof achter een enkel
     * exemplaar leest als een typefout en ondermijnt precies de regel die het
     * meldt.
     */
    private function telWoord(int $aantal, string $enkelvoud): string
    {
        return $aantal.' '.$enkelvoud.($aantal === 1 ? '' : "'s");
    }

    /**
     * De eerste namen voltuit, de rest geteld. Twintig sleutels achter elkaar op
     * één regel is geen opsomming meer maar een muur, en dan leest niemand ook
     * de eerste.
     *
     * @param  list<string>  $namen
     */
    private function eersteNamen(array $namen, int $maximum = 5): string
    {
        if (count($namen) <= $maximum) {
            return implode(', ', $namen);
        }

        return implode(', ', array_slice($namen, 0, $maximum))
            .' en nog '.(count($namen) - $maximum);
    }

    /**
     * De actieve KPI-definities uitgesplitst naar wat een gemiste meting met ze
     * doet.
     *
     * @return array{0: Collection<int, KpiDefinitie>, 1: Collection<int, KpiDefinitie>, 2: Collection<int, KpiDefinitie>}
     */
    private function kpisNaarSoort(): array
    {
        $actief = KpiDefinitie::query()->where('actief', true)->get();

        $handmatig = $actief->filter(fn (KpiDefinitie $d) => $d->meetbron === null);

        $berekend = $actief->filter(
            fn (KpiDefinitie $d) => $d->meetbron !== null && Meetbronnen::bestaat($d->meetbron)
        );

        return [
            $berekend->reject(fn (KpiDefinitie $d) => Meetbronnen::isGebeurtenis($d->meetbron))->values(),
            $berekend->filter(fn (KpiDefinitie $d) => Meetbronnen::isGebeurtenis($d->meetbron))->values(),
            $handmatig->values(),
        ];
    }

    /** @param  list<array<string, mixed>>  $gaten */
    private function maakTaken(array $gaten): void
    {
        $ciso = $this->ciso()?->id;

        foreach ($gaten as $gat) {
            match ($gat['klasse']) {
                // Inhaalbaar: geen taak. Er valt niets te doen wat de volgende
                // run niet vanzelf doet; de melding hierboven is de uitgang.
                'inhaalbaar' => null,
                'gemengd' => $this->taakPerToestandKpi($gat, $ciso),
                default => $this->taakZonderEntiteit($gat, $ciso),
            };
        }
    }

    /**
     * Voor de KPI's is er een natuurlijke entiteit — de `KpiDefinitie` waarvan
     * het meetpunt ontbreekt. Dat geeft de idempotentie gratis: de bestaande
     * sleutel (entiteit, soort) zorgt dat een herstart geen tweede taak
     * oplevert, en de taak beschrijft wát er ontbreekt in plaats van "de
     * scheduler heeft gehaperd" (00m §7).
     *
     * @param  array<string, mixed>  $gat
     */
    private function taakPerToestandKpi(array $gat, ?int $cisoId): void
    {
        [$toestand] = $this->kpisNaarSoort();
        $maanden = $this->momentenlijst($gat['momenten']);

        foreach ($toestand as $definitie) {
            TaakPlanner::planVoorEntiteit(
                $definitie,
                self::SOORT_MEETPUNT_GEMIST,
                "Meetpunt ontbreekt: {$definitie->naam} ({$maanden})",
                Carbon::today()->addDays(14),
                self::BLOK,
                $cisoId,
            );
        }
    }

    /**
     * Voor een gat als dat van `isms:leg-restrisico-vast` is er geen enkele
     * entiteit — het betreft alle controls. Eén taak, dus, en de enige plek waar
     * `TaakPlanner` niet past (00m §7).
     *
     * **Afwijking van §7.** Dat plan wilde de idempotentiesleutel op (soort,
     * deadline). Dat werkt niet: de deadline is `vandaag + 14`, en een gemist
     * jaarmoment blijft een jaar lang gemist — er zou dus elke dag een nieuwe
     * taak bij komen. De sleutel is daarom (soort, titel), en de titel noemt het
     * commando en de gemiste momenten. Die is stabiel zolang het gat hetzelfde
     * is, en verandert juist wél zodra er een moment bij komt.
     *
     * @param  array<string, mixed>  $gat
     */
    private function taakZonderEntiteit(array $gat, ?int $cisoId): void
    {
        $titel = sprintf(
            'Bewaking onderbroken: %s (%s)',
            $gat['sleutel'],
            $gat['afgekapt'] ? 'meer dan '.count($gat['momenten']).' momenten' : $this->momentenlijst($gat['momenten']),
        );

        $bestaat = Taak::query()
            ->where('soort', self::SOORT_ONDERBROKEN)
            ->where('titel', $titel)
            ->whereIn('status', Taak::OPENSTAAND)
            ->exists();

        if ($bestaat) {
            return;
        }

        Taak::create([
            'titel' => $titel,
            'deadline' => Carbon::today()->addDays(14),
            'eigenaar_id' => $cisoId,
            'soort' => self::SOORT_ONDERBROKEN,
            'gekoppeld_blok_naam' => self::BLOK,
        ]);
    }

    /**
     * De eigenaar van de taken: de langst bestaande actieve CISO, of niemand.
     * `eigenaar_id` mag null zijn — dan staat de taak zonder toewijzing in het
     * algemene overzicht, en dat is een geldige stand en geen storing. Zelfde
     * keuze als in {@see Kenmerken}.
     */
    private function ciso(): ?Gebruiker
    {
        return Gebruiker::selecteerbaar()
            ->whereHas('rollen', fn ($q) => $q->where('naam', 'CISO'))
            ->orderBy('id')
            ->first();
    }

    /**
     * Rijen ouder dan de bewaartermijn opruimen (00m §1.1).
     *
     * **Met één harde uitzondering: de laatste rij per sleutel blijft altijd
     * staan.** Zonder die regel wist het opruimen bij een lange stilstand precies
     * het ankerpunt waar de detectie op leunt, en dan verdwijnt het gat samen met
     * het bewijs ervan. Dat is het ergste dat hier mis kan gaan.
     */
    private function ruimOp(): void
    {
        $grens = now()->subDays((int) config('hartslag.bewaartermijn_dagen'));

        // Per sleutel de jongste rij, op `gedraaid_op` en niet op `id`: bij een
        // herstelde installatie kan een oudere hartslag later ingevoegd zijn.
        $tesparen = Systeemhartslag::query()
            ->select('taak_sleutel')
            ->selectRaw('MAX(gedraaid_op) as top')
            ->groupBy('taak_sleutel')
            ->get()
            ->map(fn ($rij) => Systeemhartslag::query()
                ->where('taak_sleutel', $rij->taak_sleutel)
                ->where('gedraaid_op', $rij->top)
                ->orderByDesc('id')
                ->value('id'))
            ->filter()
            ->all();

        // Machinale log, dus een gewone `delete()` op de query builder: er is
        // geen audit trail om langs te gaan (00m §0.3).
        Systeemhartslag::query()
            ->where('gedraaid_op', '<', $grens)
            ->whereNotIn('id', $tesparen)
            ->delete();
    }
}
