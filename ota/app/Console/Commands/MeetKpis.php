<?php

namespace App\Console\Commands;

use App\Models\KpiDefinitie;
use App\Support\Meetbronnen;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Legt maandelijks de KPI-metingen vast (implementatie/12 §5). Onveranderlijk:
 * elke meting is een nieuw meetpunt, nooit een herberekening — historie is niet
 * met terugwerkende kracht te maken.
 *
 * De berekeningen zelf staan in `App\Support\Meetbronnen` (implementatie/12e §4);
 * dit commando kiest alleen wie er gemeten wordt en schrijft het resultaat weg.
 *
 * De nadruk ligt op Check (§3): dat meet of de cyclus drááit. De drie Act-rijen
 * en de eenmalige backfill hangen aan de audit trail en zijn bewust uitgesteld
 * (§5.3/§5.4).
 */
class MeetKpis extends Command
{
    protected $signature = 'isms:meet-kpis';

    protected $description = 'Legt de maandelijkse KPI-metingen vast (teller/noemer, onveranderlijk)';

    public function handle(): int
    {
        $vandaag = Carbon::today();
        // Het venster loopt tot het meetmoment zelf en niet tot middernacht:
        // anders valt een gebeurtenis later op de meetdag buiten elk venster.
        $meetmoment = Carbon::now();
        $aantal = 0;
        $onbekend = [];

        foreach (KpiDefinitie::where('actief', true)->get() as $definitie) {
            // Handmatige KPI: de CISO voert teller en noemer zelf in. Stil
            // overslaan, dat is de normale gang van zaken (12e §2).
            if ($definitie->meetbron === null) {
                continue;
            }

            // Een meetbron die uit de code is verdwenen terwijl er nog definities
            // aan hangen, moet luidruchtig zijn. Vóór 12e was dit niet te
            // onderscheiden van "geen populatie": geen melding, exitcode 0, en
            // een KPI die nooit meet ziet er precies zo uit als een KPI die nog
            // historie opbouwt (12e §1).
            if (! Meetbronnen::bestaat($definitie->meetbron)) {
                $onbekend[] = "{$definitie->sleutel} (meetbron: {$definitie->meetbron})";

                continue;
            }

            // Een gebeurtenismeting kijkt naar een periode in plaats van naar de
            // toestand nu. Het venster begint waar het vorige eindigde en niet
            // bij "de vorige kalendermaand": bij een gemiste run wordt de
            // volgende periode langer in plaats van dat de gebeurtenissen uit
            // die maand permanent buiten élke meting vallen (12g §3).
            $gebeurtenis = Meetbronnen::isGebeurtenis($definitie->meetbron);
            $periodeVan = $gebeurtenis ? $this->vorigePeriodegrens($definitie) : null;

            [$teller, $noemer] = Meetbronnen::bereken($definitie->meetbron, $periodeVan, $meetmoment);

            // Geen populatie: niets te meten, en geen lege meetrij die als 0%
            // leest (§5).
            if ($noemer === 0) {
                continue;
            }

            // Onveranderlijk + maandelijks: niet twee metingen in dezelfde maand.
            $bestaatDezeMaand = $definitie->metingen()
                ->whereYear('gemeten_op', $vandaag->year)
                ->whereMonth('gemeten_op', $vandaag->month)
                ->exists();

            if ($bestaatDezeMaand) {
                continue;
            }

            $definitie->metingen()->create([
                'gemeten_op' => $vandaag,
                // Leeg bij een toestandsmeting. Bij een gebeurtenismeting is
                // `periode_van` leeg op het eerste meetpunt: er is dan geen
                // ondergrens, en dat hoort zichtbaar te zijn in plaats van
                // ingevuld met een verzonnen startdatum.
                'periode_van' => $periodeVan,
                'periode_tot' => $gebeurtenis ? $meetmoment : null,
                'teller' => $teller,
                'noemer' => $noemer,
                // De norm gaat mee de meetrij in, net als de definitieversie
                // (12d §2b): een later bijgestelde streefwaarde mag de kleur van
                // bestaande meetpunten niet met terugwerkende kracht veranderen.
                // Alleen een vástgestelde norm telt mee — een meegeleverd
                // voorstel kleurt niets (12e §9).
                'definitie_versie' => $definitie->definitie_versie,
                'streefwaarde' => $definitie->vastgesteldeStreefwaarde(),
                'signaalwaarde' => $definitie->vastgesteldeSignaalwaarde(),
            ]);

            $aantal++;
        }

        $this->info("{$aantal} meting(en) vastgelegd.");

        if ($onbekend !== []) {
            $this->warn('Onbekende meetbron, deze KPI\'s zijn niet gemeten:');

            foreach ($onbekend as $regel) {
                $this->warn("  - {$regel}");
            }

            // Niet-nul, zodat een scheduler of CI dit oppikt. De overige
            // metingen zijn wél weggeschreven: één kapotte definitie mag de
            // maandelijkse run niet stilleggen.
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Waar het vorige venster van deze KPI eindigde, of `null` als dit het
     * eerste meetpunt is. Zelfherstellend: is er een maand overgeslagen, dan
     * loopt het nieuwe venster gewoon vanaf het laatste gemeten moment door.
     */
    private function vorigePeriodegrens(KpiDefinitie $definitie): ?Carbon
    {
        $grens = $definitie->metingen()->whereNotNull('periode_tot')->max('periode_tot');

        return $grens === null ? null : Carbon::parse($grens);
    }
}
