<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `geexporteerd` als vijfde waarde in `audit_logregels.actie`
 * (implementatie/01e §3).
 *
 * De vier bestaande waarden beschrijven wat er met een récord gebeurt:
 * aangemaakt, gewijzigd, status gewijzigd, verwijderd. Een export raakt geen
 * record — de inhoud verlaat het systeem — en past dus op geen van de vier.
 * `aangemaakt` erop plakken zou hem tussen de recordmutaties zetten en het
 * filter van de auditor vervuilen.
 *
 * **Alleen op MySQL.** De testsuite draait op sqlite (`phpunit.xml`), en daar is
 * een enum gewoon een tekstkolom zonder controle. Dat is precies waarom deze
 * migratie er pas is nadat de fout in productie viel: de suite kon hem niet
 * zien. `AuditLogregel::ACTIES` sluit dat gat aan de codekant — die lijst wordt
 * bij elke schrijfactie getoetst, op elke driver.
 */
return new class extends Migration
{
    private const ACTIES = ['aangemaakt', 'gewijzigd', 'status_gewijzigd', 'verwijderd', 'geexporteerd'];

    public function up(): void
    {
        $this->zetEnum(self::ACTIES);
    }

    /**
     * Terug naar vier waarden. Bestaande exportregels blijven staan: ze zijn
     * append-only en mogen niet verdwijnen omdat een schema terugrolt. MySQL
     * maakt er dan een lege waarde van met een waarschuwing — daarom eerst
     * kijken of ze er zijn, en zo ja de terugrol weigeren.
     */
    public function down(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        if (DB::table('audit_logregels')->where('actie', 'geexporteerd')->exists()) {
            throw new RuntimeException(
                'Er staan exportregels in de audit trail; die zouden door deze terugrol '
                .'leeggemaakt worden. De audit trail is append-only — rol dit niet terug.'
            );
        }

        $this->zetEnum(array_slice(self::ACTIES, 0, 4));
    }

    /** @param  list<string>  $waarden */
    private function zetEnum(array $waarden): void
    {
        if (! $this->isMysql()) {
            return;
        }

        $lijst = implode(', ', array_map(fn (string $w) => "'{$w}'", $waarden));

        DB::statement("ALTER TABLE audit_logregels MODIFY actie ENUM({$lijst}) NOT NULL");
    }

    private function isMysql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }
};
