<?php

namespace App\Support;

use App\Mail\StapActueel;
use App\Models\Taak;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * De tweede ingang van de taken-engine, naast `TaakPlanner`
 * (implementatie/07b). Waar de planner één openstaande taak per (entiteit,
 * soort) aanhoudt, houdt deze klasse een geordende reeks stappen bij op één
 * entiteit.
 *
 * De koppeling loopt net als bij de planner één kant op: bronblokken roepen
 * deze klasse aan met een kant-en-klare lijst stappen, de engine kent geen
 * sjablonen en geen dossiersoorten. Uit welk sjabloon een reeks komt en wat er
 * na een afkeuring gebeurt, weet alleen het bronblok.
 *
 * Wat een reeks bij elkaar houdt is de gekoppelde entiteit plus een gevulde
 * `volgorde` — geen eigen kolom (§3). Daarmee draagt één entiteit ook precies
 * één reeks tegelijk; `start()` weigert een tweede.
 */
final class Stappenreeks
{
    /** @var list<string> */
    public const UITKOMSTEN = ['goedgekeurd', 'afgekeurd', 'uitgevoerd', 'nvt'];

    /**
     * Legt de hele reeks vast en activeert de eerste groep.
     *
     * Alle stappen worden meteen aangemaakt, in `wachtend` (§2): de uitvoerder
     * ziet daarmee wat er komt, eigenaren zijn vooraf te corrigeren, en de
     * audit trail legt het plan vast zoals het bij aanvang was.
     *
     * Met `extra` kan het aanroepende blok zijn eigen kolommen op de taak
     * meegeven (blok 15 zet er `sjabloonstap_id` in). De engine kijkt er niet
     * in en kent die kolommen niet — hij geeft ze door aan `Taak::create()`.
     * Het alternatief was ze er achteraf op zetten, en dat levert per stap een
     * extra regel in de audit trail op voor iets dat bij het aanmaken hoort.
     *
     * @param  list<array{titel: string, volgorde: int, deadline: mixed, eigenaar_id?: int|null, omschrijving?: string|null, vraagt_uitkomst?: bool, extra?: array<string, mixed>}>  $stappen
     * @return Collection<int, Taak>
     */
    public static function start(Model $entiteit, string $blokNaam, array $stappen): Collection
    {
        if ($stappen === []) {
            throw new RuntimeException('Een stappenreeks zonder stappen heeft geen betekenis.');
        }

        // Stilzwijgend samenvoegen zou twee reeksen op één hoop gooien en pas
        // veel later opvallen — dan liever hier stuk (§3).
        if (self::query($entiteit)->exists()) {
            throw new RuntimeException(sprintf(
                'Op %s #%s loopt al een stappenreeks.',
                $entiteit->getMorphClass(),
                $entiteit->getKey(),
            ));
        }

        $aangemaakt = collect($stappen)->map(fn (array $stap) => Taak::create([
            ...$stap['extra'] ?? [],
            'titel' => $stap['titel'],
            'omschrijving' => $stap['omschrijving'] ?? null,
            'eigenaar_id' => $stap['eigenaar_id'] ?? null,
            'deadline' => $stap['deadline'],
            'status' => 'wachtend',
            'volgorde' => $stap['volgorde'],
            'vraagt_uitkomst' => $stap['vraagt_uitkomst'] ?? false,
            'gekoppeld_blok_naam' => $blokNaam,
            'gekoppeld_entiteit_type' => $entiteit->getMorphClass(),
            'gekoppeld_entiteit_id' => $entiteit->getKey(),
            // Bewust leeg: een gevulde `soort` zou de stap laten matchen in
            // TaakPlanner::openstaandeTaken() (§4).
            'soort' => null,
        ]));

        self::activeerVolgendeGroep($entiteit, na: null);

        return $aangemaakt;
    }

    /**
     * De hele reeks, op volgorde. Zonder record-scope: dit is de bron voor het
     * dossierscherm van het bronblok, dat zijn eigen rechten bewaakt.
     *
     * @return Collection<int, Taak>
     */
    public static function voorEntiteit(Model $entiteit): Collection
    {
        return self::query($entiteit)->orderBy('volgorde')->orderBy('id')->get();
    }

    /**
     * De stappen die nu aan de beurt zijn (één groep, dus mogelijk meerdere).
     *
     * @return Collection<int, Taak>
     */
    public static function actueleStappen(Model $entiteit): Collection
    {
        return self::query($entiteit)
            ->whereIn('status', Taak::OPENSTAAND)
            ->orderBy('volgorde')
            ->get();
    }

    /** Alle stappen voltooid. Een lege reeks is niet afgerond maar afwezig. */
    public static function isAfgerond(Model $entiteit): bool
    {
        return self::query($entiteit)->exists()
            && ! self::query($entiteit)->where('status', '!=', 'voltooid')->exists();
    }

    /**
     * Legt het resultaat van een stap vast én voltooit hem, in één handeling —
     * een uitkomst zonder voltooiing hoort niet te kunnen bestaan.
     */
    public static function legUitkomstVast(Taak $taak, string $uitkomst): void
    {
        if (! $taak->isStap()) {
            throw new RuntimeException('Een uitkomst hoort bij een stap, niet bij een losse taak.');
        }

        if (! in_array($uitkomst, self::UITKOMSTEN, true)) {
            throw new RuntimeException("Onbekende uitkomst: {$uitkomst}.");
        }

        // De observer pakt het doorschuiven op; de uitkomst staat dan al in het
        // model, zodat een afkeuring de reeks stil kan zetten.
        $taak->update([
            'uitkomst' => $uitkomst,
            'status' => 'voltooid',
            'voltooid_op' => now(),
        ]);
    }

    /**
     * Zet de reeks terug naar een eerdere stap. De enige route waarlangs een
     * voltooide stap terugkomt (§8) — het takenscherm laat het niet toe.
     *
     * Wordt aangeroepen door het bronblok na een afkeuring; wát er na een
     * afkeuring hoort te gebeuren, is een besluit van dat blok.
     */
    public static function heropenVanaf(Model $entiteit, int $volgorde): void
    {
        foreach (self::query($entiteit)->where('volgorde', '>=', $volgorde)->get() as $stap) {
            $stap->update([
                'status' => 'wachtend',
                'uitkomst' => null,
                'voltooid_op' => null,
                'escalatie_niveau' => 0,
                'escalatie_op' => null,
            ]);
        }

        // Alleen activeren als alles ervóór klaar is. Staat er nog een eerdere
        // stap open, dan activeert die straks vanzelf de volgende groep.
        $onafgerondErvoor = self::query($entiteit)
            ->where('volgorde', '<', $volgorde)
            ->where('status', '!=', 'voltooid')
            ->exists();

        if (! $onafgerondErvoor) {
            self::activeerVolgendeGroep($entiteit, na: null);
        }
    }

    /**
     * Aangeroepen vanuit `TaakObserver` zodra een stap voltooid raakt — vanuit
     * welk scherm of commando dan ook (§7).
     */
    public static function naVoltooiing(Taak $taak): void
    {
        // Een afgekeurde stap schuift niet door: de reeks staat stil tot het
        // bronblok beslist (§8).
        if ($taak->uitkomst === 'afgekeurd') {
            return;
        }

        $entiteit = $taak->entiteit;

        if ($entiteit === null) {
            return;
        }

        // Parallelle stappen: pas doorschuiven als de hele groep klaar is.
        $groepNogBezig = self::query($entiteit)
            ->where('volgorde', $taak->volgorde)
            ->where('status', '!=', 'voltooid')
            ->exists();

        if ($groepNogBezig) {
            return;
        }

        self::activeerVolgendeGroep($entiteit, na: $taak->volgorde);
    }

    /**
     * Activeert de laagste wachtende groep boven `$na`. Bewust niet "$na + 1":
     * een geschrapte stap laat een gat in de nummering achter.
     */
    private static function activeerVolgendeGroep(Model $entiteit, ?int $na): void
    {
        $volgende = self::query($entiteit)
            ->where('status', 'wachtend')
            ->when($na !== null, fn (EloquentBuilder $q) => $q->where('volgorde', '>', $na))
            ->min('volgorde');

        if ($volgende === null) {
            return;
        }

        $stappen = self::query($entiteit)
            ->where('status', 'wachtend')
            ->where('volgorde', $volgende)
            ->get();

        foreach ($stappen as $stap) {
            $stap->update(['status' => 'open']);

            // Zonder bericht is de reeks in de praktijk een wachtrij: de stap
            // verschijnt in een lijst die op dat moment niemand opent (§9).
            if ($stap->eigenaar !== null) {
                NotificatieDispatcher::verzend(
                    'stap_actueel',
                    new StapActueel($stap),
                    collect([$stap->eigenaar]),
                );
            }
        }
    }

    /** De reeks van één entiteit: gekoppelde entiteit plus gevulde volgorde. */
    private static function query(Model $entiteit): EloquentBuilder
    {
        return Taak::query()
            ->where('gekoppeld_entiteit_type', $entiteit->getMorphClass())
            ->where('gekoppeld_entiteit_id', $entiteit->getKey())
            ->whereNotNull('volgorde');
    }
}
