<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Personeelsbeveiliging (ISO 27001 A.6): pre-employment (NDA + screening) en
// offboarding (accounts ingetrokken) als velden op het account.
// Zie implementatie/01b-personeelsbeveiliging.md.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gebruikers', function (Blueprint $table) {
            // Pre-employment (A.6.1 screening, A.6.2/A.6.6 NDA). Nullable-datums:
            // gevuld = gedaan op die datum, leeg = nog niet.
            $table->date('nda_getekend_op')->nullable()->after('vervalt_op');
            $table->enum('screening_type', ['vog', 'referentiecheck'])->nullable()->after('nda_getekend_op');
            $table->date('screening_op')->nullable()->after('screening_type');

            // Offboarding (A.6.5 / A.5.11): de checklist-bevestiging dát de
            // accounts (op de laatste werkdag) zijn ingetrokken. Leeg bij een nog
            // niet bevestigde offboarding.
            $table->date('accounts_ingetrokken_op')->nullable()->after('screening_op');
        });
    }

    public function down(): void
    {
        Schema::table('gebruikers', function (Blueprint $table) {
            $table->dropColumn(['nda_getekend_op', 'screening_type', 'screening_op', 'accounts_ingetrokken_op']);
        });
    }
};
