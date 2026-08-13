<?php

namespace Database\Seeders;

use App\Models\Auditobject;
use Illuminate\Database\Seeder;

/**
 * De auditbare (sub)clausules van de ISO 27001-hoofdtekst (H4-H10) als
 * audit-objecten (plan 11b §3). De titels zijn **eigen korte omschrijvingen** en
 * geen ISO-clausuletekst.
 *
 * Ze blijven staan waar de maatregelomschrijvingen zijn verdwenen (04f §1.1a),
 * en dat is geen inconsistentie: de clausulenummers en hun onderwerpen zijn
 * openbaar — de Harmonized Structure is op hoofdlijnen breed gepubliceerd — dus
 * een auditor die een titel betwist krijgt een bron in plaats van een
 * interpretatie. Bij een maatregelomschrijving is de norm zelf het enige
 * vergelijkingspunt, en dáár lag het bezwaar.
 *
 * Idempotent op `clausule_nummer`; de maatregel-objecten komen apart via
 * `isms:sync-auditobjecten`.
 */
class AuditobjectClausuleSeeder extends Seeder
{
    /**
     * @var array<string, array{0: string, 1: string}> nummer => [groep, eigen titel]
     */
    private const CLAUSULES = [
        '4.1' => ['4 Context', 'Organisatie en context begrijpen'],
        '4.2' => ['4 Context', 'Behoeften en verwachtingen van belanghebbenden'],
        '4.3' => ['4 Context', 'Scope van het ISMS bepalen'],
        '4.4' => ['4 Context', 'Het managementsysteem zelf'],

        '5.1' => ['5 Leiderschap', 'Leiderschap en betrokkenheid directie'],
        '5.2' => ['5 Leiderschap', 'Informatiebeveiligingsbeleid'],
        '5.3' => ['5 Leiderschap', 'Rollen, verantwoordelijkheden en bevoegdheden'],

        '6.1' => ['6 Planning', 'Aanpak van risico\'s en kansen'],
        '6.2' => ['6 Planning', 'Beveiligingsdoelstellingen en planning'],
        '6.3' => ['6 Planning', 'Planning van wijzigingen'],

        '7.1' => ['7 Ondersteuning', 'Middelen'],
        '7.2' => ['7 Ondersteuning', 'Competentie'],
        '7.3' => ['7 Ondersteuning', 'Bewustzijn'],
        '7.4' => ['7 Ondersteuning', 'Communicatie'],
        '7.5' => ['7 Ondersteuning', 'Gedocumenteerde informatie'],

        '8.1' => ['8 Uitvoering', 'Operationele planning en beheersing'],
        '8.2' => ['8 Uitvoering', 'Risicobeoordeling uitvoeren'],
        '8.3' => ['8 Uitvoering', 'Risicobehandeling uitvoeren'],

        '9.1' => ['9 Evaluatie', 'Monitoren, meten, analyseren en evalueren'],
        '9.2' => ['9 Evaluatie', 'Interne audit'],
        '9.3' => ['9 Evaluatie', 'Directiebeoordeling'],

        '10.1' => ['10 Verbetering', 'Continue verbetering'],
        '10.2' => ['10 Verbetering', 'Afwijking en corrigerende maatregel'],
    ];

    public function run(): void
    {
        $volgorde = 0;

        foreach (self::CLAUSULES as $nummer => [$groep, $titel]) {
            Auditobject::updateOrCreate(
                ['soort' => 'clausule', 'clausule_nummer' => $nummer],
                [
                    'titel' => $titel,
                    'groep' => $groep,
                    'volgorde' => $volgorde += 10,
                    'actief' => true,
                ],
            );
        }
    }
}
