<?php

namespace App\Console\Commands;

use App\Support\Oidc\Configuratie;
use App\Support\Oidc\Provider;
use App\Support\Oidc\Storing;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Controleert de koppeling met de externe identiteitsprovider zonder dat er een
 * testaccount nodig is (implementatie/01j §1.4).
 *
 * Het client secret zelf wordt niet gecontroleerd: dat kan alleen met een echte
 * login. Wel drukt het commando de redirect-URI af die in de app-registratie
 * moet, zodat een verschil achter de TLS-terminatie zichtbaar is vóór de eerste
 * gebruiker ertegenaan loopt.
 */
class ControleerExternInloggen extends Command
{
    protected $signature = 'isms:extern-inloggen-controleren';

    protected $description = 'Controleert de configuratie en bereikbaarheid van de externe identiteitsprovider';

    /** Hoe ruim van tevoren een verlopend secret gemeld wordt. */
    private const SECRET_WAARSCHUWING_DAGEN = 30;

    public function handle(): int
    {
        if (! Configuratie::isIngesteld()) {
            $this->info('Inloggen via een externe identiteitsprovider is niet ingesteld (OIDC_ISSUER, OIDC_CLIENT_ID en OIDC_CLIENT_SECRET zijn niet alle drie gevuld).');

            return self::SUCCESS;
        }

        $this->line('Issuer:       '.Configuratie::issuer());
        $this->line('Client-id:    '.config('extern_inloggen.client_id'));
        $this->line('Knoptekst:    Inloggen met '.Configuratie::weergavenaam());
        $this->line('Subject:      claim '.config('extern_inloggen.subject_claim'));
        $this->line('Domein (hd):  '.(config('extern_inloggen.domein') ?: '—'));
        $this->line('Redirect-URI: '.Provider::redirectUri());
        $this->line('Tweede factor via de IdP: '.(config('tweefactor.idp_dwingt_af') ? 'ja (ISMS_IDP_DWINGT_MFA)' : 'nee'));
        $this->newLine();

        $geslaagd = true;

        foreach (Configuratie::fouten() as $fout) {
            $this->error($fout);
            $geslaagd = false;
        }

        if (! $geslaagd) {
            return self::FAILURE;
        }

        try {
            // Vers ophalen: een controle die de cache leest, controleert niets.
            cache()->forget('oidc.discovery.'.md5(Configuratie::issuer()));
            $discovery = Provider::discovery();
            $this->info('Discovery-document opgehaald; de issuer klopt.');

            $sleutels = Provider::sleutelset(vers: true);
            $this->info('Sleutelset opgehaald: '.count($sleutels['keys']).' sleutel(s).');

            $algoritmen = $discovery['id_token_signing_alg_values_supported'] ?? ['RS256'];
            $this->line('Ondertekening: '.implode(', ', (array) $algoritmen));
        } catch (Storing $e) {
            $this->error($e->getMessage());
            $this->line('Kan deze server de identiteitsprovider bereiken? Er is uitgaand HTTPS nodig naar de issuer.');

            return self::FAILURE;
        }

        $this->waarschuwVoorVerlopendSecret();

        $this->newLine();
        $this->info('De koppeling is bruikbaar. Het client secret is alleen met een echte login te controleren.');

        return self::SUCCESS;
    }

    private function waarschuwVoorVerlopendSecret(): void
    {
        $datum = config('extern_inloggen.secret_verloopt_op');

        if (blank($datum)) {
            return;
        }

        try {
            $verloopt = Carbon::parse($datum)->startOfDay();
        } catch (Throwable) {
            $this->warn("OIDC_CLIENT_SECRET_VERLOOPT_OP is geen datum: {$datum}");

            return;
        }

        $dagen = (int) now()->startOfDay()->diffInDays($verloopt, false);

        if ($dagen < 0) {
            $this->error('Het client secret is verlopen op '.$verloopt->format('d-m-Y').'. Geen enkel extern account komt nog binnen.');
        } elseif ($dagen <= self::SECRET_WAARSCHUWING_DAGEN) {
            $this->warn("Het client secret verloopt over {$dagen} dag(en), op ".$verloopt->format('d-m-Y').'.');
        } else {
            $this->line('Client secret geldig tot '.$verloopt->format('d-m-Y').'.');
        }
    }
}
