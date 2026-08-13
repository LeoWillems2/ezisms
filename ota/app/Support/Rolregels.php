<?php

namespace App\Support;

use App\Models\Gebruiker;
use App\Models\Rol;

/**
 * De ene plek waar staat welke rollen niet samengaan (implementatie/01e §2.4).
 *
 * Rollen zijn in dit systeem cumulatief, en dat is een vastgelegde keuze:
 * *"functiescheiding is een organisatorische keuze die het systeem faciliteert,
 * niet afdwingt"* (implementatie/01c §0). Bij een organisatie van zestien FTE is
 * één persoon met twee petten soms de realiteit, en het systeem hoort dat
 * zichtbaar te maken in plaats van te verbieden.
 *
 * De Administrator is de uitzondering, en de reden is dat de combinatie de
 * scheiding opheft die de rol bestaansrecht geeft. Hij mag toetsbestanden
 * plaatsen — door mensen geleverde HTML met JavaScript. Zou hij daarnaast een
 * ISMS-rol hebben, dan kan één account een bestand plaatsen én het als
 * ISMS-gebruiker openen. Met de scheiding vraagt dat altijd twee personen: de
 * Administrator kan geen rechten uitdelen (geen `identity-access`) en de CISO
 * kan zichzelf geen technische rechten geven.
 *
 * Wat hiermee NIET is afgedekt: de CISO kan een account aanmaken op een adres
 * van zichzelf en dát tot Administrator maken. In een systeem met één
 * organisatie is dat niet dicht te timmeren; het antwoord daarop is
 * zichtbaarheid — de audit trail legt elke toekenning vast en /gebruikers toont
 * alle rollen per persoon — en niet een extra slot dat het toch niet is.
 */
final class Rolregels
{
    /**
     * De rol die met geen enkele andere samengaat.
     *
     * Op naam en niet op id: de rollen zijn referentiedata die via een seeder
     * ontstaan, en een id is per installatie anders.
     */
    public const EXCLUSIEF = 'Administrator';

    /** Mag deze gebruiker deze rol erbij krijgen? */
    public static function onverenigbaar(Gebruiker $gebruiker, Rol $rol): bool
    {
        // Een verse query en niet de eventueel al geladen relatie: deze controle
        // draait vlak vóór het wegschrijven, en dan telt wat er nú in de
        // database staat — niet wat er stond toen het scherm werd opgebouwd.
        $bestaand = $gebruiker->exists
            ? $gebruiker->rollen()->pluck('naam')
            : collect();

        // Dezelfde rol nog een keer is geen strijdigheid maar een dubbeling; die
        // hoort hier niet geweigerd te worden.
        $andere = $bestaand->reject(fn (string $naam) => $naam === $rol->naam);

        if ($andere->isEmpty()) {
            return false;
        }

        // Beide richtingen op: een Administrator krijgt er geen ISMS-rol bij, en
        // een ISMS-gebruiker wordt geen Administrator.
        return $rol->naam === self::EXCLUSIEF
            || $andere->contains(self::EXCLUSIEF);
    }

    /** De melding, in de vorm waarin een mens hem te zien krijgt. */
    public static function melding(): string
    {
        return 'De rol '.self::EXCLUSIEF.' gaat niet samen met een ISMS-rol op hetzelfde account. '
            .'Technisch beheer en toegang tot de inhoud van het ISMS horen bij twee verschillende '
            .'personen; maak daarvoor een apart account aan.';
    }
}
