<?php

use App\Models\Auditobject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Blok 11d (Behandeling per auditobject) — implementatie/11d-behandeling-per-auditobject.md §2.
return new class extends Migration
{
    public function up(): void
    {
        // De feitelijke afhandeling per object in de normatieve scope. Er is
        // bewust géén waarde 'bevinding': dat is een afgeleide van de
        // bevindingen van de ronde, en een tweede vastlegging ervan zou de
        // bevindingenlijst kunnen tegenspreken (11d §0).
        Schema::table('auditronde_auditobject', function (Blueprint $table) {
            $table->enum('afhandeling', ['niet_behandeld', 'geen_opmerkingen', 'niet_toegekomen'])
                ->default('niet_behandeld');
            // Verplicht bij 'niet_toegekomen': waaróm er een gat zit.
            $table->string('toelichting')->nullable();
            // Verplicht bij 'geen_opmerkingen': zonder bron is groen een
            // bewering. nullOnDelete: een vertrokken medewerker mag het
            // auditbewijs niet meeslepen (lijn van auditor_gebruiker_id).
            $table->foreignId('gesproken_met_id')->nullable()->constrained('gebruikers')->nullOnDelete();
            // Tijdens de uitvoering aan de scope toegevoegd doordat er een
            // bevinding op kwam — herkenbaar, want de auditor rekt daarmee zijn
            // eigen ronde op (spiegel van 11c §9).
            $table->boolean('buiten_planning')->default(false);
        });

        Schema::table('bevindingen', function (Blueprint $table) {
            // Nullable in de kolom, verplicht in het formulier: bestaande
            // bevindingen zonder maatregel mogen niet aan een verzonnen object
            // worden gehangen (11d §2).
            $table->foreignId('auditobject_id')->nullable()->after('omschrijving')
                ->constrained('auditobjecten')->nullOnDelete();
            // Alleen invullen als het gesprek afwijkt van wat op het object staat.
            $table->foreignId('gesproken_met_id')->nullable()->after('auditobject_id')
                ->constrained('gebruikers')->nullOnDelete();
        });

        $this->verhuisMaatregelNaarAuditobject();

        Schema::table('bevindingen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maatregel_id');
        });
    }

    /**
     * Elke bevinding die naar een maatregel wees, gaat naar het bijbehorende
     * auditobject. Ook een *inactief* object telt: een maatregel die intussen
     * buiten de SoA-scope is gezet, hoort nog steeds bij de bevinding van toen.
     * Bestaat het object niet, dan maken we het inactief aan — dan duikt het niet
     * op in de keuzelijsten, maar houdt de bevinding wel zijn verwijzing.
     */
    private function verhuisMaatregelNaarAuditobject(): void
    {
        $maatregelIds = DB::table('bevindingen')->whereNotNull('maatregel_id')
            ->distinct()->pluck('maatregel_id');

        foreach ($maatregelIds as $maatregelId) {
            $objectId = DB::table('auditobjecten')
                ->where('soort', 'maatregel')->where('maatregel_id', $maatregelId)
                ->value('id');

            if ($objectId === null) {
                $maatregel = DB::table('maatregelen')->find($maatregelId);

                $objectId = DB::table('auditobjecten')->insertGetId([
                    'soort' => 'maatregel',
                    'maatregel_id' => $maatregelId,
                    'groep' => Auditobject::groepVoorThema($maatregel?->thema),
                    'volgorde' => Auditobject::volgordeVoorReferentie($maatregel?->annex_a_referentie),
                    'actief' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('bevindingen')->where('maatregel_id', $maatregelId)
                ->update(['auditobject_id' => $objectId]);
        }
    }

    public function down(): void
    {
        Schema::table('bevindingen', function (Blueprint $table) {
            $table->foreignId('maatregel_id')->nullable()->after('omschrijving')
                ->constrained('maatregelen')->nullOnDelete();
        });

        DB::table('bevindingen')
            ->join('auditobjecten', 'auditobjecten.id', '=', 'bevindingen.auditobject_id')
            ->update(['bevindingen.maatregel_id' => DB::raw('auditobjecten.maatregel_id')]);

        Schema::table('bevindingen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gesproken_met_id');
            $table->dropConstrainedForeignId('auditobject_id');
        });

        Schema::table('auditronde_auditobject', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gesproken_met_id');
            $table->dropColumn(['afhandeling', 'toelichting', 'buiten_planning']);
        });
    }
};
