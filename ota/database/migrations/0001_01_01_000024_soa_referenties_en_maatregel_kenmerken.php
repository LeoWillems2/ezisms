<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Prompt p11: lichte vervanging van het (bevroren) blok 4a. Geen normtekst in de
// SoA, maar eigen referentievelden per regel + een compacte classificatie op de
// maatregel.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soa_regels', function (Blueprint $table) {
            // Korte, eigen verwijzingen van de organisatie (bijv. een
            // hoofdstuknummer). Naast de gestructureerde beleidsdocument-koppeling
            // uit blok 5, niet ter vervanging daarvan.
            $table->string('beleidreferentie')->nullable()->after('motivatie');
            $table->string('procesreferentie')->nullable()->after('beleidreferentie');
        });

        Schema::table('maatregelen', function (Blueprint $table) {
            // De meegeleverde uitgangsclassificatie; de dimensies staan in
            // config/maatregelkenmerken.php. Referentiedata, geseed door
            // MaatregelKenmerkenSeeder uit een eigen distribueerbaar bestand —
            // los van de tekstbron, zodat ze ook bij de gelicentieerde
            // normtekst aanwezig blijft.
            $table->json('kenmerken')->nullable()->after('omschrijving');
        });
    }

    public function down(): void
    {
        Schema::table('maatregelen', function (Blueprint $table) {
            $table->dropColumn('kenmerken');
        });

        Schema::table('soa_regels', function (Blueprint $table) {
            $table->dropColumn(['beleidreferentie', 'procesreferentie']);
        });
    }
};
