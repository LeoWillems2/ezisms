<?php

namespace Database\Seeders;

use App\Models\Blok;
use App\Models\Rol;
use App\Models\RolPermissie;
use Illuminate\Database\Seeder;

/**
 * De rechtenmatrix uit deelproducten/01-identity-access.md §4, beperkt tot de
 * MVP-blokken. Een ontbrekende combinatie betekent "geen toegang" — daarom
 * staat er voor Medewerker geen regel bij risico-soa.
 *
 * Management (implementatie/01c) krijgt nergens `muteren`: vaststellen zonder
 * te kunnen herschrijven is de hele reden dat die rol bestaat. Op
 * `identity-access` staat er bewust géén rij — geen gebruikersbeheer.
 *
 * Administrator (implementatie/01e) staat op precies één blok en dat is geen
 * ISMS-blok. Zet er nooit een `lezen`-rij bij "zodat hij kan zien of het werkt":
 * dan is het een account met inzage in het ISMS, en dat is wat deze rol niet is.
 */
class RolPermissieSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            'identity-access' => ['CISO' => ['muteren'], 'Medewerker' => ['lezen'], 'Auditor' => ['lezen']],
            'context-scope' => [
                'CISO' => ['muteren'],
                'Medewerker' => ['lezen'],
                'Auditor' => ['lezen'],
                'Management' => ['goedkeuren'],
            ],
            'asset-classificatie' => [
                'CISO' => ['muteren'],
                'Medewerker' => ['lezen'],
                'Auditor' => ['lezen'],
                'Management' => ['lezen'],
            ],
            'risico-soa' => ['CISO' => ['muteren'], 'Auditor' => ['lezen'], 'Management' => ['goedkeuren']],
            // De Auditor krijgt hier twee rijen: `exporteren` staat buiten de
            // niveau-ladder (zie AppServiceProvider) en impliceert alleen
            // lezen, niet muteren. Medewerker mag eigen bewijs uploaden —
            // record-scoped afgedwongen in het component, niet in de Gate.
            // Management staat hier als Medewerker plus inzage: `uitvoeren` om
            // zelf bewijs te kunnen aanleveren (het reviewverslag komt van de
            // directie), `exporteren` omdat dat op een record-scoped blok het
            // signaal is waarmee Recordscope "alle rijen" van "eigen rijen"
            // onderscheidt — een directeur die alleen zijn eigen bewijs ziet,
            // ziet niets.
            'bewijsrepository-audit-trail' => [
                'CISO' => ['muteren'],
                'Medewerker' => ['uitvoeren'],
                'Auditor' => ['lezen', 'exporteren'],
                'Management' => ['uitvoeren', 'exporteren'],
            ],
            // Zelfde patroon: de Medewerker krijgt `uitvoeren` (eigen taken),
            // en de Auditor `exporteren` zodat Recordscope de twee positief kan
            // onderscheiden in plaats van via "lezen maar niet uitvoeren".
            // Management krijgt hier bewust alléén `uitvoeren`: de directie
            // krijgt zelf taken toegewezen en hoort daar de eigen rijen te
            // zien, niet het volledige takenbord van de organisatie.
            'taken-workflow-engine' => [
                'CISO' => ['muteren'],
                'Medewerker' => ['uitvoeren'],
                'Auditor' => ['lezen', 'exporteren'],
                'Management' => ['uitvoeren'],
            ],
            // Hier staat de functiescheiding die implementatie/01c mogelijk
            // maakte: de CISO stelt beleid op (`muteren`), Management stelt het
            // vast (`goedkeuren`, de publicatieknop). Tot 29-07-2026 had de
            // CISO hier `goedkeuren` en deed hij via de ladder allebei.
            // Medewerker krijgt `uitvoeren`: bevestigen is een schrijfhandeling.
            // Management krijgt naast `goedkeuren` ook `uitvoeren`: een
            // directeur bevestigt zijn eigen leesbevestigingen, en /beleid
            // staat op dat niveau omdat bevestigen een schrijfhandeling is.
            'beleid-maatregelbeheer' => [
                'CISO' => ['muteren'],
                'Medewerker' => ['uitvoeren'],
                'Auditor' => ['lezen', 'exporteren'],
                'Management' => ['uitvoeren', 'goedkeuren'],
            ],
            // Zelfde patroon als bewijs en taken: de Medewerker meldt (een
            // schrijfhandeling) en ziet daarna alleen zijn eigen meldingen.
            // Afwijkingen en corrigerende maatregelen vragen `muteren` — met
            // één uitzondering: een maatregel waarvan hij eigenaar is, mag hij
            // afwerken (implementatie/08 §9).
            // Management: `uitvoeren` om zelf te kunnen melden, `exporteren`
            // om alles te zien. Dat laatste om dezelfde reden als bij bewijs —
            // incidenten zijn verplichte input voor de directiebeoordeling
            // (§9.3), en zonder dat signaal ziet de directie alleen de eigen
            // meldingen. Het afwijkingenoverzicht blijft op `muteren` en dus
            // buiten bereik; de CAPA-cyclus komt bij de directie langs via de
            // reviewagenda, niet via het beheerscherm.
            'incident-afwijkingenbeheer' => [
                'CISO' => ['muteren'],
                'Medewerker' => ['uitvoeren'],
                'Auditor' => ['lezen', 'exporteren'],
                'Management' => ['uitvoeren', 'exporteren'],
            ],
            // Geen record-scoping (leveranciers zijn niet per gebruiker "eigen"),
            // dus geen Medewerker-regel en geen exporteren voor de Auditor —
            // gelijk aan risico-soa (implementatie/09 §8).
            'leveranciers-derdenrisico' => ['CISO' => ['muteren'], 'Auditor' => ['lezen'], 'Management' => ['lezen']],
            // Record-scoping zoals bij bewijs/taken/incident: de Medewerker
            // registreert eigen voltooiing en maakt toegewezen toetsen, en ziet
            // alleen de eigen rijen. De Auditor krijgt `exporteren` zodat
            // Recordscope de twee positief onderscheidt (implementatie/10 §11).
            // De aparte blok-code 'toetsen' uit deelproduct 15 is bewust vervallen.
            // Management staat hier als Medewerker in de rij: eigen training en
            // leesbevestiging, eigen rijen. Een directeur die zijn eigen
            // e-learning niet hoeft te doen, is geen goed voorbeeld.
            'bewustzijn-training' => [
                'CISO' => ['muteren'],
                'Medewerker' => ['uitvoeren'],
                'Auditor' => ['lezen', 'exporteren'],
                'Management' => ['uitvoeren'],
            ],
            // Auditmanagement (blok 11). CISO muteert (plannen, opvolging);
            // Auditor leest+exporteert. Het schrijven van bevindingen loopt níet
            // via deze blok-Gate maar via de record-guard (implementatie/11 §4/§8):
            // de toegewezen interne auditor mag ze op zíjn eigen ronde vastleggen.
            'auditmanagement' => [
                'CISO' => ['muteren'],
                'Auditor' => ['lezen', 'exporteren'],
                'Management' => ['lezen'],
            ],
            // Management review (blok 13). Geen record-scoping (een review is niet
            // "eigen" per gebruiker); gelijk aan risico-soa/leveranciers.
            'management-review-verbetercyclus' => [
                'CISO' => ['muteren'],
                'Auditor' => ['lezen'],
                'Management' => ['goedkeuren'],
            ],
            // Notificatie- & integratielaag (blok 14). Geen record-scoping en
            // geen Medewerker-rij: "notificaties ontvangen" is geen permissie
            // maar volgt uit de rol waarvoor een regel geldt (implementatie/14 §8).
            'notificatie-integratielaag' => ['CISO' => ['muteren'], 'Auditor' => ['lezen'], 'Management' => ['lezen']],
            // Wijzigingsbeheer (blok 15). Melden staat op 'uitvoeren', om
            // dezelfde reden als bij incidenten: drempels op melden leveren
            // minder meldingen op, niet minder wijzigingen.
            //
            // Bewust GEEN record-scoping, anders dan bij taken en bewijs. Daar
            // was "uitvoeren impliceert lezen" een probleem; hier is het de
            // bedoeling — A.8.32 c) verplicht tot het informeren van
            // belanghebbenden, en een wijzigingskalender die alleen de CISO ziet
            // werkt daar tegenin. De scoping die er wél toe doet zit al op de
            // stappen: `Taak::scopeZichtbaar()` toont een Medewerker alleen zijn
            // eigen stappen (implementatie/15 §5).
            'wijzigingsbeheer' => [
                'CISO' => ['muteren'],
                'Medewerker' => ['uitvoeren'],
                'Auditor' => ['lezen', 'exporteren'],
                'Management' => ['lezen'],
            ],
            // Installatiebeheer (implementatie/01e). Eén rij, en de twee dingen
            // die hier NIET staan doen even hard mee:
            //   - de Administrator heeft op geen enkel ander blok een rij, en
            //     dat is precies zijn rol. Het ontbreken van een rij is de
            //     weigering;
            //   - de CISO heeft hier geen rij. Zonder dat is het een rol met
            //     extra rechten in plaats van een rol met andere rechten.
            'installatiebeheer' => ['Administrator' => ['muteren']],
        ];

        $rollen = Rol::pluck('id', 'naam');
        $blokken = Blok::pluck('id', 'code');

        foreach ($matrix as $blokCode => $perRol) {
            foreach ($perRol as $rolNaam => $niveaus) {
                foreach ($niveaus as $niveau) {
                    RolPermissie::updateOrCreate([
                        'rol_id' => $rollen[$rolNaam],
                        'blok_id' => $blokken[$blokCode],
                        'niveau' => $niveau,
                    ]);
                }
            }
        }
    }
}
