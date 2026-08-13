<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Referentiedata, geen testdata: het ISMS functioneert niet zonder deze rollen.
 * Zie deelproducten/01-identity-access.md §4.
 */
class RolSeeder extends Seeder
{
    public function run(): void
    {
        $rollen = [
            [
                'naam' => 'CISO',
                'omschrijving' => 'Eigenaar van het ISMS; beheert risico\'s, maatregelen, beleid en gebruikers.',
            ],
            [
                'naam' => 'Medewerker',
                'omschrijving' => 'Voert toegewezen taken uit, meldt incidenten en bevestigt beleid; geen beheerrechten.',
            ],
            [
                'naam' => 'Auditor',
                'omschrijving' => 'Interne of externe auditor met read-only inzage ten behoeve van auditbewijs.',
            ],
            [
                'naam' => 'Management',
                'omschrijving' => 'Directie: stelt scope, beleid en restrisico\'s vast en sluit de '
                    .'directiebeoordeling af. Geen beheerrechten.',
            ],
            // De enige rol die niet over het ISMS gaat maar over de installatie.
            // Hij is onverenigbaar met elke andere rol; `Rolregels` bewaakt dat.
            [
                'naam' => 'Administrator',
                'omschrijving' => 'Technisch beheer van de installatie; plaatst toetsbestanden. '
                    .'Geen enkele toegang tot de inhoud van het ISMS.',
            ],
        ];

        foreach ($rollen as $rol) {
            Rol::updateOrCreate(['naam' => $rol['naam']], $rol);
        }
    }
}
