<?php

namespace App\Support;

/**
 * Verlaat post dit systeem wel of niet (implementatie/01i §1)?
 *
 * Een `try/catch` rond `Mail::send()` beantwoordt die vraag niet: met
 * `MAIL_MAILER=log` — de standaardwaarde in `.env.example` en in
 * `docker/ezisms/env.voorbeeld` — slaagt de verzending, en belandt de mail in
 * een logbestand dat de geadresseerde nooit ziet. Op een installatie in een
 * afgeschermd netwerk is dat de eindtoestand en geen storing. Wie post
 * verstuurt, vraagt het daarom vooraf hier.
 */
final class Postkanaal
{
    /**
     * Transporten die de mail wél accepteren maar niets afleveren.
     *
     * `array` staat er bewust níet bij: dat is het transport van de
     * testomgeving (`phpunit.xml`) en van `Mail::fake()`, en `DemoVul` zet het
     * om verzending te slikken. Zou het hier als "geen kanaal" gelden, dan liep
     * de hele suite door de handmatige tak en ging de demo-simulatie
     * uitnodigingsbestanden produceren (01i §0).
     *
     * @var list<string>
     */
    private const STILLE_TRANSPORTEN = ['log', 'null'];

    public static function beschikbaar(): bool
    {
        return self::reden() === null;
    }

    /**
     * Waarom er geen post uit kan, in mensentaal en met de naam van de
     * instelling erin — de modal uit §4 toont deze zin aan de CISO, en een
     * beheerder die het wél wil inrichten weet daarmee waar hij moet zijn.
     */
    public static function reden(): ?string
    {
        $naam = (string) config('mail.default');
        $mailer = config("mail.mailers.{$naam}", []);
        $transport = $mailer['transport'] ?? $naam;

        if (in_array($transport, self::STILLE_TRANSPORTEN, true)) {
            return "Er is geen e-mailkanaal ingesteld: MAIL_MAILER staat op '{$naam}'.";
        }

        // Alleen controleren waar een host betekenis heeft; een mailer zonder
        // die sleutel (bijv. een API-transport) beoordelen we niet op iets wat
        // hij niet kent.
        if (array_key_exists('host', $mailer) && trim((string) $mailer['host']) === '') {
            return 'Er is geen mailserver ingesteld: MAIL_HOST is leeg.';
        }

        return null;
    }
}
