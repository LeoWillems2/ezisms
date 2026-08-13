<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blok 15 (Wijzigingsbeheer), implementatie/15 §1. Het register achter A.8.32:
 * de maatregel stond wel in de SoA, maar er was geen registratie van de
 * wijzigingen zelf.
 *
 * De stappen van een wijziging staan NIET hier maar in `taken`, met een gevulde
 * `volgorde` en een koppeling naar de wijziging (07b §3).
 */
return new class extends Migration
{
    private const SOORTEN = ['leveranciersrelease', 'configuratie', 'infrastructuur', 'ingebruikname', 'afvoer'];

    private const ZWAARTES = ['standaard', 'ingrijpend', 'spoed'];

    public function up(): void
    {
        Schema::create('wijzigingssjablonen', function (Blueprint $table) {
            $table->id();
            $table->string('naam')->unique();
            $table->text('omschrijving')->nullable();
            $table->enum('soort', self::SOORTEN);
            $table->enum('zwaarte', self::ZWAARTES)->default('standaard');
            $table->boolean('actief')->default(true);
            $table->timestamps();
        });

        Schema::create('sjabloonstappen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wijzigingssjabloon_id')->constrained('wijzigingssjablonen')->cascadeOnDelete();
            $table->unsignedSmallInteger('volgorde');
            $table->string('titel');
            $table->text('omschrijving')->nullable();
            // De vijf staptypen zijn code en geen configuratie: elk type heeft
            // eigen gedrag (07b/deelproduct 15 §2). Alleen `goedkeuring` raakt
            // de engine, via `vraagt_uitkomst`.
            $table->enum('staptype', ['analyse', 'goedkeuring', 'informeren', 'uitvoeren', 'evaluatie']);
            $table->foreignId('standaard_eigenaar_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            // Signed: negatief = vóór `gepland_op`. Eén anker met offsets is
            // minder om fout te doen dan een losse datum per stap (§1).
            $table->integer('deadline_offset_dagen')->default(0);
            $table->boolean('bewijs_verplicht')->default(false);
            $table->foreignId('doelgroep_id')->nullable()->constrained('doelgroepen')->nullOnDelete();
            // Leeg = een afkeuring wijst het dossier af; gevuld = de reeks
            // springt terug naar die volgorde (§7).
            $table->unsignedSmallInteger('bij_afkeuren_terug_naar')->nullable();
            $table->timestamps();

            $table->index(['wijzigingssjabloon_id', 'volgorde'], 'sjabloonstappen_volgorde_index');
        });

        Schema::create('wijzigingen', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            // Nullable + nullOnDelete: de reeks staat in `taken` en overleeft het
            // verwijderen van het sjabloon; een lopend dossier mag daar niet op
            // stuklopen.
            $table->foreignId('wijzigingssjabloon_id')->nullable()
                ->constrained('wijzigingssjablonen')->nullOnDelete();
            // Gekopieerd van het sjabloon en niet afgeleid: een sjabloon mag
            // later wijzigen, het dossier moet vasthouden wat er gold.
            $table->enum('soort', self::SOORTEN);
            $table->enum('zwaarte', self::ZWAARTES)->default('standaard');
            $table->foreignId('leverancier_id')->nullable()->constrained('leveranciers')->nullOnDelete();
            $table->foreignId('aangemeld_door_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            // Het ticketnummer uit een ITSM-systeem. Eén veld, geen koppeling.
            $table->string('externe_referentie')->nullable();
            $table->date('aangekondigd_op')->nullable();
            // De voorgenomen datum, ingevuld bij het in behandeling nemen: de
            // stapdeadlines hangen eraan via de offsets (§2b).
            $table->date('gepland_op')->nullable();
            $table->date('uitgevoerd_op')->nullable();
            $table->text('impact_toelichting')->nullable();
            $table->text('terugvalplan')->nullable();
            // Zes waarden, geen acht: `goedgekeurd` en `gepland` staan al in de
            // reeks en zouden hier een tweede bron van waarheid worden (§2a).
            $table->enum('status', [
                'aangemeld', 'in_behandeling', 'uitgevoerd', 'gesloten', 'afgewezen', 'geannuleerd',
            ])->default('aangemeld');
            $table->boolean('geslaagd')->nullable();
            $table->boolean('teruggedraaid')->default(false);
            $table->text('evaluatie')->nullable();
            $table->timestamps();

            $table->index(['status', 'gepland_op'], 'wijzigingen_status_planning_index');
        });

        Schema::create('systeem_wijziging', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wijziging_id')->constrained('wijzigingen')->cascadeOnDelete();
            $table->foreignId('systeem_id')->constrained('systemen')->cascadeOnDelete();

            $table->unique(['wijziging_id', 'systeem_id']);
        });

        // Afwijking van implementatie/15 §1, zie §15 van dat plan: de reeks is
        // wél te vinden via (entiteit, volgorde), maar de bijbehorende
        // sjabloonstap niet — parallelle stappen delen hun volgorde. Zonder deze
        // FK is het staptype van een stap niet te bepalen, en daar hangen de
        // terugvalplancontrole en het terugspringen bij afkeuren aan.
        Schema::table('taken', function (Blueprint $table) {
            $table->foreignId('sjabloonstap_id')->nullable()->after('volgorde')
                ->constrained('sjabloonstappen')->nullOnDelete();
        });

        Schema::table('incidenten', function (Blueprint $table) {
            // Onvoldoende beheersing van veranderingen is volgens A.8.32 een
            // veelvoorkomende oorzaak van storingen; zonder dit veld is die
            // relatie achteraf niet meer te leggen.
            $table->foreignId('wijziging_id')->nullable()->after('id')
                ->constrained('wijzigingen')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incidenten', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wijziging_id');
        });

        Schema::table('taken', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sjabloonstap_id');
        });

        Schema::dropIfExists('systeem_wijziging');
        Schema::dropIfExists('wijzigingen');
        Schema::dropIfExists('sjabloonstappen');
        Schema::dropIfExists('wijzigingssjablonen');
    }
};
