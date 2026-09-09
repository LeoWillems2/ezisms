<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AuditLogregel;
use App\Models\Concerns\Waardenbewaking;
use App\Models\Contractclausule;
use App\Models\Gebruiker;
use App\Models\OverheidsmaatregelBeoordeling;
use App\Models\Reviewsessie;
use App\Models\Sjabloonstap;
use App\Models\Systeem;
use App\Models\Wijziging;

/**
 * De toegestane waarden van elke enum-kolom, aan de codekant
 * (implementatie/00s §10).
 *
 * **Waarom dit bestaat.** Op MySQL bewaakt de database zelf welke waarden in een
 * `enum`-kolom mogen; op SQLite levert dezelfde migratie een kale `varchar` op
 * zonder enige controle. Zonder dit register zouden twee installaties van
 * hetzelfde product verschillende integriteitsgaranties hebben — en dat is een
 * verschil dat een auditor terecht aanwijst.
 *
 * De weg terug (86 kolommen op SQLite herschrijven naar handmatige
 * check-constraints) is geen weg: Laravel kan dat niet declaratief, elke
 * wijziging vraagt daar een tabelherbouw, en het levert een schema op dat van de
 * migraties afwijkt. Dus andersom: de garantie naar de code, waar hij op élke
 * driver geldt.
 *
 * **Onderhoud.** `EnumdekkingTest` leest de migraties tijdens het draaien uit en
 * eist dat deze lijst er precies op past. Een nieuwe enum-kolom zonder regel
 * hier laat de suite dus vallen; dat is het punt.
 *
 * Waar een model de lijst al publiceert, verwijst dit register hem aan in plaats
 * van hem te kopiëren — één waarheid per verzameling.
 *
 * Afdwingen doet {@see Waardenbewaking}; dit bestand weet
 * alleen wát er mag.
 */
final class Enumwaarden
{
    /**
     * Kolommen waarvan een látere migratie de verzameling wijzigde met ruwe SQL,
     * en die de recorder in de toets dus niet ziet. Per kolom staat erbij welke
     * migratie het doet; de toets slaat de vergelijking voor deze kolommen over
     * en eist alleen dat de gedeclareerde waarden een deelverzameling zijn.
     *
     * @var array<string, string>
     */
    public const RUWE_SQL_UITBREIDING = [
        'audit_logregels.actie' => '0001_01_01_000051 verbreedt deze op MySQL met ALTER TABLE (01e §3)',
    ];

    /**
     * 'tabel.kolom' => de toegestane waarden.
     *
     * @return array<string, list<string>>
     */
    public static function alle(): array
    {
        return [
            'afwijkingen.bron' => ['audit_bevinding', 'incident', 'interne_signalering'],
            'afwijkingen.status' => ['open', 'analyse', 'actie_lopend', 'gesloten'],
            'agendapunten.categorie' => Reviewsessie::VERPLICHTE_CATEGORIEEN,
            'assets.beschikbaarheidsniveau' => ['openbaar', 'intern', 'vertrouwelijk', 'geheim'],
            'assets.integriteitsniveau' => ['openbaar', 'intern', 'vertrouwelijk', 'geheim'],
            'assets.persoonsgegevens' => Asset::PERSOONSGEGEVENSSOORTEN,
            'assets.status' => ['geregistreerd', 'actief', 'buiten_gebruik', 'afgestoten'],
            'assets.type' => ['informatie', 'systeem_of_dienst', 'hardware'],
            'assets.vertrouwelijkheidsniveau' => ['openbaar', 'intern', 'vertrouwelijk', 'geheim'],
            'audit_ketencontroles.soort' => ['controle', 'verzegeld'],
            'audit_logregels.actie' => AuditLogregel::ACTIES,
            'auditobjecten.soort' => ['clausule', 'maatregel'],
            'auditplannen.status' => ['concept', 'vastgesteld'],
            'auditprogrammas.aard' => ['voorbereiding', 'certificeringscyclus'],
            'auditprogrammas.status' => ['concept', 'actief', 'afgesloten'],
            'auditrondes.status' => ['gepland', 'in_uitvoering', 'afgerond'],
            'auditronde_auditobject.afhandeling' => [
                'niet_behandeld', 'geen_opmerkingen', 'niet_toegekomen',
            ],
            'auditrondes.type' => [
                'intern', 'intern_nulmeting', 'extern_certificering', 'extern_surveillance',
            ],
            'belanghebbenden.aard' => ['intern', 'extern'],
            'beleidsdocumenten.status' => ['concept', 'ter_goedkeuring', 'actief', 'ingetrokken'],
            'beleidsdocumenten.type' => ['beleid', 'procedure'],
            'beleidsversies.status' => ['concept', 'ter_goedkeuring', 'actief', 'vervangen'],
            'beoordelingsniveaus.as' => ['kans', 'impact'],
            'bevindingen.status' => ['open', 'non_conformiteit_gestart', 'gesloten'],
            'bevindingen.type' => [
                'non_conformiteit_major', 'non_conformiteit_minor', 'observatie',
                'verbeterkans',
            ],
            'bewijsstukken.status' => ['actief', 'gearchiveerd'],
            'classificatieschemas.dimensie' => [
                'vertrouwelijkheid', 'integriteit', 'beschikbaarheid',
            ],
            'classificatieschemas.niveau' => ['openbaar', 'intern', 'vertrouwelijk', 'geheim'],
            'contractclausules.type' => array_keys(Contractclausule::TYPES),
            'corrigerende_maatregelen.status' => ['open', 'in_uitvoering', 'voltooid'],
            'effectiviteitstoetsen.resultaat' => ['effectief', 'niet_effectief'],
            'eisen.bron' => ['contractueel', 'wettelijk', 'verwachting'],
            'gebruikers.screening_type' => array_keys(Gebruiker::SCREENING_TYPES),
            'gebruikers.status' => ['uitgenodigd', 'actief', 'gedeactiveerd', 'geblokkeerd'],
            'incident_meldingen.fase' => ['waarschuwing', 'melding', 'betrokkenen', 'eindverslag'],
            'incident_meldingen.grondslag' => ['avg', 'cbw'],
            'incidenten.ernst' => ['laag', 'midden', 'hoog', 'kritiek'],
            'incidenten.status' => ['gemeld', 'in_onderzoek', 'opgelost', 'gesloten'],
            'integratie_adapters.status' => ['niet_geconfigureerd', 'actief', 'inactief'],
            'integratie_adapters.type' => ['identiteit', 'ticketing', 'scanning', 'overig'],
            'issues.aard' => ['intern', 'extern'],
            'kpi_definities.eenheid' => ['ratio', 'dagen', 'aantal'],
            'kpi_definities.fase' => ['plan', 'do', 'check', 'act'],
            'kpi_definities.richting' => ['omhoog', 'omlaag'],
            'leveranciers.risiconiveau' => ['laag', 'midden', 'hoog'],
            'leveranciers.status' => ['kandidaat', 'actief', 'beeindigd'],
            'maatregelen.thema' => ['organisatorisch', 'mensgericht', 'fysiek', 'technologisch'],
            'notificaties.resultaat' => ['succes', 'fout'],
            'organisatie_eenheden.type' => ['afdeling', 'locatie', 'proces'],
            'overheidsmaatregel_beoordelingen.status' => array_keys(OverheidsmaatregelBeoordeling::STATUS_LABELS),
            'overheidsmaatregelen.status' => ['geldend', 'vervallen', 'verplaatst'],
            'reviewsessies.status' => ['gepland', 'gehouden'],
            'risicobehandelingen.behandeloptie' => [
                'mitigeren', 'accepteren', 'overdragen', 'vermijden',
            ],
            'risicocriteria_versies.status' => [
                'concept', 'ter_goedkeuring', 'actief', 'vervangen',
            ],
            'risicos.status' => [
                'geidentificeerd', 'beoordeeld', 'behandelplan_opgesteld', 'geaccepteerd',
                'in_uitvoering', 'gemitigeerd',
            ],
            'rol_permissies.niveau' => [
                'lezen', 'uitvoeren', 'muteren', 'goedkeuren', 'exporteren',
            ],
            'scope_verklaringen.status' => ['concept', 'ter_goedkeuring', 'actief', 'vervangen'],
            'sjabloonstappen.staptype' => Sjabloonstap::STAPTYPEN,
            'soa_regels.implementatiestatus' => [
                'nvt', 'niet_gestart', 'in_uitvoering', 'geimplementeerd',
            ],
            'synchronisatie_logs.resultaat' => ['succes', 'fout'],
            'systeemhartslag.resultaat' => ['gelukt', 'fout', 'overgeslagen', 'nulpunt'],
            'systemen.beschikbaarheidseis' => Systeem::BESCHIKBAARHEIDSEISEN,
            'systemen.hostingtype' => ['intern', 'extern'],
            'systemen.status' => ['in_gebruik', 'afgevoerd'],
            'taaksjablonen.herhaling' => [
                'eenmalig', 'maandelijks', 'per_kwartaal', 'jaarlijks', 'aangepast',
            ],
            'taken.staptype' => Sjabloonstap::STAPTYPEN,
            'taken.status' => ['wachtend', 'open', 'in_uitvoering', 'voltooid', 'verlopen'],
            'taken.uitkomst' => ['goedgekeurd', 'afgekeurd', 'uitgevoerd', 'nvt'],
            'toetsopdrachten.status' => ['uitgezet', 'gezakt', 'geslaagd'],
            'trainingsvoltooiingen.bron' => ['zelfregistratie', 'toets'],
            'verbeteracties.status' => ['open', 'voltooid'],
            'wijzigingen.soort' => [
                'leveranciersrelease', 'configuratie', 'infrastructuur', 'ingebruikname',
                'afvoer',
            ],
            'wijzigingen.status' => [...Wijziging::LOPEND, ...Wijziging::AFGEROND],
            'wijzigingen.zwaarte' => ['standaard', 'ingrijpend', 'spoed'],
            'wijzigingssjablonen.soort' => [
                'leveranciersrelease', 'configuratie', 'infrastructuur', 'ingebruikname',
                'afvoer',
            ],
            'wijzigingssjablonen.zwaarte' => ['standaard', 'ingrijpend', 'spoed'],
        ];
    }

    /**
     * De toegestane waarden voor één kolom, of null als de kolom geen
     * geregistreerde verzameling heeft.
     *
     * @return list<string>|null
     */
    public static function voor(string $tabel, string $kolom): ?array
    {
        return self::alle()[$tabel.'.'.$kolom] ?? null;
    }

    /**
     * De geregistreerde kolommen van één tabel: kolomnaam => toegestane waarden.
     *
     * @return array<string, list<string>>
     */
    public static function vanTabel(string $tabel): array
    {
        $uit = [];

        foreach (self::alle() as $sleutel => $waarden) {
            [$t, $kolom] = explode('.', $sleutel, 2);

            if ($t === $tabel) {
                $uit[$kolom] = $waarden;
            }
        }

        return $uit;
    }
}
