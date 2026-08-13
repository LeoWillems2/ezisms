<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een systeem werd hard verwijderd: de rij verdween, de assetkoppelingen
 * vervielen via cascade en er bleef niets in de audit trail. Dat is de
 * asymmetrie met een asset, die bij afstoten juist een status krijgt én wordt
 * geaudit (implementatie/03 §4, 06 §3). Systemen krijgen daarom dezelfde
 * behandeling: een `afgevoerd`-status in plaats van een delete, zodat het
 * register de historie behoudt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systemen', function (Blueprint $table) {
            $table->enum('status', ['in_gebruik', 'afgevoerd'])->default('in_gebruik')->after('leverancier_id');
            $table->date('afgevoerd_op')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('systemen', function (Blueprint $table) {
            $table->dropColumn(['status', 'afgevoerd_op']);
        });
    }
};
