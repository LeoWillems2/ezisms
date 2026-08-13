<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maakt ruimte voor gebeurtenissen die een *verzameling* raken in plaats van één
 * rij — te beginnen met het opschonen van `raadplegingen` (implementatie/05 §14).
 *
 * Het alternatief was `entiteit_id = 0` als schijn-verwijzing. Dat leest in de
 * trail als een bestaande rij met id 0 en is dus een onwaarheid op precies de
 * plek waar dat het duurst is. `null` zegt wat het is: deze gebeurtenis gaat
 * niet over een individueel record.
 *
 * Bestaande regels houden hun waarde; de indexen blijven werken, want MySQL
 * indexeert NULL gewoon mee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logregels', function (Blueprint $table) {
            $table->unsignedBigInteger('entiteit_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logregels', function (Blueprint $table) {
            $table->unsignedBigInteger('entiteit_id')->nullable(false)->change();
        });
    }
};
