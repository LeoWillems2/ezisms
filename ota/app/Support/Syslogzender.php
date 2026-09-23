<?php

namespace App\Support;

use RuntimeException;

/**
 * Stuurt één syslogbericht naar een server elders (implementatie/01l §6.2).
 *
 * Een eigen zender en niet Monolog: diens `SyslogFormatter`, nodig voor TCP,
 * zet de facility vast op `user`. Authenticatiegebeurtenissen horen onder
 * `authpriv`, en daar filteren de meeste SIEM-regels op. Voor twee protocollen
 * en één regelformaat is een eigen zender kleiner dan de omweg eromheen.
 *
 * RFC 5424, facility `authpriv` (10). Over TCP afgesloten met een regeleinde
 * (RFC 6587, non-transparent framing): rsyslog, syslog-ng en Graylog nemen dat
 * standaard aan.
 */
final class Syslogzender
{
    private const FACILITY_AUTHPRIV = 10;

    /** RFC 5424 §6.2.1. */
    private const ERNST = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    public function __construct(
        private readonly string $host,
        private readonly int $poort,
        private readonly string $protocol,
        private readonly float $wachttijd,
        private readonly string $afzender,
    ) {
        if (! in_array($protocol, ['udp', 'tcp'], true)) {
            throw new RuntimeException("ISMS_SYSLOG_PROTOCOL moet udp of tcp zijn, niet '{$protocol}'.");
        }
    }

    public static function vanuitConfig(): self
    {
        $syslog = config('beveiligingsbewaking.syslog');

        return new self(
            host: trim((string) $syslog['host']),
            poort: (int) $syslog['poort'],
            protocol: (string) $syslog['protocol'],
            wachttijd: (float) $syslog['wachttijd_seconden'],
            afzender: Signaalkanaal::installatie(),
        );
    }

    public function verstuur(string $ernst, string $bericht, string $berichtId = '-'): void
    {
        $regel = $this->opmaak($ernst, $bericht, $berichtId);

        // IPv6 tussen haken, anders leest PHP de poort als deel van het adres.
        $host = str_contains($this->host, ':') ? '['.$this->host.']' : $this->host;
        $adres = "{$this->protocol}://{$host}:{$this->poort}";

        $socket = @stream_socket_client($adres, $foutcode, $fouttekst, $this->wachttijd);

        if ($socket === false) {
            throw new RuntimeException(trim("{$fouttekst} ({$foutcode})"));
        }

        try {
            stream_set_timeout($socket, (int) ceil($this->wachttijd));
            $geschreven = @fwrite($socket, $this->protocol === 'tcp' ? $regel."\n" : $regel);

            if ($geschreven === false || $geschreven === 0) {
                throw new RuntimeException('het bericht kon niet worden geschreven');
            }
        } finally {
            fclose($socket);
        }
    }

    public function opmaak(string $ernst, string $bericht, string $berichtId = '-'): string
    {
        $prioriteit = self::FACILITY_AUTHPRIV * 8 + (self::ERNST[$ernst] ?? self::ERNST['warning']);

        return sprintf(
            '<%d>1 %s %s ezisms %d %s - %s',
            $prioriteit,
            now()->format('Y-m-d\TH:i:s.vP'),
            $this->veld($this->afzender),
            getmypid(),
            $this->veld($berichtId),
            str_replace(["\r", "\n"], ' ', $bericht),
        );
    }

    /** RFC 5424 §6: een kopveld is printbaar ASCII zonder spatie, of '-'. */
    private function veld(string $waarde): string
    {
        $schoon = preg_replace('/[^\x21-\x7E]/', '', $waarde);

        return $schoon === '' ? '-' : $schoon;
    }
}
