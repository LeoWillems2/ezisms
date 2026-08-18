<?php

namespace App\Support;

use Illuminate\Support\Str;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Normalizer\SlugNormalizer;
use RuntimeException;

/**
 * Register van kennisbankartikelen. De inhoud staat als markdown in
 * `resources/kennisbank/` — versiebeheerd en distribueerbaar, geen runtime-CRUD.
 * Een nieuw artikel toevoegen = een `.md` neerzetten en hier een regel bijzetten.
 *
 * **Per normprofiel** (implementatie/00i): een artikel kan `norm` dragen (dan
 * bestaat het alleen in dat profiel) en `bestand` mag een array profiel => pad
 * zijn (dan levert dezelfde slug per profiel een andere tekst). Zie
 * {@see self::zichtbaar()} voor waarom het filter hier zit en niet in de
 * Livewire-component.
 */
final class Kennisartikelen
{
    /**
     * slug => [titel, categorie, bestand, (bron), (norm)]. De volgorde hier
     * bepaalt de volgorde in de lijst. `bron: 'projectroot'` leest het bestand
     * uit de projectroot i.p.v. `resources/kennisbank/` — voor gegenereerde
     * documenten die daar canoniek staan (de SBOM), zodat er geen tweede kopie
     * ontstaat.
     *
     * @var array<string, array{titel: string, categorie: string, bestand: string|array<string, string>, bron?: string, norm?: string}>
     */
    private const ARTIKELEN = [
        'incidenten-en-afwijkingen' => [
            'titel' => 'Incidenten & afwijkingen: statussen en normkoppeling',
            'categorie' => 'Incidentbeheer',
            'bestand' => 'incidenten-en-afwijkingen.md',
        ],
        'wijzigingsbeheer' => [
            'titel' => 'Wijzigingsbeheer: van aankondiging tot evaluatie',
            'categorie' => 'Incidentbeheer',
            'bestand' => 'wijzigingsbeheer.md',
        ],
        // Het waaróm staat hierboven; dit gaat over de twee tabbladen zelf.
        'wijzigingsbeheer-schermen' => [
            'titel' => 'Wijzigingsbeheer: het register en de sjablonen',
            'categorie' => 'Incidentbeheer',
            'bestand' => 'wijzigingsbeheer-schermen.md',
        ],
        'hr-saas-leverancier-opvoeren' => [
            'titel' => 'Een HR-SaaS-leverancier opvoeren',
            'categorie' => 'Leveranciers & derdenrisico',
            'bestand' => 'hr-saas-leverancier-opvoeren.md',
        ],
        'software-bill-of-materials' => [
            'titel' => 'Software Bill of Materials (SBOM)',
            'categorie' => 'Techniek & beheer',
            // Canoniek in de projectroot; gegenereerd door
            // scripts/genereer-sbom.php. Niet dupliceren naar resources/.
            'bestand' => 'SBOM.md',
            'bron' => 'projectroot',
        ],
        'besluit-opslag-bewijzen-beleidsdocumenten' => [
            'titel' => 'Besluit: opslag van bewijzen en beleidsdocumenten',
            'categorie' => 'Besluiten & architectuur',
            'bestand' => 'besluit-opslag-bewijzen-beleidsdocumenten.md',
        ],
        'kpis-en-meetwaarden' => [
            'titel' => "KPI's en meetwaarden",
            'categorie' => 'Meten & rapportage',
            'bestand' => 'kpis-en-meetwaarden.md',
        ],
        'de-audit-trail' => [
            'titel' => 'De audit trail: wat er in staat, en wat niet',
            'categorie' => 'Meten & rapportage',
            'bestand' => 'de-audit-trail.md',
        ],
        'communicatie-en-overleg' => [
            'titel' => 'Communicatie en overleg vastleggen (§7.4)',
            // Bij de audit trail en de KPI's, want het gaat over aantoonbaar
            // maken dat het managementsysteem draait — en niet over één blok:
            // het artikel leunt op beleid, taken, doelgroepen en bewijs.
            'categorie' => 'Meten & rapportage',
            // Norm-neutraal: clausule 7.4 staat in alle drie de profielen
            // gelijk, en het artikel noemt geen maatregelnummers.
            'bestand' => 'communicatie-en-overleg.md',
        ],
        'issues-en-risicos' => [
            'titel' => "Issues (§4.1) en risico's (§6.1): wat hoort waar?",
            'categorie' => 'Risico & SoA',
            'bestand' => 'issues-en-risicos.md',
        ],
        'soa-onderbouwen-en-restrisico' => [
            'titel' => "De SoA onderbouwen: van 'ja' tot restrisico",
            'categorie' => 'Risico & SoA',
            // Bewust géén variant: dit artikel gaat van begin tot eind over
            // methode (driver → realisatie → bewijs, netto versus bruto), en die
            // is onder beide normen identiek. Zie 00i §0.
            'bestand' => 'soa-onderbouwen-en-restrisico.md',
        ],
        'maatregelclassificatie' => [
            'titel' => 'Maatregelclassificatie: uitgangspunt en eigen vaststelling',
            'categorie' => 'Risico & SoA',
            'bestand' => 'maatregelclassificatie.md',
        ],
        'beheer' => [
            'titel' => "Beheer: de artisan-commando's",
            'categorie' => 'Techniek & beheer',
            'bestand' => 'beheer.md',
        ],
        'normteksten-invoeren' => [
            'titel' => 'De normteksten invoeren',
            'categorie' => 'Techniek & beheer',
            // Bewust gedeeld en geen variant per profiel: het bestand, het
            // commando en de valkuilen zijn in beide profielen gelijk. Wat
            // NEN 7510 erbij heeft — de zorgspecifieke aanvullingen — staat in
            // `wat-nen-7510-toevoegt`, dat toch al over dat veld gaat. Zie
            // 00i §0 voor waarom een variant hier duurder is dan hij oplevert.
            'bestand' => 'normteksten-invoeren.md',
        ],
        'integraties-en-normeis' => [
            'titel' => 'Integraties: welke norm-eis onderbouw je ermee?',
            'categorie' => 'Techniek & beheer',
            // Het antwoord kantelt per norm: NEN 7512 en 7513 zijn zelfstandige
            // normen die precies over koppelvlakken gaan, en die bestaan onder
            // ISO niet.
            'bestand' => [
                'iso27001' => 'integraties-en-normeis.md',
                'nen7510' => 'integraties-en-normeis-nen7510.md',
                // Onder de BIO kantelt het antwoord opnieuw: koppelvlakken met
                // ketenpartners en de verplichte beveiligingsstandaarden van het
                // Forum Standaardisatie zijn hier de onderbouwing, en die bestaan
                // in geen van de andere twee profielen.
                'bio2' => 'integraties-en-normeis-bio2.md',
            ],
        ],
        'open-punten' => [
            'titel' => 'Open punten, bedenkingen en ideeën',
            'categorie' => 'Techniek & beheer',
            'bestand' => 'open-punten.md',
        ],
        'interne-audit-opzetten' => [
            'titel' => 'Een interne audit opzetten (§9.2)',
            'categorie' => 'Audits & certificering',
            'bestand' => 'interne-audit-opzetten.md',
        ],
        'externe-certificeringsaudit' => [
            'titel' => 'De externe certificeringsaudit in het ISMS',
            'categorie' => 'Audits & certificering',
            // De opvolgingsmechaniek is identiek; het stelsel eromheen niet.
            // Doorslaggevend voor de variant: de IGJ is geen certificerende
            // instelling, en dat onderscheid staat nergens in de ISO-versie.
            'bestand' => [
                'iso27001' => 'externe-certificeringsaudit.md',
                'nen7510' => 'externe-certificeringsaudit-nen7510.md',
                // De BIO verplicht geen certificering; wat ervoor in de plaats
                // komt is verantwoording aan de RDI. Dat is een ander gesprek met
                // een andere partij en een andere uitkomst — geen certificaat maar
                // een In Control Verklaring.
                'bio2' => 'externe-certificeringsaudit-bio2.md',
            ],
        ],
        'gebruikers-rollen-en-rechten' => [
            'titel' => 'Gebruikers, rollen en rechten',
            'categorie' => 'Toegang & gebruikers',
            'bestand' => 'gebruikers-rollen-en-rechten.md',
        ],
        'ezisms-voor-de-ciso' => [
            'titel' => 'EzISMS voor de CISO: past dit bij je?',
            'categorie' => 'Naslag',
            // Bewust profielloos: het is een oriëntatiestuk voor wie het systeem
            // nog niet kent, en de keuze tussen de twee profielen is er zelf een
            // onderwerp in. Vandaar geen enkel hard maatregelaantal in de tekst —
            // dat is het enige wat per profiel zou verschillen.
            'bestand' => 'ezisms-voor-de-ciso.md',
        ],
        'ezisms-voor-de-auditor' => [
            'titel' => 'EzISMS voor de externe auditor: een rondleiding',
            'categorie' => 'Naslag',
            // Idem profielloos, en om dezelfde reden: de paragraaf over de norm
            // benoemt beide profielen en wijst naar het menu, zodat de lezer zelf
            // ziet welk profiel deze installatie draait.
            'bestand' => 'ezisms-voor-de-auditor.md',
        ],
        'sitestructuur' => [
            'titel' => 'Sitestructuur',
            'categorie' => 'Naslag',
            'bestand' => 'sitestructuur.md',
        ],
        'de-cyberbeveiligingswet-in-het-isms' => [
            'titel' => 'De Cyberbeveiligingswet: waar hij dit systeem raakt',
            'categorie' => 'Naslag',
            // Bewust profielloos en bewust géén variant. Cbw-plichtigheid hangt
            // af van sector en omvang en staat los van de norm die je volgt (zie
            // `config/meldplicht.php` en `config/norm.php`): een ISO-installatie
            // kan eronder vallen en een BIO-installatie niet. Een variant per
            // profiel zou juist die verwarring bevestigen. De enige
            // profielgebonden paragraaf — de reikwijdte-markering uit de BIO —
            // staat als zodanig gemarkeerd in de tekst; hetzelfde patroon als de
            // twee oriëntatieartikelen hierboven.
            'bestand' => 'de-cyberbeveiligingswet-in-het-isms.md',
        ],
        'wat-nen-7510-toevoegt' => [
            'titel' => 'Wat NEN 7510 toevoegt bovenop ISO 27001',
            'categorie' => 'Naslag',
            'bestand' => 'wat-nen-7510-toevoegt.md',
            'norm' => 'nen7510',
        ],
        'verantwoording-en-disclaimer' => [
            'titel' => 'Verantwoording en disclaimer',
            'categorie' => 'Naslag',
            // Dezelfde slug in beide profielen, en dat moet: sinds 04f is
            // Maatregel::GEEN_OMSCHRIJVING_AANHEF de enige verwijzing hierheen
            // vanuit de SoA, en die staat bij élke maatregel. Eén linkbron in
            // code in plaats van 93 in data — maar breekt hij, dan breekt hij
            // overal tegelijk.
            'bestand' => [
                'iso27001' => 'verantwoording-en-disclaimer.md',
                'nen7510' => 'verantwoording-en-disclaimer-nen7510.md',
                // In dit profiel heeft de pagina twee verhalen te vertellen in
                // plaats van één: waarom de ISO-omschrijvingen ontbreken (zoals
                // overal), én waarom de BIO-tekst ontbreekt — dat laatste om een
                // andere reden, namelijk de CC BY-NC-SA-licentie.
                'bio2' => 'verantwoording-en-disclaimer-bio2.md',
            ],
        ],
        'wat-de-bio-toevoegt' => [
            'titel' => 'Wat de BIO toevoegt bovenop ISO 27001',
            'categorie' => 'Naslag',
            'bestand' => 'wat-de-bio-toevoegt.md',
            'norm' => 'bio2',
        ],
    ];

    /**
     * Artikelen die niet als Word-document te downloaden zijn.
     *
     * `sitestructuur` is één inline SVG met de menuboom erin, en pandoc laat
     * die bij de conversie naar docx vallen: wat je overhoudt is de legenda
     * zonder het diagram waar ze naar verwijst. Een download die stilzwijgend
     * het onderwerp van de pagina weglaat is erger dan geen download — vandaar
     * dat de knop er niet staat in plaats van dat hij een half document geeft.
     *
     * @var list<string>
     */
    private const NIET_DOWNLOADBAAR = ['sitestructuur'];

    /**
     * Is dit artikel als document mee te nemen? Zegt niets over *wie* dat mag —
     * dat is de autorisatiecheck `kennisartikel-downloaden` — maar over of er
     * een bruikbaar document van te maken is.
     */
    public static function isDownloadbaar(string $slug): bool
    {
        return self::bestaat($slug) && ! in_array($slug, self::NIET_DOWNLOADBAAR, true);
    }

    /**
     * De artikelen die in het actieve normprofiel bestaan, met `bestand`
     * opgelost naar het pad voor dít profiel.
     *
     * @return array<string, array{titel: string, categorie: string, bestand: string, bron?: string, norm?: string}>
     */
    public static function alles(): array
    {
        return self::zichtbaar();
    }

    /** De slug van het eerste artikel (voor de standaardweergave). */
    public static function eersteSlug(): ?string
    {
        return array_key_first(self::zichtbaar());
    }

    public static function bestaat(string $slug): bool
    {
        return isset(self::zichtbaar()[$slug]);
    }

    /**
     * @return array{titel: string, categorie: string, bestand: string, bron?: string, norm?: string}|null
     */
    public static function metadata(string $slug): ?array
    {
        return self::zichtbaar()[$slug] ?? null;
    }

    /**
     * Het register zoals dit profiel het kent.
     *
     * **Het filter zit hier en niet in de Livewire-component**, en dat is de
     * hele opzet: `alles()`, `bestaat()`, `eersteSlug()` en `metadata()` lezen
     * er allemaal uit, dus een rechtstreekse URL naar het artikel van het andere
     * profiel geeft 404 in plaats van een gerenderde pagina. En
     * {@see Kennisbankzoeker::zoek()} loopt over `alles()`, dus die volgt vanzelf
     * — zou het filter in de component zitten, dan zou de zoeker treffers geven
     * in artikelen die niet op te vragen zijn.
     *
     * Niet gememoriseerd: het profiel ligt vast per installatie en dit is een
     * `array_filter` over achttien regels.
     *
     * @return array<string, array{titel: string, categorie: string, bestand: string, bron?: string, norm?: string}>
     */
    private static function zichtbaar(): array
    {
        $profiel = Normprofiel::actief();
        $zichtbaar = [];

        foreach (self::ARTIKELEN as $slug => $meta) {
            if (($meta['norm'] ?? $profiel) !== $profiel) {
                continue;
            }

            $meta['bestand'] = self::bestandVoor($slug, $meta['bestand'], $profiel);
            $zichtbaar[$slug] = $meta;
        }

        return $zichtbaar;
    }

    /**
     * Het bestand van dit artikel in dit profiel.
     *
     * Een array zonder sleutel voor het actieve profiel gooit, net als
     * {@see Normprofiel::label()}. Stilzwijgend overslaan zou het artikel in dat
     * ene profiel laten verdwijnen zonder dat iets dat meldt — en dan mist er een
     * verantwoordingspagina waar 93 maatregelomschrijvingen naar verwijzen.
     *
     * @param  string|array<string, string>  $bestand
     */
    private static function bestandVoor(string $slug, string|array $bestand, string $profiel): string
    {
        if (is_string($bestand)) {
            return $bestand;
        }

        if (! array_key_exists($profiel, $bestand)) {
            throw new RuntimeException(
                "Kennisartikel '{$slug}' heeft geen bestand voor normprofiel '{$profiel}'. Aanwezig: "
                .implode(', ', array_keys($bestand)).'.'
            );
        }

        return $bestand[$profiel];
    }

    /** Het pad naar het markdown-bestand, of null als slug/bestand ontbreekt. */
    public static function pad(string $slug): ?string
    {
        $meta = self::metadata($slug);
        if ($meta === null) {
            return null;
        }

        $pad = ($meta['bron'] ?? null) === 'projectroot'
            ? base_path($meta['bestand'])
            : resource_path('kennisbank/'.$meta['bestand']);

        return is_file($pad) ? $pad : null;
    }

    /** De ruwe markdown-inhoud, of null als slug/bestand ontbreekt. */
    public static function inhoud(string $slug): ?string
    {
        $pad = self::pad($slug);

        if ($pad === null) {
            return null;
        }

        $markdown = (string) file_get_contents($pad);

        // Een eigen H1 bovenaan het bestand weghalen: de kennisbank toont de
        // titel al apart (uit dit register), dus een '# ...'-regel zou die
        // dubbelen. Alleen de eerste regel, alleen als het een H1 is.
        return preg_replace('/\A\x{FEFF}?#\s+.*\R+/u', '', $markdown, 1);
    }

    /**
     * De gerenderde HTML van een artikel, of null als slug/bestand ontbreekt.
     *
     * Koppen krijgen een `id` (implementatie/00g §5), zodat een zoekresultaat
     * naar een paragraaf kan wijzen in plaats van naar de bovenkant van een
     * artikel van tweeduizend woorden. `insert: none` omdat we alleen het anker
     * willen en niet het ¶-symbool ernaast; `apply_id_to_heading` zet het id op
     * de kop zelf. De prefixen staan leeg zodat het anker gelijk is aan
     * `Kennisbankzoeker::anker()` — die berekent hem met dezelfde
     * SlugNormalizer, en KennisbankzoekTest bewaakt dat ze niet uit elkaar
     * lopen.
     */
    public static function html(string $slug): ?string
    {
        $markdown = self::inhoud($slug);

        if ($markdown === null) {
            return null;
        }

        // Inhoud komt uit de repo (vertrouwd), niet van gebruikers; markdown →
        // HTML met de GitHub-flavored converter (tabellen, code, etc.).
        return Str::markdown($markdown, [
            'heading_permalink' => [
                'insert' => 'none',
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'fragment_prefix' => '',
            ],
        ], [new HeadingPermalinkExtension]);
    }

    /** Het kopanker (fragment) van een kop, gelijk aan het `id` uit `html()`. */
    public static function anker(string $kop): string
    {
        return (new SlugNormalizer)->normalize($kop);
    }
}
