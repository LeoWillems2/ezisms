<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blok 14 (Notificatie & Integratielaag) — implementatie/14-notificatie-integratielaag.md §3.
return new class extends Migration
{
    public function up(): void
    {
        // De configuratie: welke gebeurtenis mailt wie, aan/uit.
        Schema::create('notificatieregels', function (Blueprint $table) {
            $table->id();
            // Vrije tekst, bedoeld om exact te matchen met wat de code uitzendt.
            // Een type dat nergens wordt uitgezonden vuurt simpelweg nooit.
            $table->string('gebeurtenis_type');
            // Rolnaam (bijv. 'CISO'): alle actieve gebruikers met die rol. Leeg =
            // de betrokkene uit de gebeurtenis (de uitzender levert die als context).
            $table->string('ontvanger_rol')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamps();
            // Geen exacte dubbele regels.
            $table->unique(['gebeurtenis_type', 'ontvanger_rol']);
        });

        // De log van elke uitgaande poging (geen inbox, geen gelezen-status).
        Schema::create('notificaties', function (Blueprint $table) {
            $table->id();
            // nullOnDelete: de log overleeft het verwijderen van een regel.
            $table->foreignId('notificatieregel_id')->nullable()
                ->constrained('notificatieregels')->nullOnDelete();
            // Gedenormaliseerd, zodat de gezondheidshistorie leesbaar blijft ook
            // nadat een regel is verwijderd.
            $table->string('gebeurtenis_type');
            $table->foreignId('gebruiker_id')->nullable()
                ->constrained('gebruikers')->nullOnDelete();
            $table->dateTime('gegenereerd_op');
            $table->dateTime('verzonden_op')->nullable();
            $table->enum('resultaat', ['succes', 'fout']);
            $table->text('fout')->nullable();
            $table->timestamps();
        });

        // Het register: welke externe koppelingen bestaan, van welk type, en of
        // de laatste sync lukte. Bevat bewust géén verbindingsdetails.
        Schema::create('integratie_adapters', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->enum('type', ['identiteit', 'ticketing', 'scanning', 'overig']);
            $table->enum('status', ['niet_geconfigureerd', 'actief', 'inactief'])
                ->default('niet_geconfigureerd');
            $table->dateTime('laatste_synchronisatie_op')->nullable();
            $table->timestamps();
        });

        Schema::create('synchronisatie_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integratie_adapter_id')
                ->constrained('integratie_adapters')->cascadeOnDelete();
            $table->dateTime('tijdstip');
            $table->enum('resultaat', ['succes', 'fout']);
            $table->integer('aantal_verwerkte_records')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synchronisatie_logs');
        Schema::dropIfExists('integratie_adapters');
        Schema::dropIfExists('notificaties');
        Schema::dropIfExists('notificatieregels');
    }
};
