<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 7 (Taken- & Workflow-engine) — implementatie/07-taken-workflow-engine.md §2.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taaksjablonen', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->text('omschrijving')->nullable();
            $table->enum('herhaling', ['eenmalig', 'maandelijks', 'per_kwartaal', 'jaarlijks', 'aangepast']);
            // Alleen gevuld bij herhaling = 'aangepast'; anders af te leiden.
            $table->unsignedSmallInteger('interval_dagen')->nullable();
            $table->string('bron_blok', 50);
            $table->foreignId('standaard_eigenaar_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            // Een jaarlijkse taak die pas op de deadline verschijnt is nutteloos.
            $table->unsignedSmallInteger('aanmaken_dagen_vooraf')->default(14);
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });

        Schema::create('taken', function (Blueprint $table) {
            $table->id();
            // Nullable: losse eenmalige taken hebben geen sjabloon.
            $table->foreignId('taaksjabloon_id')->nullable()->constrained('taaksjablonen')->nullOnDelete();
            $table->string('titel');
            $table->text('omschrijving')->nullable();
            $table->foreignId('eigenaar_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->date('deadline');
            $table->enum('status', ['open', 'in_uitvoering', 'voltooid', 'verlopen'])->default('open');
            // Zelfde polymorfe patroon als bewijs_koppelingen (implementatie/06 §4).
            $table->string('gekoppeld_blok_naam', 50)->nullable();
            $table->string('gekoppeld_entiteit_type', 50)->nullable();
            $table->unsignedBigInteger('gekoppeld_entiteit_id')->nullable();
            // Onderscheidt "risico-herbeoordeling" van "bewijs-upload" op
            // dezelfde entiteit — nodig voor de idempotentie van TaakPlanner.
            $table->string('soort', 50)->nullable();
            $table->unsignedTinyInteger('escalatie_niveau')->default(0); // 0 | 1 | 2
            $table->date('escalatie_op')->nullable();
            // Maakt de vertraging berekenbaar (deadline -> voltooid_op).
            $table->date('voltooid_op')->nullable();
            $table->timestamps();

            // Idempotentie op databaseniveau i.p.v. "de generator is
            // voorzichtig". Losse taken blijven vrij: taaksjabloon_id is daar
            // NULL, en NULL-waarden botsen niet in een unique index — dat is
            // gewenst, niet een gat om te "repareren".
            $table->unique(['taaksjabloon_id', 'deadline'], 'taken_sjabloon_deadline_unique');

            $table->index(['status', 'deadline'], 'taken_status_deadline_index');
            $table->index(['gekoppeld_entiteit_type', 'gekoppeld_entiteit_id'], 'taken_entiteit_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taken');
        Schema::dropIfExists('taaksjablonen');
    }
};
