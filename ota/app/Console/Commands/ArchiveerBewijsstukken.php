<?php

namespace App\Console\Commands;

use App\Models\Bewijsstuk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Zet bewijsstukken op 'gearchiveerd' zodra hun bewaartermijn verstreken is
 * (implementatie/06 §7).
 *
 * Verwijdert bewust NIETS: deelproducten/06 §7 laat de AVG-afweging voor
 * bewijsstukken met persoonsgegevens expliciet open, en dat is geen beslissing
 * om in een cronjob te verstoppen.
 */
class ArchiveerBewijsstukken extends Command
{
    protected $signature = 'isms:archiveer-bewijsstukken';

    protected $description = 'Archiveert bewijsstukken waarvan de bewaartermijn is verstreken';

    public function handle(): int
    {
        $aantal = Bewijsstuk::where('status', 'actief')
            ->whereDate('bewaren_tot', '<=', now())
            // Blok 5: het bestand van een actief beleid blijft actief. De
            // download zou blijven werken (status wordt nergens gecheckt), maar
            // "gearchiveerd" tonen op precies het scherm waar mensen moeten
            // zien dat dit de geldende versie is, is misleidend.
            ->whereNotExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('beleidsversies')
                ->whereColumn('beleidsversies.bewijsstuk_id', 'bewijsstukken.id')
                ->where('beleidsversies.status', 'actief'))
            ->updateGeaudit(['status' => 'gearchiveerd']);

        $this->info("{$aantal} bewijsstuk(ken) gearchiveerd wegens verstreken bewaartermijn.");

        return self::SUCCESS;
    }
}
