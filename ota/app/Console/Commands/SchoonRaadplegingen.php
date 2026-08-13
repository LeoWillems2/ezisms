<?php

namespace App\Console\Commands;

use App\Models\AuditLogregel;
use App\Models\Raadpleging;
use Illuminate\Console\Command;

/**
 * Handhaaft de bewaartermijn op `raadplegingen` (implementatie/05 §14).
 *
 * Dit sluit het openstaande punt uit blok 5: de tabel groeide onbeperkt, en
 * "voor altijd omdat het kan" is bij registratie van leesgedrag van werknemers
 * geen houdbare termijn. Anders dan `isms:archiveer-bewijsstukken`, dat bewust
 * niets verwijdert, verwijdert dit commando wél — bij een bewijsstuk is de
 * inhoud het bewijs, bij een raadpleging is de registratie zelf het gegeven
 * waarvan de bewaring verantwoord moet worden.
 */
class SchoonRaadplegingen extends Command
{
    protected $signature = 'isms:schoon-raadplegingen';

    protected $description = 'Verwijdert raadplegingen ouder dan de bewaartermijn';

    /**
     * Bewaartermijn in dagen. Staat NIET in de norm — een eigen keuze, lang
     * genoeg om een leesbevestiging te onderbouwen (de leestermijn is 30 dagen,
     * zie GenereerTaken::LEESTERMIJN_DAGEN) en kort genoeg om geen
     * leesgeschiedenis per medewerker op te bouwen.
     */
    public const BEWAARTERMIJN_DAGEN = 60;

    public function handle(): int
    {
        $grens = now()->subDays(self::BEWAARTERMIJN_DAGEN);

        $aantal = Raadpleging::verwijderOuderDan($grens);

        // Eén regel per opschoning, niet per verwijderde rij: per rij loggen zou
        // het leesgedrag naar de audit trail verplaatsen in plaats van het te
        // verwijderen. Dit legt vast dát de bewaartermijn is gehandhaafd en op
        // hoeveel regels — zonder te herhalen wie wat had opgehaald.
        //
        // Alleen bij een niet-lege opschoning. Een lege run elke nacht loggen
        // levert 365 regels ruis per jaar op, en dat de termijn wordt
        // gehandhaafd is toch al rechtstreeks in de data te zien: er hoort geen
        // raadpleging ouder dan de termijn te bestaan.
        if ($aantal > 0) {
            AuditLogregel::legVerzamelingVast(
                blokNaam: 'beleid-maatregelbeheer',
                entiteitType: 'raadpleging',
                actie: 'verwijderd',
                omschrijving: "Bewaartermijn raadplegingen ({$aantal} regels)",
                details: [
                    'aantal' => $aantal,
                    'bewaartermijn_dagen' => self::BEWAARTERMIJN_DAGEN,
                    'ouder_dan' => $grens->toDateTimeString(),
                ],
            );
        }

        $this->info("{$aantal} raadpleging(en) verwijderd (ouder dan {$grens->format('d-m-Y')}).");

        return self::SUCCESS;
    }
}
