<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jaarlijkse, onveranderlijke restrisico-snapshot per control (plan 04c §2). De
 * fase-1-rollup (max netto-restrisico + aantal risico's) wordt één keer per jaar
 * weggeschreven zodat er een trend ontstaat die niet met terugwerkende kracht te
 * herrekenen is. `toelichting` mag later gevuld worden met de reden van beweging.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restrisico_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soa_regel_id')->constrained('soa_regels')->cascadeOnDelete();
            $table->unsignedSmallInteger('peiljaar');
            // Nullable: "onbepaald" — wel een gekoppeld risico, maar geen restrisico
            // ingevuld. Bewust geen 0 (dat zou "geen restrisico" suggereren).
            $table->unsignedSmallInteger('max_restrisico')->nullable();
            $table->unsignedSmallInteger('aantal_risicos');
            // Versie van de rollup-definitie; verandert de berekening, dan is de
            // breuk in de reeks zichtbaar (zelfde gedachte als Meting).
            $table->unsignedInteger('definitie_versie')->default(1);
            $table->text('toelichting')->nullable();
            $table->timestamps();

            // Eén snapshot per control per jaar: dubbel vastleggen is uitgesloten.
            $table->unique(['soa_regel_id', 'peiljaar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restrisico_snapshots');
    }
};
