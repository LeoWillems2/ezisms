<?php

namespace Database\Seeders;

use App\Models\Classificatieschema;
use Illuminate\Database\Seeder;

/**
 * De 12 rijen van het classificatieschema (3 dimensies x 4 niveaus). Alleen de
 * structuur wordt geseed; omschrijving en omgangsregels blijven leeg — dat is
 * organisatie-specifiek beleid dat de CISO zelf invult (implementatie/03 §5).
 *
 * De niveaus verschillen per dimensie: vertrouwelijkheid en integriteit gebruiken
 * de gevoeligheidsschaal, beschikbaarheid de A.8.14-schaal die gelijk loopt met
 * de beschikbaarheidseis op een systeem (implementatie/03 §2).
 */
class ClassificatieschemaSeeder extends Seeder
{
    public function run(): void
    {
        $niveausPerDimensie = [
            'vertrouwelijkheid' => ['openbaar', 'intern', 'vertrouwelijk', 'geheim'],
            'integriteit' => ['openbaar', 'intern', 'vertrouwelijk', 'geheim'],
            'beschikbaarheid' => ['niet_kritiek', 'normaal', 'hoog', 'bedrijfskritiek'],
        ];

        foreach ($niveausPerDimensie as $dimensie => $niveaus) {
            foreach ($niveaus as $niveau) {
                Classificatieschema::updateOrCreate([
                    'dimensie' => $dimensie,
                    'niveau' => $niveau,
                ]);
            }
        }
    }
}
