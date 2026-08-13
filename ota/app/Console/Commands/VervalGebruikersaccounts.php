<?php

namespace App\Console\Commands;

use App\Models\Gebruiker;
use Illuminate\Console\Command;

/**
 * Implementeert de automatische transitie Actief -> Gedeactiveerd zodra
 * vervalt_op bereikt is (deelproducten/01-identity-access.md §3). Dit is de
 * enige statusovergang die geen CISO-handeling vereist: juist bedoeld om te
 * voorkomen dat een tijdelijk auditor-account onbedoeld actief blijft.
 */
class VervalGebruikersaccounts extends Command
{
    protected $signature = 'isms:verval-gebruikersaccounts';

    protected $description = 'Deactiveert actieve accounts waarvan de vervaldatum is bereikt';

    public function handle(): int
    {
        $aantal = Gebruiker::where('status', 'actief')
            ->whereNotNull('vervalt_op')
            ->whereDate('vervalt_op', '<=', now())
            // updateGeaudit i.p.v. update: een massa-update omzeilt de
            // Eloquent-events en dus de audit trail (zie AppServiceProvider).
            ->updateGeaudit(['status' => 'gedeactiveerd']);

        $this->info("{$aantal} account(s) gedeactiveerd wegens bereikte vervaldatum.");

        return self::SUCCESS;
    }
}
