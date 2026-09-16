<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inloggen via een externe identiteitsprovider (implementatie/01j §2).
 *
 * De inlogmethode staat op het account en wordt niet afgeleid uit het bestaan
 * van een identiteitsrij: een extern account kan tijdelijk zonder koppeling zijn
 * (§9), en die toestand moet benoembaar blijven.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gebruikers', function (Blueprint $tabel) {
            $tabel->enum('inlogmethode', ['wachtwoord', 'extern'])->default('wachtwoord')->after('wachtwoord');
            // Actief extern account zonder koppeling: wanneer de koppellink uitging.
            $tabel->timestamp('koppeling_uitgereikt_op')->nullable()->after('uitnodiging_kanaal');
        });

        Schema::create('externe_identiteiten', function (Blueprint $tabel) {
            $tabel->id();
            // Uniek: één identiteit per account, en via de tweede index één
            // account per identiteit. Opnieuw koppelen vervangt, voegt niet toe.
            $tabel->foreignId('gebruiker_id')->unique()->constrained('gebruikers')->cascadeOnDelete();
            $tabel->string('issuer');
            $tabel->string('subject');
            // Wat de IdP bij het koppelen als adres of gebruikersnaam meegaf.
            // Weergave; de identiteit is issuer + subject.
            $tabel->string('idp_gebruikersnaam')->nullable();
            $tabel->timestamp('gekoppeld_op');
            $tabel->timestamp('laatst_gebruikt_op')->nullable();
            $tabel->timestamps();
            $tabel->unique(['issuer', 'subject']);
        });

        Schema::table('loginpogingen', function (Blueprint $tabel) {
            $tabel->enum('methode', ['wachtwoord', 'extern'])->default('wachtwoord')->after('succesvol');
            // Alleen bij een externe login: stond ISMS_IDP_DWINGT_MFA aan (§7.3).
            $tabel->boolean('mfa_bij_idp')->nullable()->after('methode');
        });
    }

    public function down(): void
    {
        Schema::table('loginpogingen', function (Blueprint $tabel) {
            $tabel->dropColumn(['methode', 'mfa_bij_idp']);
        });

        Schema::dropIfExists('externe_identiteiten');

        Schema::table('gebruikers', function (Blueprint $tabel) {
            $tabel->dropColumn(['inlogmethode', 'koppeling_uitgereikt_op']);
        });
    }
};
