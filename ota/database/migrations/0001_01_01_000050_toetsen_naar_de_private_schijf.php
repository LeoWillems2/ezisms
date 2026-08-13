<?php

use App\Support\ToetsBestanden;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * De toetsbestanden van `public/toetsen/` naar `storage/app/private/toetsen/`
 * (implementatie/01e §1.6).
 *
 * Geen schemawijziging: `toetsopdrachten.toets_bestand` blijft de bestandsnaam,
 * dus lopende opdrachten overleven de verhuizing ongewijzigd. Wat verandert is
 * waar dat bestand gezocht wordt, en dat zit in de code.
 *
 * Bestands-I/O in een migratie is ongebruikelijk en hier toch de juiste plek:
 * het moet precies één keer per installatie gebeuren, het hangt aan deze
 * codewijziging, en beide uitrolroutes draaien migraties al. Het alternatief —
 * een apart commando — moet in `deploy.sh` én in `deploy-docker.sh` aangeroepen
 * worden en is dan op één van de twee te vergeten.
 *
 * KOPIËREN, niet verplaatsen. Loopt de uitrol daarna alsnog stuk, dan staan de
 * originelen er nog en werkt de vorige release gewoon. `public/toetsen` en de
 * symlink naar `shared/toetsen` blijven daarom in deze release staan; het
 * opruimen daarvan is een release later (01e §1.7).
 */
return new class extends Migration
{
    public function up(): void
    {
        $bron = public_path('toetsen');

        if (! is_dir($bron)) {
            return;
        }

        $disk = Storage::disk(ToetsBestanden::DISK);

        foreach (File::glob($bron.'/*.html') ?: [] as $pad) {
            $bestand = basename($pad);

            // Idempotent: een tweede keer draaien overschrijft niets. Wie na de
            // migratie een toets bijwerkt, wil die wijziging niet kwijtraken aan
            // een herhaalde uitrol.
            if ($disk->exists($bestand)) {
                continue;
            }

            $disk->put($bestand, (string) File::get($pad));
        }
    }

    /**
     * Leeg, met opzet. `up()` kopieert en gooit niets weg, dus er is niets te
     * herstellen; de bestanden in `public/toetsen/` staan er nog. Hier de kopieën
     * wissen zou bij een terugrol werk vernietigen dat ná de migratie is
     * geüpload.
     */
    public function down(): void
    {
        //
    }
};
