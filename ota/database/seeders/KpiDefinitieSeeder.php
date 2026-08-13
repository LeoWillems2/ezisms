<?php

namespace Database\Seeders;

use App\Models\KpiDefinitie;
use App\Support\Meetbronnen;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * De KPI-catalogus (§9.1: wát wordt gemeten en hoe) — referentiedata, draait in
 * beide omgevingen. De nadruk ligt op Check (implementatie/12 §3): dat meet of de
 * cyclus drááit, niet of er ooit iets is gepland. De Act-rijen (score-daling,
 * behandelplan-overgang, nieuwe risico's) hangen aan de audit trail en zijn
 * bewust uitgesteld (§5.3), net als de eenmalige backfill (§5.4).
 *
 * Naam, fase en de norm zijn eigenschappen van de KPI en staan hier; eenheid,
 * richting en berekeningswijze horen bij de meetbron en komen uit
 * `Meetbronnen::voorstel()` (implementatie/12e §4). Zo staan ze op één plek —
 * twee bestanden die dezelfde alinea moeten herhalen, lopen uiteen.
 *
 * Streef- en signaalwaarde staan alleen ingevuld waar ze verdedigbaar zijn
 * (12d §2c). De lege regels zijn geen nalatigheid: een verzonnen norm is
 * slechter dan geen norm, want hij wordt bij de eerste audit als vastgesteld
 * beleid gelezen. Die KPI's tonen als `onbepaald` tot de CISO ze invult.
 */
class KpiDefinitieSeeder extends Seeder
{
    public function run(): void
    {
        $definities = [
            [
                'sleutel' => 'soa_beoordeeld',
                'meetbron' => 'soa_beoordeeld',
                'naam' => 'SoA-regels beoordeeld',
                'fase' => 'plan',
                // Elke Annex A-maatregel hoort een beslissing te hebben.
                'streefwaarde' => 100,
                'signaalwaarde' => 95,
            ],
            [
                'sleutel' => 'soa_toepasselijk_met_beleid',
                'meetbron' => 'soa_toepasselijk_met_beleid',
                'naam' => 'Toepasselijke regels met actief beleid',
                'fase' => 'plan',
                // Geen streefwaarde: vaststellen door de organisatie.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'risico_met_eigenaar_en_plan',
                'meetbron' => 'risico_met_eigenaar_en_plan',
                'naam' => "Risico's met eigenaar én behandelplan",
                'fase' => 'plan',
                // Een risico zonder eigenaar is niemands werk.
                'streefwaarde' => 100,
                'signaalwaarde' => 90,
            ],
            [
                'sleutel' => 'soa_geimplementeerd',
                'meetbron' => 'soa_geimplementeerd',
                'naam' => 'Toepasselijke regels geïmplementeerd',
                'fase' => 'do',
                // Geen streefwaarde: hangt van de fasering af.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'soa_herbeoordeeld_binnen_termijn',
                'meetbron' => 'soa_herbeoordeeld_binnen_termijn',
                'naam' => 'SoA-regels binnen de termijn herbeoordeeld',
                'fase' => 'check',
                // Volgt de jaarlijkse cyclus uit 07 §4.
                'streefwaarde' => 95,
                'signaalwaarde' => 85,
            ],
            [
                'sleutel' => 'risico_herbeoordeeld_binnen_termijn',
                'meetbron' => 'risico_herbeoordeeld_binnen_termijn',
                'naam' => "Risico's binnen de termijn herbeoordeeld",
                'fase' => 'check',
                // Volgt de jaarlijkse cyclus uit 07 §4.
                'streefwaarde' => 95,
                'signaalwaarde' => 85,
            ],
            [
                'sleutel' => 'reviewtaken_op_tijd',
                'meetbron' => 'reviewtaken_op_tijd',
                'naam' => 'Beheerde taken op tijd afgerond',
                'fase' => 'check',
                'streefwaarde' => 90,
                'signaalwaarde' => 75,
            ],
            [
                'sleutel' => 'incident_tijdig_extern_gemeld',
                'meetbron' => 'incident_tijdig_extern_gemeld',
                'naam' => 'Externe meldingen binnen de wettelijke termijn',
                'fase' => 'check',
                // Geen streef- en signaalwaarde, hoe raar dat ook oogt bij een
                // wettelijke termijn. 100% lijkt de enige verdedigbare norm,
                // maar levert een cijfer dat bij één te late melding instort en
                // verder niets stuurt; en een lagere waarde meeleveren zou
                // suggereren dat te laat melden binnen beleid past. De
                // organisatie stelt hem zelf vast.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'reviewtaken_gem_overschrijding',
                'meetbron' => 'reviewtaken_gem_overschrijding',
                'naam' => 'Gemiddelde overschrijding beheerde taken (dagen)',
                'fase' => 'check',
                // Dagen, richting omlaag: streef <= 5, rood boven 15.
                'streefwaarde' => 5,
                'signaalwaarde' => 15,
            ],

            // --- Bronbreedte (implementatie/12d §4) --------------------------

            [
                'sleutel' => 'risico_boven_drempel_met_plan',
                'meetbron' => 'risico_boven_drempel_met_plan',
                'naam' => "Risico's boven de drempel met behandeling",
                'fase' => 'plan',
                // Een risico boven de acceptatiedrempel zonder behandeling is
                // per definitie onaanvaardbaar; hier is 100 geen greep.
                'streefwaarde' => 100,
                'signaalwaarde' => 90,
            ],
            [
                'sleutel' => 'context_binnen_herzieningstermijn',
                'meetbron' => 'context_binnen_herzieningstermijn',
                'naam' => 'Context binnen de herzieningstermijn',
                'fase' => 'check',
                // Geen streefwaarde: hoe vaak de context herzien hoort te worden is een
                // keuze van de organisatie.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'trainingsgraad',
                'meetbron' => 'trainingsgraad',
                'naam' => 'Trainingsgraad verplichte modules',
                'fase' => 'do',
                // Geen streefwaarde: vaststellen door de organisatie.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'soa_geimplementeerd_met_bewijs',
                'meetbron' => 'soa_geimplementeerd_met_bewijs',
                'naam' => 'Geïmplementeerde regels met bewijs',
                'fase' => 'do',
                // Bewust geen norm (12d §4): deze begint rond 0 omdat de keten
                // maatregel → bewijs nog nergens is gelegd. Een streefwaarde
                // hoort er pas als de organisatie besluit hem te gaan leggen.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'bevindingen_open',
                'meetbron' => 'bevindingen_open',
                'naam' => 'Openstaande auditbevindingen',
                'fase' => 'check',
                // Geen streefwaarde: hangt van de auditcadans af.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'dagen_sinds_interne_audit',
                'meetbron' => 'dagen_sinds_interne_audit',
                'naam' => 'Dagen sinds de laatste interne audit',
                'fase' => 'check',
                // Dagen, richting omlaag. Volgt de jaarlijkse cyclus uit 07 §4:
                // binnen een jaar is goed, boven vijftien maanden rood.
                'streefwaarde' => 365,
                'signaalwaarde' => 455,
            ],
            [
                'sleutel' => 'capa_op_tijd',
                'meetbron' => 'capa_op_tijd',
                'naam' => 'Corrigerende maatregelen op tijd voltooid',
                'fase' => 'act',
                'streefwaarde' => 90,
                'signaalwaarde' => 75,
            ],
            [
                'sleutel' => 'capa_doorlooptijd',
                'meetbron' => 'capa_doorlooptijd',
                'naam' => 'Gemiddelde doorlooptijd corrigerende maatregelen (dagen)',
                'fase' => 'act',
                // Geen streefwaarde: vaststellen door de organisatie.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],

            // --- Act op gebeurtenissen (implementatie/12g) --------------------

            [
                'sleutel' => 'nieuwe_risicos',
                'meetbron' => 'nieuwe_risicos',
                'naam' => "Nieuw geïdentificeerde risico's",
                'fase' => 'act',
                // Bewust geen norm: zowel nul als heel veel is een signaal
                // (blok 12 §4). Een streefwaarde zou suggereren dat er een
                // gewenst aantal nieuwe risico's is.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'behandelplannen_afgerond',
                'meetbron' => 'behandelplannen_afgerond',
                'naam' => 'Statusovergangen naar gemitigeerd',
                'fase' => 'act',
                // Geen streefwaarde: hoeveel van de overgangen naar gemitigeerd
                // hóórt te gaan hangt van de fase van het ISMS af.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'scoredaling_zonder_bewijs',
                'meetbron' => 'scoredaling_zonder_bewijs',
                'naam' => 'Scoredalingen zonder onderbouwing',
                'fase' => 'act',
                // Begint op 100% zolang er geen bewijs aan risico's hangt. Zet
                // er pas een norm op als de organisatie besluit die koppeling
                // te gaan leggen — zelfde afweging als bij
                // soa_geimplementeerd_met_bewijs (12d §4).
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'wijzigingen_geslaagd',
                'meetbron' => 'wijzigingen_geslaagd',
                'naam' => 'Wijzigingen geslaagd',
                'fase' => 'check',
                // Geen norm: hoeveel mislukte wijzigingen aanvaardbaar zijn,
                // hangt af van het soort landschap. Dat is aan de organisatie.
                'streefwaarde' => null,
                'signaalwaarde' => null,
            ],
            [
                'sleutel' => 'wijzigingen_met_terugvalplan',
                'meetbron' => 'wijzigingen_met_terugvalplan',
                'naam' => 'Uitvoering met vastgelegd terugvalplan',
                'fase' => 'check',
                // Hier wél een harde norm, anders dan bij de meeste KPI's:
                // A.8.32 f) laat geen ruimte voor een uitvoering zonder vangnet,
                // en de applicatie dwingt het af. Alles onder 100% betekent dat
                // er buiten het systeem om is gewerkt.
                'streefwaarde' => 100,
                'signaalwaarde' => 100,
            ],
            [
                'sleutel' => 'spoedwijzigingen_achteraf_goedgekeurd',
                'meetbron' => 'spoedwijzigingen_achteraf_goedgekeurd',
                'naam' => 'Spoedwijzigingen achteraf goedgekeurd',
                'fase' => 'check',
                'streefwaarde' => 100,
                'signaalwaarde' => 90,
            ],
        ];

        foreach ($definities as $definitie) {
            $voorstel = Meetbronnen::voorstel($definitie['meetbron']);

            if ($voorstel === null) {
                throw new RuntimeException(
                    "Onbekende meetbron '{$definitie['meetbron']}' in de KPI-catalogus."
                );
            }

            // Aanmaken, niet overschrijven. Sinds 12e beheert de CISO deze
            // catalogus zelf: een gewijzigde naam, een bijgestelde norm, een op
            // inactief gezette KPI en een opgehoogde `definitie_versie` zijn nu
            // besluiten van de organisatie. Een seeder die bij elke deploy
            // overheen schrijft, zou die stilzwijgend terugdraaien — en
            // `definitie_versie` terugzetten naar 1 wist bovendien het signaal
            // dat een reeks een breuk kent.
            KpiDefinitie::firstOrCreate(
                ['sleutel' => $definitie['sleutel']],
                $definitie + [
                    'eenheid' => $voorstel['eenheid'],
                    'richting' => $voorstel['richting'],
                    'berekeningswijze' => $voorstel['berekeningswijze'],
                    // Leeg: de meegeleverde streefwaarde is een voorstel tot de
                    // organisatie hem vaststelt (12e §9).
                    'streefwaarde_vastgesteld_op' => null,
                    'definitie_versie' => 1,
                    'actief' => true,
                ],
            );
        }
    }
}
