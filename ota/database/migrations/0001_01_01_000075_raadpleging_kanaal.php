<?php

use App\Models\Raadpleging;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Langs welke weg een bestand is geraadpleegd (implementatie/14b §6.2): in de
 * UI, of door een gekoppelde assistent. Bestaande rijen zijn allemaal `ui`;
 * vóór deze migratie bestond er geen andere weg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raadplegingen', function (Blueprint $tabel) {
            $tabel->enum('kanaal', Raadpleging::KANALEN)->default('ui')->after('geraadpleegd_op');
        });
    }

    public function down(): void
    {
        Schema::table('raadplegingen', function (Blueprint $tabel) {
            $tabel->dropColumn('kanaal');
        });
    }
};
