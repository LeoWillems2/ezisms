<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 5 (Beleid & Maatregelbeheer) — implementatie/05-beleid-maatregelbeheer.md §2.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beleidsdocumenten', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            $table->enum('type', ['beleid', 'procedure']);
            $table->text('omschrijving')->nullable();
            // Afgeleid uit de versies (§3a/§3b), onderhouden door
            // BeleidsversieObserver — komt nooit uit een formulier.
            $table->enum('status', ['concept', 'ter_goedkeuring', 'actief', 'ingetrokken'])
                ->default('concept');
            $table->date('ingetrokken_op')->nullable();
            $table->foreignId('eigenaar_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            // Geen ->default(): de waarde volgt bij aanmaken uit `type` (§6) en
            // is daarna een bewuste keuze van de CISO. Een default hier zou die
            // keuze stilzwijgend overschrijven zodra het type wijzigt.
            $table->boolean('leesbevestiging_vereist');
            $table->timestamps();
        });

        Schema::create('beleidsversies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beleidsdocument_id')->constrained('beleidsdocumenten')->cascadeOnDelete();
            $table->unsignedSmallInteger('versienummer');
            // De brug naar blok 6. Nullable: een concept mag bestaan voordat het
            // bestand er is, maar publiceren kan niet zonder (§4).
            $table->foreignId('bewijsstuk_id')->nullable()->constrained('bewijsstukken')->nullOnDelete();
            $table->enum('status', ['concept', 'ter_goedkeuring', 'actief', 'vervangen'])
                ->default('concept');
            $table->text('wijzigingsreden')->nullable();
            $table->date('gepubliceerd_op')->nullable();
            // Afwijking van het deelproduct: FK in plaats van een ingetypte
            // naam (§3c). Een string is niet aan een account te koppelen en
            // daarmee geen auditbewijs.
            $table->foreignId('goedgekeurd_door_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            $table->timestamp('goedgekeurd_op')->nullable();
            $table->date('volgende_herziening_gepland')->nullable();
            $table->timestamps();

            // Expliciete korte namen, zoals bij bewijs_koppelingen (blok 6): de
            // auto-gegenereerde naam gaat hier over de 64 tekens heen.
            $table->unique(['beleidsdocument_id', 'versienummer'], 'bv_document_versie_unique');
            $table->index(['status', 'volgende_herziening_gepland'], 'bv_status_herziening_index');
        });

        Schema::create('leesbevestigingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beleidsversie_id')->constrained('beleidsversies')->cascadeOnDelete();
            $table->foreignId('gebruiker_id')->constrained('gebruikers')->cascadeOnDelete();
            // Timestamp, geen date (§3d): bij een leesbevestiging is het
            // tijdstip het bewijs.
            $table->timestamp('bevestigd_op');
            $table->timestamps();

            // Twee keer bevestigen is geen fout die je in de UI wilt afvangen.
            $table->unique(['beleidsversie_id', 'gebruiker_id'], 'lb_versie_gebruiker_unique');
        });

        // Sluit het koppelvlak dat blok 4 bewust openliet. De koppeling hangt
        // aan het document en niet aan de versie: "welk beleid onderbouwt
        // A.5.1" verandert niet doordat er een nieuwe versie uitkomt (§2).
        Schema::create('beleidsdocument_soa_regel', function (Blueprint $table) {
            $table->foreignId('beleidsdocument_id')->constrained('beleidsdocumenten')->cascadeOnDelete();
            $table->foreignId('soa_regel_id')->constrained('soa_regels')->cascadeOnDelete();
            $table->primary(['beleidsdocument_id', 'soa_regel_id'], 'bd_soa_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beleidsdocument_soa_regel');
        Schema::dropIfExists('leesbevestigingen');
        Schema::dropIfExists('beleidsversies');
        Schema::dropIfExists('beleidsdocumenten');
    }
};
