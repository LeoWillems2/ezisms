<?php

/**
 * Het normprofiel van deze installatie (implementatie/00h-normprofiel.md).
 *
 * NEN 7510 is een superset van ISO 27001, geen afwijkende norm: hoofdstuk 4–10
 * is dezelfde Harmonized Structure, en Bijlage A is de 93 ISO-maatregelen plus
 * zorgspecifieke aanvullende tekst plus 8 extra maatregelen. Superset en
 * additief betekent: een profielschakelaar, geen fork.
 *
 * **Vastgelegd bij de installatie en daarna immutable.** Welk profiel actief is
 * staat sinds 05-08-2026 in de database (tabel `normprofiel`, migratie 000048) en
 * niet meer in `.env`: het is een eigenschap van deze installatie en geen
 * instelling die tussen twee deploys door te wijzigen hoort te zijn. Een wissel
 * zou de SoA van controlset laten veranderen terwijl de beoordelingen blijven
 * staan, en dat is precies de situatie waarin een auditrapport onbruikbaar wordt.
 * Van norm wisselen betekent dus: de database opnieuw opbouwen.
 *
 * Los van `ISMS_CBW_PLICHTIG` in `config/meldplicht.php`: onder welke wet je
 * valt hangt af van sector en omvang, niet van welke norm je volgt.
 */
return [

    /*
     * Leeg, en dat hoort zo: de waarde komt uit de tabel `normprofiel`.
     * {@see App\Support\Normprofiel::actief()} leest die één keer en zet hem
     * hier neer als cache voor de rest van het verzoek.
     *
     * Vul deze sleutel niet in een configuratiebestand — dan wint hij van de
     * database en volgt de applicatie een andere norm dan de installatie zegt te
     * volgen. De testsuite zet hem wél rechtstreeks; die heeft geen rij nodig om
     * een profiel te toetsen.
     *
     * `ISMS_NORM` uit `.env` speelt alleen nog bij het opzetten van de database;
     * zie `keuze` hieronder.
     */
    'actief' => null,

    /*
     * ── De installatiekeuze die de seeders lezen ─────────────────────────────
     *
     * Hij staat hier en niet als `env()`-aanroep in de seeder zelf, en dat is geen
     * stijlkwestie. `scripts/deploy.sh` draait `artisan config:cache` vóór
     * `db:seed`, en zodra de configuratie gecached is slaat Laravel het inlezen
     * van `.env` volledig over — `LoadEnvironmentVariables::bootstrap()` keert
     * meteen terug zodra `configurationIsCached()`. Elke `env()` búiten een
     * configuratiebestand levert dan de standaardwaarde op, hoe stellig `.env`
     * ook iets anders zegt.
     *
     * Dat is één keer echt misgegaan: een uitrol met `ISMS_NORM=nen7510` zette
     * `iso27001` in de database en seedde 93 in plaats van 101 maatregelen. Alleen
     * configuratiebestanden worden nog uitgevoerd vóór het cachen, dus alleen hier
     * overleeft de waarde de stap.
     */

    /*
     * Wat `NormprofielSeeder` in de nog lege tabel `normprofiel` zet — en verder
     * niets. Dit is niet het actieve profiel: staat de rij er eenmaal, dan is
     * deze sleutel betekenisloos en wint de database. Leeg laten levert de
     * uitgeleverde standaard op; die staat in de seeder, want daar is het een
     * beslissing en hier alleen een doorgeefluik.
     */
    'keuze' => env('ISMS_NORM'),

    /*
     * Hier stond tot 06-08-2026 ook `maatregelen_bron` (`MAATREGELEN_BRON`), de
     * keuze tussen de gekochte normtekst en de eigen omschrijvingen. Die keuze
     * bestaat niet meer: er is één maatregelbestand per profiel, de installatie
     * bewerkt dat bestand zelf, en er valt dus niets meer te kiezen (04f §8.2).
     * De waarschuwing hierboven blijft onverkort gelden voor `keuze`.
     */

    /*
     * Waar `MaatregelSeeder` de bestanden `maatregelen-<profiel>.json` zoekt.
     * Leeg = `database/seeders/data`, en zo hoort het op een installatie.
     *
     * Deze sleutel bestaat voor de testsuite: daar staat het bestand van de
     * installatie zélf, met de door de CISO overgetypte normtekst erin, en een
     * test die dat overschrijft gooit dat werk weg. Dat is één keer gebeurd bij
     * de capaciteiten en mag niet nog eens.
     */
    'maatregelenmap' => null,

    'profielen' => [

        'iso27001' => [
            'labels' => [
                'naam' => 'ISO/IEC 27001',
                'naam_kort' => 'ISO 27001',
                // Het label van de maatregelenbijlage. De nummers erin lopen in
                // beide normen door; alleen hoe de bijlage heet verschilt.
                'bijlage' => 'Annex A',
                'certificeringsdoel' => 'ISO 27001-certificering',
                /*
                 * Wat je van een leverancier vraagt, is iets anders dan wat je
                 * zelf volgt: ISO 27001 is wat leveranciers internationaal
                 * aantoonbaar hebben. Meevertalen zou het ISMS om een
                 * certificaat laten vragen dat de leverancier niet kan leveren.
                 */
                'leverancierscertificaat' => 'ISO 27001',
            ],
            'capaciteiten' => [],
        ],

        'nen7510' => [
            'labels' => [
                'naam' => 'NEN 7510',
                'naam_kort' => 'NEN 7510',
                'bijlage' => 'Bijlage A',
                // Geen ISO-certificering maar het zorgstelsel: NCS 7510 onder
                // accreditatie van de RvA, en het toetsingskader van de IGJ.
                'certificeringsdoel' => 'NCS 7510-certificering (RvA) / IGJ-toetsingskader',
                'leverancierscertificaat' => 'NEN 7510 of ISO 27001',
            ],
            /*
             * De haakjes waar stap 4 t/m 6 aan hangen (nen7510-opzet.md §7).
             * Ze staan er nu al zodat het profiel compleet is; er zijn nog geen
             * gebruikers van.
             */
            'capaciteiten' => [
                // De zorgspecifieke aanvulling per maatregel (stap 4).
                'zorgaanvulling',
                // Zorgterminologie in schaalteksten en vocabulaire (stap 6).
                'zorgterminologie',
            ],
        ],

        /*
         * De Baseline Informatiebeveiliging Overheid 2
         * (deelproducten/04b-bio-overheidsmaatregelen.md).
         *
         * Anders van vorm dan NEN 7510, en dat is de kern van het onderzoek in
         * implementatie/00q. NEN 7510 is een superset op hetzelfde niveau: 93 + 8
         * rijen in `maatregelen`, plus een tekstveld per maatregel. De BIO laat
         * Bijlage A volledig ongemoeid — geen enkele nieuwe maatregel, geen enkele
         * hernummerd — en hangt er een niveau ónder: 118 individueel genummerde
         * overheidsmaatregelen, tot zeven onder één beheersmaatregel. Vandaar één
         * nieuwe entiteit en géén derde maatregelenset.
         *
         * De uitgave is BIO2 v1.3; de sleutel is `bio2` en niet `bio13`, want de
         * norm heet BIO2 en 1.3 is de documentversie.
         */
        'bio2' => [
            'labels' => [
                'naam' => 'Baseline Informatiebeveiliging Overheid 2',
                'naam_kort' => 'BIO2',
                // De BIO2 verwijst voor de maatregelenbijlage naar NEN-EN-ISO/IEC
                // 27002:2022 en noemt die zelf "Bijlage A".
                'bijlage' => 'Bijlage A',
                /*
                 * Het enige label zonder certificaat om naar te wijzen: *"De BIO
                 * verplicht geen NEN-EN-ISO/IEC 27001-certificering."* Wat ervoor
                 * in de plaats komt is verantwoording — een jaarlijkse In Control
                 * Verklaring (overheidsmaatregel 5.36.01) en toezicht door de RDI
                 * onder de Cyberbeveiligingswet.
                 */
                'certificeringsdoel' => 'Verantwoording van de Cbw-zorgplicht aan de RDI',
                /*
                 * Blijft ISO 27001, om dezelfde reden als bij NEN 7510: je vraagt
                 * je hostingpartij niet om de BIO omdat jíj die volgt. Een gemeente
                 * vraagt haar leverancier eventueel wél om aansluiting bij de BIO
                 * langs de inkoopeisen uit deel 1 §13, maar dat is een
                 * contractclausule en geen certificaat — en `Contractclausule`
                 * bestaat al.
                 */
                'leverancierscertificaat' => 'ISO 27001',
            ],
            /*
             * Eén capaciteit en niet twee. Het onderzoek stelde ook
             * `cbw-reikwijdte` voor, als aparte schakelaar voor de grijs
             * gemarkeerde maatregelen. Die is er niet gekomen: hij kan niet
             * onafhankelijk van deze aan of uit, want de reikwijdte is een
             * uitspraak van de BIO en heeft buiten een BIO-installatie geen
             * betekenis. Twee capaciteiten die altijd samen optreden zijn geen
             * keuze maar ruis.
             *
             * Let op: dit is iets anders dan `ISMS_CBW_PLICHTIG` in
             * `config/meldplicht.php`. Dat zegt of déze organisatie onder de
             * Cyberbeveiligingswet valt (sector en omvang); `cbw_reikwijdte` zegt
             * of de BIO een maatregel binnen die wet plaatst. Ze vermenigvuldigen
             * elkaar en mogen niet in elkaar geschoven worden — deel 1 §11.1
             * noemt de BIO-entiteit die buiten de Cbw valt expliciet, en dan geldt
             * de BIO als verplichtende zelfregulering.
             */
            'capaciteiten' => [
                'overheidsmaatregelen',
            ],
        ],
    ],
];
