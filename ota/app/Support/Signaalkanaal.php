<?php

namespace App\Support;

use App\Mail\Beveiligingssignaal as SignaalMail;
use App\Models\Beveiligingssignaal;
use App\Models\Gebruiker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Waar een beveiligingssignaal heen gaat, en het versturen zelf
 * (implementatie/01l §6).
 *
 * Syslog óf mail, nooit allebei: met een SIEM erachter hoort dezelfde melding
 * niet ook in de mailbox van de CISO te landen (§16, besluit 2). De enige
 * uitzondering is een TCP-verbinding die geweigerd wordt; dan is de mail geen
 * tweede aflevering maar de enige die lukt (§6.3).
 */
final class Signaalkanaal
{
    /** Vastgesteld bij het aanmaken, dus deel van de regel "aangemaakt". */
    public static function bepaal(): string
    {
        if (self::syslogIngesteld()) {
            return 'syslog';
        }

        if (Postkanaal::beschikbaar() && self::ontvangers()->isNotEmpty()) {
            return 'mail';
        }

        return 'geen';
    }

    public static function syslogIngesteld(): bool
    {
        return trim((string) config('beveiligingsbewaking.syslog.host')) !== '';
    }

    /**
     * Na het antwoord aan de browser, maar in hetzelfde proces.
     *
     * Géén `ShouldQueue`-job: er draait geen queueworker (`QUEUE_CONNECTION`
     * is `database`, en `queue:work` staat nergens in supervisord of de
     * crontab). Een job in de wachtrij zou dus nooit verstuurd worden, en dat
     * zou niemand opvallen (§6.5). Op de console — tests, het proefcommando —
     * is er geen antwoord om op te wachten, en gaat het meteen.
     */
    public static function naHetAntwoord(Beveiligingssignaal $signaal): void
    {
        if (app()->runningInConsole()) {
            self::verstuur($signaal);

            return;
        }

        app()->terminating(fn () => self::verstuur($signaal));
    }

    /** Mag nooit gooien (§6.6). */
    public static function verstuur(Beveiligingssignaal $signaal): void
    {
        try {
            match ($signaal->kanaal) {
                'syslog' => self::viaSyslog($signaal),
                'mail' => self::perMail($signaal),
                default => Log::warning(
                    'Beveiligingssignaal kon nergens heen: stel ISMS_SYSLOG_HOST of een mailkanaal in.',
                    ['signaal' => $signaal->id, 'soort' => $signaal->soort],
                ),
            };
        } catch (Throwable $fout) {
            self::legFoutVast($signaal, $fout->getMessage());
        }
    }

    /**
     * Actieve CISO's. Een gedeactiveerde of geblokkeerde CISO hoort geen
     * beveiligingsmeldingen meer te krijgen — en bij een overgenomen,
     * geblokkeerd account al helemaal niet.
     *
     * @return Collection<int, Gebruiker>
     */
    public static function ontvangers(): Collection
    {
        return Gebruiker::query()
            ->where('status', 'actief')
            ->whereHas('rollen', fn ($q) => $q->where('naam', 'CISO'))
            ->get();
    }

    private static function viaSyslog(Beveiligingssignaal $signaal): void
    {
        try {
            Syslogzender::vanuitConfig()->verstuur($signaal->ernst(), self::syslogbericht($signaal), $signaal->soort);
        } catch (Throwable $fout) {
            // Bij UDP komen we hier vrijwel nooit: het pakket gaat weg, en of
            // het aankomt vertelt niemand. Bij TCP wel, en dan is mail de
            // terugval (§6.3).
            $reden = 'syslog '.self::syslogadres().' weigerde: '.$fout->getMessage();

            if (Postkanaal::beschikbaar() && self::ontvangers()->isNotEmpty()) {
                self::perMail($signaal);
                self::legFoutVast($signaal, $reden.'; per mail verstuurd');

                return;
            }

            self::legFoutVast($signaal, $reden.'; geen mailkanaal als terugval');
        }
    }

    private static function perMail(Beveiligingssignaal $signaal): void
    {
        foreach (self::ontvangers() as $ciso) {
            Mail::to($ciso->email)->send(new SignaalMail($signaal));
        }
    }

    /**
     * Eén regel `sleutel=waarde`: zonder parser te lezen, met een parser te
     * splitsen (§6.2). `installatie` omdat één syslogserver vaak van meer
     * installaties ontvangt; `id` om terug te kunnen naar de audit trail.
     */
    public static function syslogbericht(Beveiligingssignaal $signaal): string
    {
        $velden = [
            'signaal' => $signaal->soort,
            'installatie' => self::installatie(),
            'account' => $signaal->gebruiker?->email ?? ($signaal->details['account'] ?? null),
            'ip' => $signaal->ip_adres,
        ];

        foreach ($signaal->details as $sleutel => $waarde) {
            if ($sleutel === 'account') {
                continue;
            }

            $velden[$sleutel] = is_array($waarde)
                ? implode(',', array_map(
                    fn ($k, $v) => is_int($k) ? $v : "{$k}:{$v}",
                    array_keys($waarde),
                    $waarde,
                ))
                : $waarde;
        }

        $velden['id'] = $signaal->id;

        return self::regel($velden);
    }

    /** @param  array<string, mixed>  $velden */
    public static function regel(array $velden): string
    {
        $delen = ['ezisms'];

        foreach ($velden as $sleutel => $waarde) {
            if ($waarde === null || $waarde === '') {
                continue;
            }

            $tekst = is_bool($waarde) ? ($waarde ? 'ja' : 'nee') : (string) $waarde;
            // Geen spaties of regeleinden in een waarde: die breken het
            // splitsen, en een regeleinde maakt van één syslogbericht er twee.
            $delen[] = $sleutel.'='.preg_replace('/[\s"]+/u', '_', $tekst);
        }

        return implode(' ', $delen);
    }

    public static function installatie(): string
    {
        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'onbekend');
    }

    public static function syslogadres(): string
    {
        $syslog = config('beveiligingsbewaking.syslog');

        return $syslog['protocol'].'://'.$syslog['host'].':'.$syslog['poort'];
    }

    private static function legFoutVast(Beveiligingssignaal $signaal, string $reden): void
    {
        Log::error('Beveiligingssignaal: '.$reden, ['signaal' => $signaal->id, 'soort' => $signaal->soort]);

        try {
            $signaal->forceFill(['afleverfout' => mb_substr($reden, 0, 2000)])->save();
        } catch (Throwable) {
            // Het log hierboven is dan de enige plek; meer is er niet te doen.
        }
    }
}
