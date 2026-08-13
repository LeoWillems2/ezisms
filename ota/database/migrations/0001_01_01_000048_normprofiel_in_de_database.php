<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welke norm deze installatie volgt, als gegeven in plaats van als instelling
 * (implementatie/00h-normprofiel.md, herzien 05-08-2026).
 *
 * Stond eerst in `ISMS_NORM` in `.env`. Dat werkte, maar het maakte van een
 * eigenschap van de installatie een regel in een configuratiebestand die iemand
 * tussen twee deploys door kan omzetten — en dan wisselt de controlset terwijl
 * de SoA-beoordelingen blijven staan. Hier vastleggen maakt de keuze onderdeel
 * van de installatie zelf: hij wordt gezet bij het opzetten van de database en
 * verandert daarna niet meer. Wie van norm wil wisselen, zet een nieuwe
 * installatie op.
 *
 * Eén rij, geen `gebruiker_id`, geen scherm om hem te wijzigen. De rij wordt
 * geschreven door `NormprofielSeeder`, die `ISMS_NORM` uitleest — die
 * omgevingsvariabele geldt vanaf nu **alleen bij de installatie** en wordt
 * tijdens het draaien nergens meer gelezen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('normprofiel', function (Blueprint $table) {
            $table->id();
            // Geen enum: welke profielen bestaan staat in config/norm.php, en een
            // enum in het schema zou dat op twee plekken zetten. De seeder toetst
            // de waarde tegen die lijst en weigert een onbekend profiel.
            $table->string('profiel');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('normprofiel');
    }
};
