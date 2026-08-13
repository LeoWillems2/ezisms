<?php

namespace App\Console\Commands;

use App\Support\Audittrailketen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verwijdert alle auditmanagement-data (blok 11 + 11b) voor een schone start:
 * auditprogramma's, jaarplannen, rondes, bevindingen, dekkingen en de bijbehorende
 * pivots/bewijs-koppelingen. Bewust géén onderdeel van een schema — dit is een
 * expliciet, destructief hulpmiddel dat je met de hand draait.
 *
 * Standaard blijven de audit-*trail* (audit_logregels) en de referentie-universe
 * (auditobjecten) staan; die wis je alleen met --met-trail respectievelijk
 * --met-universe. Afwijkingen (§10.2) die uit auditbevindingen ontstonden blijven
 * altijd staan — ze verliezen alleen hun koppeling naar de verwijderde bevinding.
 *
 * Waarborg: zonder --bevestig toont het command alleen de telling (dry-run) en
 * vraagt interactief om bevestiging (default 'nee'), zodat een niet-interactieve
 * run zonder --bevestig nooit iets verwijdert.
 */
class VerwijderAuditdata extends Command
{
    protected $signature = 'isms:verwijder-auditdata
        {--bevestig : Verwijder direct, zonder interactieve bevestiging}
        {--met-trail : Verwijder ook de audit-trail-regels van blok auditmanagement}
        {--met-universe : Verwijder ook de auditobjecten (clausules + maatregel-objecten)}';

    protected $description = 'Verwijdert alle auditmanagement-data (blok 11 + 11b) voor een schone start';

    private const BLOK = 'auditmanagement';

    private const ENTITEIT_RONDE = 'auditronde';

    public function handle(): int
    {
        $metTrail = (bool) $this->option('met-trail');
        $metUniverse = (bool) $this->option('met-universe');

        // Telling per tabel, in verwijdervolgorde (kind → ouder).
        $tellingen = [
            'bewijs-koppelingen (rondes)' => DB::table('bewijs_koppelingen')->where('entiteit_type', self::ENTITEIT_RONDE)->count(),
            'ronde ↔ auditobject' => DB::table('auditronde_auditobject')->count(),
            'ronde ↔ organisatie-eenheid' => DB::table('auditronde_organisatie_eenheid')->count(),
            'bevindingen' => DB::table('bevindingen')->count(),
            'auditrondes' => DB::table('auditrondes')->count(),
            'auditplannen' => DB::table('auditplannen')->count(),
            'programma-dekkingen' => DB::table('auditprogramma_dekkingen')->count(),
            'auditprogrammas' => DB::table('auditprogrammas')->count(),
        ];

        if ($metUniverse) {
            $tellingen['auditobjecten (universe)'] = DB::table('auditobjecten')->count();
        }
        if ($metTrail) {
            $tellingen['audit-trail (auditmanagement)'] = DB::table('audit_logregels')->where('blok_naam', self::BLOK)->count();
        }

        if (array_sum($tellingen) === 0) {
            $this->info('Geen auditdata gevonden; niets te doen.');

            return self::SUCCESS;
        }

        $this->table(['Tabel', 'Rijen'], collect($tellingen)->map(fn ($n, $t) => [$t, $n])->values()->all());

        // Afwijkingen die uit auditbevindingen ontstonden blijven staan, maar
        // verliezen hun bron-koppeling (nullOnDelete). Benoem dat expliciet.
        $afwijkingenUitAudit = DB::table('afwijkingen')->whereNotNull('bevinding_id')->count();
        if ($afwijkingenUitAudit > 0) {
            $this->warn("{$afwijkingenUitAudit} afwijking(en) (§10.2) zijn uit auditbevindingen ontstaan; die blijven staan maar verliezen hun koppeling naar de verwijderde bevinding.");
        }

        // Regels uit de trail halen breekt de keten-hashes (implementatie/06c
        // §7). Dat is herstelbaar — de keten wordt opnieuw verzegeld — maar het
        // betekent wel dat de bewijskracht van de hele trail vanaf dat moment
        // opnieuw begint. Dat hoort vóór de bevestiging te staan, niet erna.
        if ($metTrail) {
            $this->warn('De keten-hashes over de audit trail worden hierna opnieuw verzegeld. '
                .'De trail bewijst daarmee alleen nog wat er ná deze handeling gebeurt.');
        }

        $verwijder = $this->option('bevestig')
            || $this->confirm('Alle bovenstaande auditdata onherroepelijk verwijderen?', false);

        if (! $verwijder) {
            $this->info('Niets verwijderd (dry-run). Gebruik --bevestig of bevestig interactief om door te gaan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($metTrail, $metUniverse) {
            // FK-veilige volgorde: pivots/koppelingen en bevindingen eerst, dan de
            // rondes/plannen, dan de dekkingen en programma's.
            DB::table('bewijs_koppelingen')->where('entiteit_type', self::ENTITEIT_RONDE)->delete();
            DB::table('auditronde_auditobject')->delete();
            DB::table('auditronde_organisatie_eenheid')->delete();
            DB::table('bevindingen')->delete();
            DB::table('auditrondes')->delete();
            DB::table('auditplannen')->delete();
            DB::table('auditprogramma_dekkingen')->delete();
            DB::table('auditprogrammas')->delete();

            if ($metUniverse) {
                DB::table('auditobjecten')->delete();
            }
            if ($metTrail) {
                DB::table('audit_logregels')->where('blok_naam', self::BLOK)->delete();
            }
        });

        if ($metTrail) {
            $verzegeling = Audittrailketen::verzegel(
                'isms:verwijder-auditdata --met-trail: trailregels van blok auditmanagement verwijderd.'
            );
        }

        $this->info('Auditdata verwijderd.');
        $this->line('- Trail (audit_logregels): '.($metTrail ? 'ook gewist (blok auditmanagement)' : 'behouden'));

        if (isset($verzegeling)) {
            $this->line('- Keten: opnieuw verzegeld over '.$verzegeling->regels.' resterende regel(s).');
        }
        $this->line('- Universe (auditobjecten): '.($metUniverse ? 'ook gewist — opnieuw seeden/synchroniseren nodig' : 'behouden'));

        return self::SUCCESS;
    }
}
