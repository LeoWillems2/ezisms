<?php

namespace App\Support;

use App\Models\Beveiligingssignaal;
use App\Models\Gebruiker;
use App\Models\Loginpoging;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * De use cases voor misbruik van inloggegevens (implementatie/01l §4).
 *
 * Draait op het moment van de poging, via de `created`-hook op `Loginpoging`:
 * één plek die alle schrijvers vangt (wachtwoord, 2FA-challenge, externe
 * callback). Een gepland commando zou een piek om 10:00 pas de volgende ochtend
 * melden (§1, beslissing 1).
 *
 * **Mag nooit gooien** (§6.6). Een fout in de bewaking hoort niemand buiten te
 * sluiten; daarom vangt elke ingang `Throwable` en logt hij.
 */
final class Beveiligingsbewaking
{
    /** Redenen van een mislukte tweede factor (01d §7c; `passkey` komt met 01k). */
    private const TWEEDE_FACTOR = ['totp', 'herstelcode', 'passkey'];

    public static function naPoging(Loginpoging $poging): void
    {
        if (! config('beveiligingsbewaking.aan')) {
            return;
        }

        try {
            $poging->succesvol
                ? self::naSucces($poging)
                : self::naMislukking($poging);
        } catch (Throwable $fout) {
            Log::error('Beveiligingsbewaking: de poging kon niet worden beoordeeld.', [
                'loginpoging' => $poging->id,
                'fout' => $fout->getMessage(),
            ]);
        }
    }

    /**
     * B: het systeem blokkeerde het account. Een handmatige blokkade door de
     * CISO is geen signaal: die staat al in de trail, met de CISO als actor
     * (01f).
     */
    public static function naBlokkade(Gebruiker $gebruiker, ?string $ipAdres): void
    {
        if (! config('beveiligingsbewaking.aan')) {
            return;
        }

        try {
            self::signaleer(
                'account_geblokkeerd',
                "blokkade:{$gebruiker->id}",
                now()->subMinutes(config('beveiligingsbewaking.blokkade.onderdrukken_minuten')),
                gebruiker: $gebruiker,
                ipAdres: $ipAdres,
                details: ['account' => $gebruiker->email],
            );
        } catch (Throwable $fout) {
            Log::error('Beveiligingsbewaking: de blokkade kon niet worden gesignaleerd.', [
                'gebruiker' => $gebruiker->id,
                'fout' => $fout->getMessage(),
            ]);
        }
    }

    private static function naMislukking(Loginpoging $poging): void
    {
        // Een geblokkeerd account dat het opnieuw probeert, valt geen
        // wachtwoord aan.
        if ($poging->reden === 'status') {
            return;
        }

        self::piek($poging);

        if (in_array($poging->reden, self::TWEEDE_FACTOR, true) && $poging->gebruiker_id !== null) {
            self::tweedeFactor($poging);
        }
    }

    private static function naSucces(Loginpoging $poging): void
    {
        if ($poging->gebruiker_id === null) {
            return;
        }

        self::nieuwNetwerk($poging);
        self::succesNaMislukkingen($poging);
    }

    /** A: password spraying ziet de blokkade per account niet. */
    private static function piek(Loginpoging $poging): void
    {
        $instelling = config('beveiligingsbewaking.piek');
        $vanaf = now()->subMinutes($instelling['venster_minuten']);

        $mislukt = fn () => self::mislukt(Loginpoging::query())->where('tijdstip', '>=', $vanaf);

        $aantal = $mislukt()->count();

        if ($aantal < $instelling['aantal']) {
            return;
        }

        $drukst = $mislukt()
            ->whereNotNull('ip_adres')
            ->selectRaw('ip_adres, COUNT(*) as n')
            ->groupBy('ip_adres')
            ->orderByDesc('n')
            ->limit(3)
            ->pluck('n', 'ip_adres')
            ->all();

        self::signaleer(
            'piek_mislukt',
            'piek',
            now()->subMinutes($instelling['onderdrukken_minuten']),
            details: [
                'aantal' => $aantal,
                'venster_min' => $instelling['venster_minuten'],
                'accounts' => $mislukt()->distinct()->count('email_ingevoerd'),
                'drukste_ip' => $drukst,
            ],
        );
    }

    /** C: het wachtwoord was goed, dus waarschijnlijk gelekt. */
    private static function tweedeFactor(Loginpoging $poging): void
    {
        $instelling = config('beveiligingsbewaking.tweede_factor');

        $aantal = Loginpoging::query()
            ->where('gebruiker_id', $poging->gebruiker_id)
            ->where('succesvol', false)
            ->whereIn('reden', self::TWEEDE_FACTOR)
            ->where('tijdstip', '>=', now()->subMinutes($instelling['venster_minuten']))
            ->count();

        if ($aantal < $instelling['aantal']) {
            return;
        }

        self::signaleer(
            'tweede_factor_mislukt',
            "tweede_factor:{$poging->gebruiker_id}",
            now()->subMinutes($instelling['onderdrukken_minuten']),
            gebruiker: $poging->gebruiker,
            ipAdres: $poging->ip_adres,
            details: [
                'account' => $poging->email_ingevoerd,
                'aantal' => $aantal,
                'venster_min' => $instelling['venster_minuten'],
            ],
        );
    }

    /**
     * D: een ongebruikelijke plek, gelezen als een netwerk dat nieuw is voor
     * dit account (§1, beslissing 4).
     *
     * Geen signaal zonder basislijn: niet bij de eerste login ooit, en niet als
     * alle historie van de proxy komt (§2.3). Anders krijgt na de uitrol iedere
     * gebruiker bij zijn eerste login een melding.
     */
    private static function nieuwNetwerk(Loginpoging $poging): void
    {
        $instelling = config('beveiligingsbewaking.nieuw_netwerk');
        $netwerk = self::netwerk($poging->ip_adres);

        if ($netwerk === null) {
            return;
        }

        $bekend = Loginpoging::query()
            ->where('gebruiker_id', $poging->gebruiker_id)
            ->where('succesvol', true)
            ->whereKeyNot($poging->getKey())
            ->where('tijdstip', '>=', now()->subDays($instelling['terugkijken_dagen']))
            ->whereNotNull('ip_adres')
            ->pluck('ip_adres')
            ->map(fn (string $ip) => self::netwerk($ip))
            ->filter()
            ->unique();

        if ($bekend->isEmpty() || $bekend->contains($netwerk)) {
            return;
        }

        self::signaleer(
            'nieuw_netwerk',
            "netwerk:{$poging->gebruiker_id}:{$netwerk}",
            now()->subHours($instelling['onderdrukken_uren']),
            gebruiker: $poging->gebruiker,
            ipAdres: $poging->ip_adres,
            details: [
                'account' => $poging->email_ingevoerd,
                'netwerk' => $netwerk,
                'methode' => $poging->methode,
                'bekende_netwerken' => $bekend->count(),
            ],
        );
    }

    /** E: de klassieke "brute force gelukt". */
    private static function succesNaMislukkingen(Loginpoging $poging): void
    {
        $instelling = config('beveiligingsbewaking.succes_na');

        $aantal = self::mislukt(Loginpoging::query())
            ->where('gebruiker_id', $poging->gebruiker_id)
            ->where('tijdstip', '>=', $poging->tijdstip->copy()->subMinutes($instelling['venster_minuten']))
            ->count();

        if ($aantal < $instelling['aantal']) {
            return;
        }

        self::signaleer(
            'succes_na_mislukkingen',
            "succes_na:{$poging->gebruiker_id}",
            now()->subMinutes($instelling['onderdrukken_minuten']),
            gebruiker: $poging->gebruiker,
            ipAdres: $poging->ip_adres,
            details: [
                'account' => $poging->email_ingevoerd,
                'mislukt_ervoor' => $aantal,
                'venster_min' => $instelling['venster_minuten'],
            ],
        );
    }

    /**
     * Mislukt, maar niet omdat het account niet actief was. `reden` is leeg
     * bij rijen van vóór 01d, en `<>` laat NULL in SQL vallen.
     *
     * @param  Builder<Loginpoging>  $query
     * @return Builder<Loginpoging>
     */
    private static function mislukt(Builder $query): Builder
    {
        return $query
            ->where('succesvol', false)
            ->where(fn (Builder $q) => $q->whereNull('reden')->orWhere('reden', '<>', 'status'));
    }

    /**
     * Het netwerk van een adres, als `203.0.113.0/24`. `null` voor een adres
     * dat niets over de bezoeker zegt: loopback of een vertrouwde proxy (§2.3).
     */
    public static function netwerk(?string $ip): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $proxies = (array) (config('trustedproxy.proxies') ?? []);

        if (IpUtils::checkIp($ip, ['127.0.0.0/8', '::1', ...$proxies])) {
            return null;
        }

        $v6 = str_contains($ip, ':');
        $lengte = (int) config($v6 ? 'beveiligingsbewaking.nieuw_netwerk.prefix_v6' : 'beveiligingsbewaking.nieuw_netwerk.prefix_v4');

        $bytes = inet_pton($ip);
        $gemaskeerd = '';

        foreach (str_split($bytes) as $i => $byte) {
            $bits = max(0, min(8, $lengte - $i * 8));
            $gemaskeerd .= chr(ord($byte) & (0xFF << (8 - $bits)) & 0xFF);
        }

        return inet_ntop($gemaskeerd).'/'.$lengte;
    }

    /**
     * Maakt het signaal aan, tenzij hetzelfde signaal er binnen het venster al
     * is (§5). De rij en de trailregel synchroon, want het bewijs moet er zijn
     * ook als de aflevering daarna misgaat; de aflevering na het antwoord.
     *
     * @param  array<string, mixed>  $details
     */
    private static function signaleer(
        string $soort,
        string $sleutel,
        \DateTimeInterface $onderdrukkenVanaf,
        ?Gebruiker $gebruiker = null,
        ?string $ipAdres = null,
        array $details = [],
    ): void {
        $alGemeld = Beveiligingssignaal::query()
            ->where('sleutel', $sleutel)
            ->where('tijdstip', '>=', $onderdrukkenVanaf)
            ->exists();

        if ($alGemeld) {
            return;
        }

        $signaal = Beveiligingssignaal::create([
            'tijdstip' => now(),
            'soort' => $soort,
            'gebruiker_id' => $gebruiker?->id,
            'ip_adres' => $ipAdres,
            'sleutel' => $sleutel,
            'details' => $details,
            'kanaal' => Signaalkanaal::bepaal(),
        ]);

        Signaalkanaal::naHetAntwoord($signaal);
    }
}
