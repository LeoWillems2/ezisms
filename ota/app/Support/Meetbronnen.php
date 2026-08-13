<?php

namespace App\Support;

use App\Models\AuditLogregel;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Models\BewijsKoppeling;
use App\Models\CorrigerendeMaatregel;
use App\Models\IncidentMelding;
use App\Models\Issue;
use App\Models\Risico;
use App\Models\ScopeVerklaring;
use App\Models\SoaRegel;
use App\Models\Taak;
use App\Models\Wijziging;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * De metingen die de applicatie zelf kan uitrekenen (implementatie/12e §4).
 *
 * Een KPI-definitie wijst een meetbron aan; de berekening zelf blijft code. Dat
 * is de bewuste grens van blok 12e: geen expressietaal en geen querybouwer, want
 * die maken de berekeningswijze onreviewbaar — precies wat §9.1 wél wil. Wie iets
 * buiten het ISMS meet, maakt een handmatige KPI (`meetbron = null`).
 *
 * `sleutel` en `meetbron` zijn bewust twee dingen: de sleutel is de identiteit
 * van de reeks, de meetbron is de berekening. Twee KPI's mogen dezelfde meetbron
 * gebruiken met een andere norm of fase, en een KPI mag van meetbron wisselen
 * zonder zijn historie te verliezen.
 *
 * **Een nieuwe berekening blijft een deploy.** Dat is de eerlijke grens en hij
 * hoort ook in het beheerscherm te staan, niet alleen hier.
 */
final class Meetbronnen
{
    /**
     * Herbeoordelingstermijn — dezelfde eigen keuze als in GenereerTaken: een
     * certificeringscyclus is jaarlijks (implementatie/07 §4).
     */
    private const HERBEOORDELINGSTERMIJN_MAANDEN = 12;

    /**
     * Teller en noemer voor één meetbron, of `null` als die niet bestaat.
     *
     * De aanroeper hoort `bestaat()` te gebruiken om een onbekende meetbron te
     * onderscheiden van een lege populatie; `null` is hier geen normale uitkomst.
     *
     * `$van`/`$tot` zijn het halfopen venster [van, tot) van een
     * **gebeurtenismeting** (12g §3). Toestandsbronnen negeren het; PHP staat
     * toe een closure met meer argumenten aan te roepen dan hij declareert, dus
     * die entries blijven ongewijzigd. `$van` mag `null` zijn: dan is er geen
     * ondergrens — dat is de eerste meting van zo'n KPI.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function bereken(string $meetbron, ?CarbonInterface $van = null, ?CarbonInterface $tot = null): ?array
    {
        $bron = self::registry()[$meetbron] ?? null;

        return $bron === null ? null : ($bron['bereken'])($van, $tot ?? Carbon::today());
    }

    /**
     * Meet deze bron gebeurtenissen in een periode in plaats van de toestand nu?
     * Alleen dan vult `MeetKpis` de periodekolommen.
     */
    public static function isGebeurtenis(string $meetbron): bool
    {
        return (bool) (self::registry()[$meetbron]['gebeurtenis'] ?? false);
    }

    public static function bestaat(string $meetbron): bool
    {
        return isset(self::registry()[$meetbron]);
    }

    /**
     * De keuzelijst voor het beheerscherm: meetbron => label. Het label zegt wat
     * er geteld wordt, niet hoe de KPI heet — die naam kiest de gebruiker zelf.
     *
     * @return array<string, string>
     */
    public static function keuzelijst(): array
    {
        return array_map(fn (array $bron) => $bron['label'], self::registry());
    }

    /**
     * De voorgevulde velden bij een gekozen meetbron. Een suggestie en geen
     * dwang: de gebruiker mag een andere fase of richting kiezen. De suggestie
     * bespaart tikwerk en voorkomt fouten, meer niet.
     *
     * @return array{label: string, eenheid: string, richting: string, berekeningswijze: string}|null
     */
    public static function voorstel(string $meetbron): ?array
    {
        $bron = self::registry()[$meetbron] ?? null;

        if ($bron === null) {
            return null;
        }

        // Expliciet opbouwen en niet `unset`: anders lekt elke nieuwe sleutel in
        // de registry (zoals `gebeurtenis`) door naar het invoerformulier.
        return [
            'label' => $bron['label'],
            'eenheid' => $bron['eenheid'],
            'richting' => $bron['richting'],
            'berekeningswijze' => $bron['berekeningswijze'],
        ];
    }

    /**
     * @return array<string, array{label: string, eenheid: string, richting: string, berekeningswijze: string, gebeurtenis?: bool, bereken: Closure}>
     */
    private static function registry(): array
    {
        return [
            'soa_beoordeeld' => [
                'label' => 'SoA: regels met een beslissing / alle Annex A-maatregelen',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'SoA-regels met een beslissing (van_toepassing niet leeg) gedeeld door het totaal aantal Annex A-maatregelen.',
                'bereken' => fn () => [
                    SoaRegel::whereNotNull('van_toepassing')->count(),
                    SoaRegel::count(),
                ],
            ],
            'soa_toepasselijk_met_beleid' => [
                'label' => 'SoA: toepasselijke regels met actief beleid / alle toepasselijke regels',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'Toepasselijke SoA-regels met minstens één gekoppeld actief beleidsdocument, gedeeld door alle toepasselijke regels.',
                'bereken' => fn () => [
                    SoaRegel::where('van_toepassing', true)
                        ->whereHas('beleidsdocumenten', fn ($q) => $q->where('status', 'actief'))
                        ->count(),
                    SoaRegel::where('van_toepassing', true)->count(),
                ],
            ],
            'risico_met_eigenaar_en_plan' => [
                'label' => "Risico's: met eigenaar én behandeling / alle risico's",
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => "Risico's met zowel een eigenaar als minstens één behandeling, gedeeld door alle risico's.",
                'bereken' => fn () => [
                    Risico::whereNotNull('risico_eigenaar_id')->whereHas('behandelingen')->count(),
                    Risico::count(),
                ],
            ],
            'soa_geimplementeerd' => [
                'label' => 'SoA: geïmplementeerde toepasselijke regels / alle toepasselijke regels',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'Toepasselijke SoA-regels met implementatiestatus geïmplementeerd, gedeeld door alle toepasselijke regels.',
                'bereken' => fn () => [
                    SoaRegel::where('van_toepassing', true)
                        ->where('implementatiestatus', 'geimplementeerd')
                        ->count(),
                    SoaRegel::where('van_toepassing', true)->count(),
                ],
            ],
            'soa_herbeoordeeld_binnen_termijn' => [
                'label' => 'SoA: binnen 12 maanden herbeoordeeld / alle beoordeelde regels',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'Beoordeelde SoA-regels met laatst_beoordeeld_op binnen 12 maanden, gedeeld door alle beoordeelde regels.',
                'bereken' => fn () => [
                    SoaRegel::whereNotNull('van_toepassing')
                        ->whereNotNull('laatst_beoordeeld_op')
                        ->whereDate('laatst_beoordeeld_op', '>=', Carbon::today()->subMonths(self::HERBEOORDELINGSTERMIJN_MAANDEN))
                        ->count(),
                    SoaRegel::whereNotNull('van_toepassing')->count(),
                ],
            ],
            'risico_herbeoordeeld_binnen_termijn' => [
                'label' => "Risico's: herbeoordeling nog niet verstreken / alle risico's",
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => "Risico's met een geplande herbeoordeling die nog niet verstreken is, gedeeld door alle risico's.",
                'bereken' => fn () => [
                    Risico::whereNotNull('volgende_beoordeling_gepland')
                        ->whereDate('volgende_beoordeling_gepland', '>=', Carbon::today())
                        ->count(),
                    Risico::count(),
                ],
            ],
            'incident_tijdig_extern_gemeld' => [
                'label' => 'Incidenten: tijdig extern gemeld / alle meldingen met een termijn',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                // Meldingen zonder uiterlijk_op vallen buiten de noemer: een
                // verplichting zonder termijn kan niet te laat zijn. AVG art. 34
                // kent er geen, en het Cbw-eindverslag bij een voortdurend
                // incident krijgt er pas een bij afhandeling. Dat staat hier
                // omdat het de eerste vraag is die iemand erover stelt.
                'berekeningswijze' => 'Externe meldverplichtingen die op of vóór de wettelijke uiterste datum zijn gedaan, '
                    .'gedeeld door alle verplichtingen mét een uiterste datum. Verplichtingen zonder termijn (AVG art. 34, '
                    .'Cbw art. 29 lid 2 bij een lopend incident) tellen niet mee — die kunnen niet te laat zijn.',
                'bereken' => fn () => [
                    IncidentMelding::whereNotNull('uiterlijk_op')
                        ->whereNotNull('gemeld_op')
                        ->whereColumn('gemeld_op', '<=', 'uiterlijk_op')
                        ->count(),
                    IncidentMelding::whereNotNull('uiterlijk_op')->count(),
                ],
            ],
            'reviewtaken_op_tijd' => [
                'label' => 'Taken: beheerde taken op tijd afgerond / alle voltooide beheerde taken',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'Voltooide beheerde taken waarvan voltooid_op op of vóór de deadline lag, gedeeld door alle voltooide beheerde taken.',
                'bereken' => function () {
                    $taken = self::voltooideBeheerdeTaken();

                    return [
                        $taken->filter(fn (Taak $t) => $t->vertragingInDagen() <= 0)->count(),
                        $taken->count(),
                    ];
                },
            ],
            'reviewtaken_gem_overschrijding' => [
                'label' => 'Taken: som van de overschrijding in dagen / aantal voltooide beheerde taken',
                'eenheid' => 'dagen',
                'richting' => 'omlaag',
                'berekeningswijze' => 'Som van de overschrijding in dagen (voltooid_op na de deadline, minimaal 0) over voltooide beheerde taken, gedeeld door hun aantal.',
                'bereken' => function () {
                    $taken = self::voltooideBeheerdeTaken();

                    // Overschrijding = dagen te laat, minimaal 0 (te vroeg telt
                    // niet mee als negatieve overschrijding); som/aantal is het
                    // gemiddelde.
                    $overschrijding = (int) $taken->sum(fn (Taak $t) => max(0, $t->vertragingInDagen()));

                    return [$overschrijding, $taken->count()];
                },
            ],

            // --- Bronbreedte (implementatie/12d §4) --------------------------
            //
            // Het deelproduct declareert tien bronblokken; tot 12d las de set er
            // drie. Afwijkingen (4.8), bewustzijn (4.6) en auditmanagement (4.9)
            // zijn inmiddels gebouwd, dus de blokkade was weg en de KPI-set was
            // alleen nooit meegegroeid.

            'risico_boven_drempel_met_plan' => [
                'label' => "Risico's: boven de drempel mét behandeling / alle risico's boven de drempel",
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => "Risico's met een score boven de acceptatiedrempel die minstens één behandeling hebben, gedeeld door alle risico's boven die drempel. Staan er geen risico's boven de drempel, dan is er niets te meten en wordt er geen meetpunt vastgelegd.",
                'bereken' => function () {
                    $boven = fn () => Risico::where('risicoscore', '>', Risico::drempelwaarde());

                    // Noemer 0 is hier géén randgeval maar een normale toestand:
                    // geen enkel risico boven de acceptatiedrempel is precies wat
                    // je wilt. `MeetKpis` slaat dat over — 100% zou suggereren
                    // dat er iets goed ging (12d §4).
                    return [$boven()->whereHas('behandelingen')->count(), $boven()->count()];
                },
            ],
            'context_binnen_herzieningstermijn' => [
                'label' => 'Context: issues en scope binnen de herzieningstermijn / alle issues en actieve scopeverklaringen',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'Issues die binnen twaalf maanden zijn beoordeeld, plus actieve scopeverklaringen waarvan de geplande herziening nog niet verstreken is, gedeeld door alle issues plus alle actieve scopeverklaringen. Twee populaties bij elkaar opgeteld: één scopeverklaring naast een handvol issues is als eigen KPI te klein, en samen meten ze hetzelfde — of §4.1 wordt onderhouden.',
                'bereken' => function () {
                    $grens = Carbon::today()->subMonths(self::HERBEOORDELINGSTERMIJN_MAANDEN);
                    $scope = fn () => ScopeVerklaring::where('status', 'actief');

                    return [
                        Issue::whereDate('laatst_beoordeeld_op', '>=', $grens)->count()
                            + $scope()->whereDate('volgende_herziening_gepland', '>=', Carbon::today())->count(),
                        Issue::count() + $scope()->count(),
                    ];
                },
            ],
            'trainingsgraad' => [
                'label' => 'Bewustzijn: afgeronde verplichte trainingen / alle verplichte (medewerker, module)-paren',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'Combinaties van een actieve medewerker en een actieve trainingsmodule die via een gedeelde doelgroep verplicht is, waarvoor een geldige voltooiing bestaat (nog niet verlopen), gedeeld door alle van die verplichte combinaties. Bewust geaggregeerd en niet per doelgroep: het model kent één teller en noemer per KPI; de uitsplitsing staat op het trainingsscherm.',
                'bereken' => fn () => self::trainingsgraad(),
            ],
            'soa_geimplementeerd_met_bewijs' => [
                'label' => 'SoA: geïmplementeerde regels mét bewijs / alle geïmplementeerde toepasselijke regels',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'Toepasselijke SoA-regels met implementatiestatus geïmplementeerd die minstens één gekoppeld bewijsstuk hebben, gedeeld door alle geïmplementeerde toepasselijke regels. Dit meet de keten maatregel → bewijs; een geïmplementeerde maatregel zonder bewijs is bij een audit een bewering.',
                'bereken' => function () {
                    $geimplementeerd = fn () => SoaRegel::where('van_toepassing', true)
                        ->where('implementatiestatus', 'geimplementeerd');

                    return [
                        $geimplementeerd()->whereIn('id', fn ($q) => $q
                            ->select('entiteit_id')
                            ->from('bewijs_koppelingen')
                            ->where('entiteit_type', 'soa_regel')
                        )->count(),
                        $geimplementeerd()->count(),
                    ];
                },
            ],
            'bevindingen_open' => [
                'label' => 'Audits: openstaande bevindingen / alle bevindingen',
                'eenheid' => 'ratio',
                'richting' => 'omlaag',
                'berekeningswijze' => 'Bevindingen die nog niet gesloten zijn, gedeeld door alle bevindingen. Lager is beter.',
                'bereken' => fn () => [
                    Bevinding::where('status', '!=', 'gesloten')->count(),
                    Bevinding::count(),
                ],
            ],
            'dagen_sinds_interne_audit' => [
                'label' => 'Audits: dagen sinds de laatste uitgevoerde interne auditronde',
                'eenheid' => 'dagen',
                'richting' => 'omlaag',
                'berekeningswijze' => 'Het aantal dagen sinds de laatst uitgevoerde interne auditronde. Bestaat die nog niet, dan valt er niets te meten en wordt er geen meetpunt vastgelegd.',
                'bereken' => function () {
                    $laatste = Auditronde::whereIn('type', Auditronde::INTERNE_TYPEN)
                        ->whereNotNull('uitgevoerd_op')
                        ->max('uitgevoerd_op');

                    // Noemer 1: `Meting::gemiddelde()` deelt teller door noemer,
                    // dus dat levert het aantal dagen zelf. Geen schemawijziging
                    // en geen derde eenheid (12d §4).
                    return $laatste === null
                        ? [0, 0]
                        : [(int) Carbon::parse($laatste)->startOfDay()->diffInDays(Carbon::today()), 1];
                },
            ],
            'capa_op_tijd' => [
                'label' => 'Afwijkingen: corrigerende maatregelen op tijd voltooid / alle voltooide met deadline',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'Voltooide corrigerende maatregelen waarvan de voltooiingsdatum op of vóór de deadline lag, gedeeld door alle voltooide corrigerende maatregelen met een deadline. Zonder deadline is "op tijd" niet te bepalen; die tellen dus in geen van beide mee.',
                'bereken' => function () {
                    $voltooid = fn () => CorrigerendeMaatregel::where('status', 'voltooid')
                        ->whereNotNull('voltooid_op')
                        ->whereNotNull('deadline');

                    return [
                        $voltooid()->whereColumn('voltooid_op', '<=', 'deadline')->count(),
                        $voltooid()->count(),
                    ];
                },
            ],
            'capa_doorlooptijd' => [
                'label' => 'Afwijkingen: som van de doorlooptijd in dagen / aantal voltooide corrigerende maatregelen',
                'eenheid' => 'dagen',
                'richting' => 'omlaag',
                'berekeningswijze' => 'De som van het aantal dagen tussen aanmaken en voltooien van corrigerende maatregelen, gedeeld door hun aantal — de gemiddelde doorlooptijd. Lager is beter.',
                'bereken' => function () {
                    $voltooid = CorrigerendeMaatregel::where('status', 'voltooid')
                        ->whereNotNull('voltooid_op')
                        ->get(['created_at', 'voltooid_op']);

                    $dagen = (int) $voltooid->sum(fn (CorrigerendeMaatregel $m) => max(
                        0,
                        (int) $m->created_at->startOfDay()->diffInDays($m->voltooid_op->startOfDay())
                    ));

                    return [$dagen, $voltooid->count()];
                },
            ],

            // --- Act: gebeurtenissen in een periode (implementatie/12g) -------
            //
            // Deze drie meten niet de toestand nu maar wat er tussen twee
            // momenten gebeurde. Ze krijgen daarom `gebeurtenis => true`, zodat
            // `MeetKpis` het venster bepaalt en op de meetrij vastlegt.

            'nieuwe_risicos' => [
                'label' => "Risico's: nieuw geïdentificeerd in de periode",
                'eenheid' => 'aantal',
                // Formeel moet er een richting staan, maar deze KPI heeft er
                // geen: zowel nul als heel veel is een signaal (blok 12 §4 —
                // "monotone verbetering is verdacht"). Hij krijgt daarom geen
                // streefwaarde en blijft `onbepaald`, dus de richting kleurt
                // niets.
                'richting' => 'omhoog',
                'gebeurtenis' => true,
                'berekeningswijze' => "Het aantal risico's dat in de periode is aangemaakt. Bewust een telling en geen aandeel: hetzelfde aantal nieuwe risico's zou als percentage dalen naarmate het register groeit, terwijl de onderliggende activiteit gelijk blijft. Nieuwe risico's zijn bewijs dát er beoordeeld wordt; nul is even goed een signaal als veel.",
                'bereken' => function (?CarbonInterface $van, CarbonInterface $tot) {
                    // Noemer 1 en niet 0: bij een telling is "nul deze periode"
                    // een uitkomst om vast te leggen, geen lege populatie om
                    // over te slaan.
                    return [self::inPeriode(Risico::query(), 'created_at', $van, $tot)->count(), 1];
                },
            ],
            'behandelplannen_afgerond' => [
                'label' => "Risico's: overgangen naar gemitigeerd / alle statusovergangen in de periode",
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'gebeurtenis' => true,
                'berekeningswijze' => "Statusovergangen van risico's naar 'gemitigeerd' in de periode, gedeeld door alle statusovergangen van risico's in die periode. Bewust elke overgang náár gemitigeerd en niet specifiek vanuit 'in_uitvoering': die tussenstatus wordt in de praktijk overgeslagen, en een KPI die alleen díé overgang telt meet structureel nul zonder dat het opvalt.",
                'bereken' => function (?CarbonInterface $van, CarbonInterface $tot) {
                    $overgangen = self::statusovergangen($van, $tot);

                    return [
                        $overgangen->where('nieuw', 'gemitigeerd')->count(),
                        $overgangen->count(),
                    ];
                },
            ],
            'scoredaling_zonder_bewijs' => [
                'label' => "Risico's: scoredalingen zonder bewijs in dezelfde periode / alle scoredalingen",
                'eenheid' => 'ratio',
                'richting' => 'omlaag',
                'gebeurtenis' => true,
                'berekeningswijze' => 'Verlagingen van de risicoscore in de periode waarbij in diezelfde periode géén bewijsstuk aan dat risico is gekoppeld, gedeeld door alle scoreverlagingen. Per gebeurtenis geteld en niet per risico: elke verlaging is een handeling die onderbouwing verdient. Een eerste beoordeling (van geen score naar een score) is geen verlaging. Zonder verlagingen wordt er niets vastgelegd — dat is niets om te meten, geen score van 0%.',
                'bereken' => function (?CarbonInterface $van, CarbonInterface $tot) {
                    $dalingen = self::scoredalingen($van, $tot);

                    if ($dalingen->isEmpty()) {
                        return [0, 0];
                    }

                    $metBewijs = self::inPeriode(
                        BewijsKoppeling::where('entiteit_type', (new Risico)->getMorphClass()),
                        'created_at', $van, $tot
                    )->pluck('entiteit_id')->unique()->flip();

                    return [
                        $dalingen->reject(fn (int $risicoId) => $metBewijs->has($risicoId))->count(),
                        $dalingen->count(),
                    ];
                },
            ],
            // Blok 15 (A.8.32). Let op: dit zijn dossier-KPI's, geen taak-KPI's.
            // Wijzigingsstappen hebben `soort = null` en vallen daarmee buiten
            // `voltooideBeheerdeTaken()`, dus de doorlooptijd-KPI's van blok 7
            // veranderen hier niet van (implementatie/15 §10).
            'wijzigingen_geslaagd' => [
                'label' => 'Wijzigingen: geslaagd / alle geëvalueerde wijzigingen',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'Gesloten wijzigingen waarvan de evaluatie "geslaagd" vermeldt, gedeeld door '
                    .'alle gesloten wijzigingen. Lopende, afgewezen en geannuleerde dossiers tellen niet mee: die '
                    .'zijn nooit uitgevoerd en kunnen dus niet slagen of falen.',
                'bereken' => fn () => [
                    Wijziging::where('status', 'gesloten')->where('geslaagd', true)->count(),
                    Wijziging::where('status', 'gesloten')->count(),
                ],
            ],
            'wijzigingen_met_terugvalplan' => [
                'label' => 'Wijzigingen: uitgevoerd mét terugvalplan / alle uitgevoerde wijzigingen',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                // Hoort structureel 1 te zijn; juist daarom een KPI en geen
                // gap-teller. Een cijfer dat zichtbaar van 1 afwijkt is het
                // signaal (implementatie/15 §10).
                'berekeningswijze' => 'Uitgevoerde en gesloten wijzigingen met een ingevuld terugvalplan, gedeeld '
                    .'door alle uitgevoerde en gesloten wijzigingen. A.8.32 f) vraagt om vangnetprocedures, dus deze '
                    .'waarde hoort 100% te zijn; elke afwijking is een uitvoering zonder vangnet.',
                'bereken' => fn () => [
                    Wijziging::whereIn('status', ['uitgevoerd', 'gesloten'])
                        ->whereNotNull('terugvalplan')->where('terugvalplan', '!=', '')->count(),
                    Wijziging::whereIn('status', ['uitgevoerd', 'gesloten'])->count(),
                ],
            ],
            'spoedwijzigingen_achteraf_goedgekeurd' => [
                'label' => 'Spoedwijzigingen: achteraf goedgekeurd / alle spoedwijzigingen met een goedkeuringsstap',
                'eenheid' => 'ratio',
                'richting' => 'omhoog',
                'berekeningswijze' => 'Spoedwijzigingen waarvan elke goedkeuringsstap een uitkomst heeft, gedeeld '
                    .'door alle spoedwijzigingen die een goedkeuringsstap kennen. De spoedroute is toegestaan '
                    .'(A.8.32 f); het overslaan van de goedkeuring niet.',
                'bereken' => function () {
                    $stappen = Taak::query()
                        ->where('gekoppeld_entiteit_type', 'wijziging')
                        ->where('vraagt_uitkomst', true)
                        ->whereIn('gekoppeld_entiteit_id',
                            Wijziging::where('zwaarte', 'spoed')->select('id'))
                        ->get(['gekoppeld_entiteit_id', 'uitkomst'])
                        ->groupBy('gekoppeld_entiteit_id');

                    return [
                        $stappen->filter(fn ($rijen) => $rijen->every(fn (Taak $s) => $s->uitkomst !== null))->count(),
                        $stappen->count(),
                    ];
                },
            ],
        ];
    }

    /**
     * Venster (van, tot]: ondergrens exclusief, bovengrens inclusief. De
     * ondergrens is de bovengrens van het vorige venster, dus een gebeurtenis op
     * precies dat moment hoort bij het vorige — anders telt hij twee keer.
     * Zonder ondergrens telt alles tot en met `tot`.
     */
    private static function inPeriode($query, string $kolom, ?CarbonInterface $van, CarbonInterface $tot)
    {
        return $query
            ->when($van !== null, fn ($q) => $q->where($kolom, '>', $van))
            ->where($kolom, '<=', $tot);
    }

    /**
     * De trailregels van risico's in de periode.
     *
     * Op de morph-alias via `getMorphClass()` en niet op de letterlijke string
     * 'risico': een hernoemde alias hoort een fout te geven en geen stille nul.
     *
     * @return \Illuminate\Support\Collection<int, AuditLogregel>
     */
    private static function risicoTrail(?CarbonInterface $van, CarbonInterface $tot)
    {
        return self::inPeriode(
            AuditLogregel::where('entiteit_type', (new Risico)->getMorphClass()),
            'tijdstip', $van, $tot
        )->get();
    }

    /**
     * Statusovergangen als {oud, nieuw}-paren.
     *
     * **Niet filteren op `actie = 'status_gewijzigd'`.** `Auditeerbaar` zet die
     * actie alleen als de status het énige gewijzigde veld was; een risico dat
     * tegelijk werd beoordeeld draagt actie `gewijzigd` en zou dan wegvallen.
     * De sleutel `status` in oude/nieuwe waarde is de betrouwbare toets.
     *
     * @return \Illuminate\Support\Collection<int, array{oud: string, nieuw: string}>
     */
    private static function statusovergangen(?CarbonInterface $van, CarbonInterface $tot)
    {
        return self::risicoTrail($van, $tot)
            ->map(fn (AuditLogregel $r) => [
                'oud' => $r->oude_waarde['status'] ?? null,
                'nieuw' => $r->nieuwe_waarde['status'] ?? null,
            ])
            ->filter(fn (array $p) => $p['oud'] !== null && $p['nieuw'] !== null && $p['oud'] !== $p['nieuw'])
            ->values();
    }

    /**
     * De risico-id's per scoreverlaging — één regel per gebeurtenis, dus een
     * risico dat twee keer daalde telt twee keer.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private static function scoredalingen(?CarbonInterface $van, CarbonInterface $tot)
    {
        return self::risicoTrail($van, $tot)
            ->filter(function (AuditLogregel $r) {
                $oud = $r->oude_waarde['risicoscore'] ?? null;
                $nieuw = $r->nieuwe_waarde['risicoscore'] ?? null;

                // Een eerste beoordeling gaat van geen score naar een score; dat
                // is geen daling en mag niet als 0 gelezen worden.
                return $oud !== null && $nieuw !== null && $nieuw < $oud;
            })
            ->map(fn (AuditLogregel $r) => (int) $r->entiteit_id)
            ->values();
    }

    /**
     * De verplichte (medewerker, module)-paren volgen uit
     * `doelgroep_gebruiker ⋈ doelgroep_trainingsmodule`, beperkt tot actieve
     * accounts en actieve modules. Eén medewerker in twee doelgroepen die
     * dezelfde module voorschrijven is één verplichting, vandaar `distinct`.
     *
     * @return array{0: int, 1: int}
     */
    private static function trainingsgraad(): array
    {
        $verplicht = DB::table('doelgroep_gebruiker as dg')
            ->join('doelgroep_trainingsmodule as dm', 'dm.doelgroep_id', '=', 'dg.doelgroep_id')
            ->join('gebruikers as g', 'g.id', '=', 'dg.gebruiker_id')
            ->join('trainingsmodules as m', 'm.id', '=', 'dm.trainingsmodule_id')
            ->where('g.status', 'actief')
            ->where('m.actief', true)
            ->distinct()
            ->get(['dg.gebruiker_id', 'dm.trainingsmodule_id']);

        // Een voltooiing telt zolang ze niet verlopen is; een module zonder
        // geldigheidsduur verloopt nooit.
        $geldig = DB::table('trainingsvoltooiingen')
            ->where(fn ($q) => $q
                ->whereNull('verloopt_op')
                ->orWhereDate('verloopt_op', '>=', Carbon::today())
            )
            ->get(['gebruiker_id', 'trainingsmodule_id'])
            ->map(fn (object $r) => $r->gebruiker_id.':'.$r->trainingsmodule_id)
            ->flip();

        $afgerond = $verplicht
            ->filter(fn (object $p) => $geldig->has($p->gebruiker_id.':'.$p->trainingsmodule_id))
            ->count();

        return [$afgerond, $verplicht->count()];
    }

    /**
     * Voltooide, door een bronblok beheerde taken (soort ≠ null).
     *
     * @return Collection<int, Taak>
     */
    private static function voltooideBeheerdeTaken()
    {
        return Taak::whereNotNull('soort')
            ->where('status', 'voltooid')
            ->whereNotNull('voltooid_op')
            ->get();
    }
}
