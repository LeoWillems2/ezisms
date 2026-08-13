<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tweefactorauthenticatie (implementatie/01d §3).
 *
 * De eerste drie kolommen zijn Engels en dat is een bewuste uitzondering op de
 * naamconventie uit `00-stack-en-conventies.md` §3: Fortify's trait leest
 * `$user->two_factor_secret` rechtstreeks en schrijft met `forceFill()->save()`,
 * dus een accessor-truc zoals bij `wachtwoord` werkt daar niet — `forceFill`
 * gaat om de accessor heen. `tweefactor_deadline` is van onszelf en dus
 * Nederlands. Die inconsistentie binnen één tabel is lelijk, maar ze markeert
 * precies waar de grens tussen framework en domein loopt; dat is eerlijker dan
 * een laag eromheen die de grens verstopt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gebruikers', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            // Per gebruiker, niet globaal: de klok gaat lopen bij zijn eerste
            // bezoek zonder 2FA, en zo is achteraf te zien wanneer dat was.
            $table->date('tweefactor_deadline')->nullable();
        });

        Schema::table('loginpogingen', function (Blueprint $table) {
            // Nullable, en bestaande rijen blijven op null: 'wachtwoord'
            // invullen zou een bewering doen over pogingen van vóór dit plan
            // die niet uit de data volgt. Waarden: wachtwoord, totp,
            // herstelcode, status.
            $table->string('reden')->nullable()->after('succesvol');
        });
    }

    public function down(): void
    {
        Schema::table('gebruikers', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'tweefactor_deadline',
            ]);
        });

        Schema::table('loginpogingen', function (Blueprint $table) {
            $table->dropColumn('reden');
        });
    }
};
