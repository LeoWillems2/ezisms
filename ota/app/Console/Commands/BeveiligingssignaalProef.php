<?php

namespace App\Console\Commands;

use App\Mail\Beveiligingssignaal as SignaalMail;
use App\Models\Beveiligingssignaal;
use App\Support\Postkanaal;
use App\Support\Signaalkanaal;
use App\Support\Syslogzender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stuurt een proefsignaal via het ingestelde kanaal (implementatie/01l §9).
 *
 * Zonder dit commando is het inrichten alleen te controleren door twintig
 * keer verkeerd in te loggen. Maakt geen rij en geen trailregel: een proef is
 * geen waarneming, en hoort het bewijs dat er gemonitord wordt niet te
 * vervuilen.
 */
class BeveiligingssignaalProef extends Command
{
    protected $signature = 'isms:beveiligingssignaal-proef';

    protected $description = 'Stuur een proefsignaal naar syslog of, als die niet is ingesteld, per mail naar de CISO\'s';

    public function handle(): int
    {
        if (! config('beveiligingsbewaking.aan')) {
            $this->components->warn('De bewaking staat uit (ISMS_BEWAKING_AAN=false). Er gaan geen signalen weg; de proef gaat wel.');
        }

        return match (Signaalkanaal::bepaal()) {
            'syslog' => $this->viaSyslog(),
            'mail' => $this->perMail(),
            default => $this->nergens(),
        };
    }

    private function viaSyslog(): int
    {
        $adres = Signaalkanaal::syslogadres();
        $bericht = Signaalkanaal::regel([
            'signaal' => 'proef',
            'installatie' => Signaalkanaal::installatie(),
        ]);

        try {
            Syslogzender::vanuitConfig()->verstuur('notice', $bericht, 'proef');
        } catch (Throwable $fout) {
            $this->components->error("Syslog {$adres} weigerde: {$fout->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info("Proefsignaal naar syslog {$adres} gestuurd.");
        $this->line('  Bericht: '.$bericht);

        if (config('beveiligingsbewaking.syslog.protocol') === 'udp') {
            $this->line('  UDP geeft geen bevestiging: of het aankwam, zie je alleen op de syslogserver.');
        }

        return self::SUCCESS;
    }

    private function perMail(): int
    {
        // Niet opgeslagen: een proef is geen waarneming (zie de klasse).
        $proef = new Beveiligingssignaal(['tijdstip' => now(), 'soort' => 'piek_mislukt', 'details' => [], 'kanaal' => 'mail']);

        $ontvangers = Signaalkanaal::ontvangers();

        try {
            foreach ($ontvangers as $ciso) {
                Mail::to($ciso->email)->send(new SignaalMail($proef, proef: true));
            }
        } catch (Throwable $fout) {
            $this->components->error('De mail kon niet worden verstuurd: '.$fout->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Er is geen syslogserver ingesteld; proefmail verstuurd aan '
            .$ontvangers->count().' '.($ontvangers->count() === 1 ? 'CISO' : "CISO's").': '
            .$ontvangers->pluck('email')->implode(', '));

        return self::SUCCESS;
    }

    private function nergens(): int
    {
        $reden = Signaalkanaal::ontvangers()->isEmpty()
            ? 'er is geen actieve gebruiker met de rol CISO'
            : Postkanaal::reden();

        $this->components->error('Signalen kunnen nergens heen: er is geen syslogserver ingesteld (ISMS_SYSLOG_HOST), '
            .'en mail valt af: '.lcfirst(rtrim((string) $reden, '.')).'.');
        $this->line('  Signalen komen dan alleen in de audit trail en in het applicatielog.');

        return self::FAILURE;
    }
}
