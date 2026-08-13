<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * implementatie/01c: `goedkeuren` verlaat de niveau-ladder en impliceert nog
 * maar één ding — `lezen`. Voor bestaande installaties is dat een rechtenverlies:
 * wie tot nu toe alleen een `goedkeuren`-rij had (de CISO op
 * `beleid-maatregelbeheer`) verloor daarmee stilzwijgend zijn muteerrecht.
 *
 * Deze migratie zet er daarom een `muteren`-rij naast. Bewust conservatief: hij
 * herstelt de status quo en legt de functiescheiding NIET op. Een verse
 * installatie krijgt via RolPermissieSeeder wél de gescheiden matrix (CISO
 * stelt op, Management stelt vast); een bestaande installatie houdt beide
 * rechten bij de CISO tot iemand daar een besluit over neemt. Dat besluit hoort
 * bij de CISO te liggen, niet bij een upgrade.
 *
 * Wil je de scheiding alsnog: verwijder de `goedkeuren`-rij van de CISO op
 * `beleid-maatregelbeheer` en ken de Management-rol toe aan de directie.
 *
 * De Management-rol zelf staat in RolSeeder/RolPermissieSeeder — die draaien
 * bij elke deploy (`migrate --force` gevolgd door `db:seed --force`), dus daar
 * is hier geen migratiecode voor nodig.
 */
return new class extends Migration
{
    public function up(): void
    {
        $bestaand = DB::table('rol_permissies')->where('niveau', 'muteren')
            ->get(['rol_id', 'blok_id'])
            ->map(fn ($rij) => $rij->rol_id.':'.$rij->blok_id)
            ->all();

        $nu = now();

        $nieuw = DB::table('rol_permissies')->where('niveau', 'goedkeuren')
            ->get(['rol_id', 'blok_id'])
            ->reject(fn ($rij) => in_array($rij->rol_id.':'.$rij->blok_id, $bestaand, true))
            ->map(fn ($rij) => [
                'rol_id' => $rij->rol_id,
                'blok_id' => $rij->blok_id,
                'niveau' => 'muteren',
                'created_at' => $nu,
                'updated_at' => $nu,
            ])
            ->values()
            ->all();

        if ($nieuw !== []) {
            DB::table('rol_permissies')->insert($nieuw);
        }
    }

    public function down(): void
    {
        // Niet terug te draaien zonder rechten weg te gooien die inmiddels
        // bewust kunnen zijn toegekend: welke `muteren`-rij hier vandaan komt
        // en welke door de CISO is gezet, is achteraf niet te onderscheiden.
    }
};
