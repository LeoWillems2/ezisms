<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De BIO-overheidsmaatregelen: een extra detailniveau onder Bijlage A
 * (deelproducten/04b-bio-overheidsmaatregelen.md §2).
 *
 * Twee tabellen met een scherpe scheidslijn. `overheidsmaatregelen` is
 * referentiedata uit de norm — geseed, niet ingevoerd, en niet auditeerbaar; een
 * nieuwe BIO-uitgave hoort geen auditregels op te leveren.
 * `overheidsmaatregel_beoordelingen` is van de organisatie en dus wél
 * auditeerbaar. Dezelfde scheiding als `maatregelen` en `soa_regels`.
 *
 * De tabellen worden in élk profiel aangemaakt en alleen in `bio2` gevuld. Dat is
 * dezelfde keuze als bij `maatregelen.zorgaanvulling`: een migratie die per
 * normprofiel iets anders doet, maakt een profielwissel tot een schemawissel, en
 * dan is een installatie niet meer met één commando bij te werken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overheidsmaatregelen', function (Blueprint $table) {
            $table->id();
            // '5.24.03' — de eerste twee delen zijn de beheersmaatregel, het derde
            // het volgnummer daarbinnen. Uniek: dit nummer is waar een auditrapport
            // naar verwijst.
            $table->string('nummer')->unique();
            $table->foreignId('maatregel_id')->constrained('maatregelen')->cascadeOnDelete();
            $table->unsignedSmallInteger('volgnummer');
            // Nullable: bij een vervallen of verplaatst nummer zegt `status` alles
            // en zou een mededeling over ontbrekende tekst alleen ruis zijn.
            $table->text('tekst')->nullable();
            // Valt deze verplichting onder de Cyberbeveiligingswet? Nee bij de
            // grijs gemarkeerde regels; daar geldt verplichtende zelfregulering.
            $table->boolean('cbw_reikwijdte')->default(true);
            // Vervallen en verplaatste nummers blijven staan: "bestaat niet" is een
            // ander antwoord dan "verplaatst naar 5.26.02" (04b §2).
            $table->enum('status', ['geldend', 'vervallen', 'verplaatst'])->default('geldend');
            $table->string('verwezen_naar')->nullable();
            $table->timestamps();
        });

        Schema::create('overheidsmaatregel_beoordelingen', function (Blueprint $table) {
            $table->id();
            // Expliciete korte FK-namen: de auto-gegenereerde variant overschrijdt
            // de 64-tekengrens van MySQL — zelfde reden als bij
            // `risicobehandeling_soa_regel`.
            $table->unsignedBigInteger('soa_regel_id');
            $table->unsignedBigInteger('overheidsmaatregel_id');
            $table->foreign('soa_regel_id', 'ombo_soa_fk')
                ->references('id')->on('soa_regels')->cascadeOnDelete();
            $table->foreign('overheidsmaatregel_id', 'ombo_om_fk')
                ->references('id')->on('overheidsmaatregelen')->cascadeOnDelete();
            $table->unique(['soa_regel_id', 'overheidsmaatregel_id'], 'ombo_unique');

            // `niet_beoordeeld` is de begintoestand en heeft daarom een default;
            // `niet_belegd` is een normale, opslaanbare uitkomst. Een ISMS dat
            // alleen "belegd" laat vastleggen levert een VvT op waarin niets
            // ontbreekt, en dat is precies het document dat niemand gelooft.
            $table->enum('status', [
                'niet_beoordeeld', 'belegd', 'deels_belegd', 'niet_belegd', 'niet_van_toepassing',
            ])->default('niet_beoordeeld');
            $table->text('motivatie')->nullable();
            // De onderbouwende risicoanalyse bij een uitzondering (deel 1 §7).
            // Nullable, want de koppeling wordt vanuit de risicokant gelegd; de
            // afwezigheid is een signaal en geen blokkade — zie het model.
            $table->foreignId('risicobehandeling_id')->nullable()
                ->constrained('risicobehandelingen')->nullOnDelete();
            $table->date('laatst_beoordeeld_op')->nullable();
            $table->foreignId('beoordeeld_door_id')->nullable()
                ->constrained('gebruikers')->nullOnDelete();
            $table->timestamps();
        });

        // Op de beheersmaatregel en niet alleen op de overheidsmaatregel: van de
        // drie beheersmaatregelen buiten de Cbw-reikwijdte hebben er twee
        // helemaal geen overheidsmaatregel, en dan is er geen rij om de vlag te
        // dragen (04b §2). Default true: in ISO en NEN 7510 is de Cbw geen
        // onderwerp en is "binnen bereik" de onschuldige stand.
        Schema::table('maatregelen', function (Blueprint $table) {
            $table->boolean('cbw_reikwijdte')->default(true)->after('thema');
        });
    }

    public function down(): void
    {
        Schema::table('maatregelen', function (Blueprint $table) {
            $table->dropColumn('cbw_reikwijdte');
        });

        Schema::dropIfExists('overheidsmaatregel_beoordelingen');
        Schema::dropIfExists('overheidsmaatregelen');
    }
};
