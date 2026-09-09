<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 2 (Context & Scope) — de vrije tekst met de gegevens van de organisatie
// zelf, boven de organisatie-eenheden.
return new class extends Migration
{
    public function up(): void
    {
        // Enkelvoud, en dat is geen vergissing tegen de conventie: er hoort per
        // installatie precies één rij in te staan. Een meervoudsnaam zou
        // suggereren dat je er een tweede organisatie naast kunt zetten, en
        // daar is nergens in het ISMS iets op ingericht.
        Schema::create('organisatieprofiel', function (Blueprint $table) {
            $table->id();
            // Nullable, want een verse installatie heeft nog niets ingevuld.
            // `text` en niet `string`: de invoer is meerregelig, en de
            // bovengrens van 2000 tekens is een keuze van het scherm die hier
            // niet vast hoort te liggen.
            $table->text('gegevens')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisatieprofiel');
    }
};
