<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 12 (Metrics, KPI & Rapportage — deel 1: de meting) — implementatie/12 §5.
return new class extends Migration
{
    public function up(): void
    {
        // Wát gemeten wordt, hoe en door wie (§9.1). De berekeningswijze en de
        // versie leggen de definitie vast; wijzigt de berekening, dan hoort de
        // versie omhoog zodat een breuk in de reeks zichtbaar is (§2b).
        Schema::create('kpi_definities', function (Blueprint $table) {
            $table->id();
            $table->string('sleutel')->unique();
            $table->string('naam');
            $table->enum('fase', ['plan', 'do', 'check', 'act']);
            // 'ratio' = teller/noemer als percentage; 'dagen' = teller/noemer als
            // gemiddelde (bijv. overschrijding in dagen).
            $table->enum('eenheid', ['ratio', 'dagen'])->default('ratio');
            $table->text('berekeningswijze');
            $table->unsignedSmallInteger('definitie_versie')->default(1);
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });

        // Onveranderlijke meetpunten (§2c): nooit een update of herberekening,
        // een fout wordt met een nieuw meetpunt + toelichting gecorrigeerd.
        Schema::create('metingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_definitie_id')->constrained('kpi_definities')->cascadeOnDelete();
            $table->date('gemeten_op');
            // Teller én noemer, nooit het percentage (§2a): "61 van 90" is uit te
            // leggen en te reconstrueren, "68%" niet — en de noemer beweegt mee.
            $table->unsignedInteger('teller');
            $table->unsignedInteger('noemer');
            // De definitieversie ín de meetrij (§2b): een berekeningsbreuk wordt
            // zichtbaar in plaats van verstopt.
            $table->unsignedSmallInteger('definitie_versie');
            $table->text('toelichting')->nullable();
            $table->timestamps();

            $table->index(['kpi_definitie_id', 'gemeten_op']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metingen');
        Schema::dropIfExists('kpi_definities');
    }
};
