<?php

namespace App\Support;

use App\Models\Incident;
use App\Models\IncidentMelding;
use Illuminate\Support\Carbon;

/**
 * Vertaalt de wettelijke termijnen uit `config/meldplicht.php` naar concrete
 * meldverplichtingen bij een incident (implementatie/08b §5).
 *
 * Eén plek die de ankerpunten kent, want die verschillen per fase en dat is de
 * makkelijkste fout om te maken: de vroegtijdige waarschuwing en de melding
 * rekenen vanaf kennisname, het eindverslag vanaf de melding, en bij een
 * voortdurend incident vanaf de afhandeling. Er bestaat geen enkele formule over
 * alle rijen.
 */
final class Meldplicht
{
    /** @return array<string, array<string, mixed>> */
    public static function grondslagen(): array
    {
        return config('meldplicht.grondslagen', []);
    }

    /**
     * De keuzelijst grondslag => label, voor het scherm.
     *
     * @return array<string, string>
     */
    public static function opties(): array
    {
        return array_map(fn (array $g) => $g['label'], self::grondslagen());
    }

    /**
     * Maakt de ontbrekende meldverplichtingen aan voor de gekozen grondslagen.
     *
     * Idempotent via `firstOrCreate` op (incident, grondslag, fase): opnieuw
     * draaien na het bijstellen van `kennisname_op` voegt niets dubbel toe. Het
     * werkt bestaande rijen ook **niet** bij — `uiterlijk_op` is een opgeslagen
     * besluit en geen berekening die mag meebewegen. Wie de datum wil wijzigen,
     * doet dat expliciet op de rij zelf.
     *
     * @param  list<string>  $grondslagen
     * @param  list<string>  $extraFasen  fasen die niet `altijd` zijn (bijv. de
     *                                    mededeling aan betrokkenen bij hoog risico)
     * @return int het aantal nieuw aangemaakte verplichtingen
     */
    public static function stelVast(Incident $incident, array $grondslagen, array $extraFasen = []): int
    {
        $nieuw = 0;

        foreach (self::grondslagen() as $grondslag => $instelling) {
            if (! in_array($grondslag, $grondslagen, true)) {
                continue;
            }

            foreach ($instelling['fasen'] as $fase => $regel) {
                if (! $regel['altijd'] && ! in_array("{$grondslag}:{$fase}", $extraFasen, true)) {
                    continue;
                }

                $melding = IncidentMelding::firstOrCreate(
                    ['incident_id' => $incident->id, 'grondslag' => $grondslag, 'fase' => $fase],
                    [
                        'meldtermijn_uren' => $regel['uren'],
                        'uiterlijk_op' => self::uiterlijk($incident, $regel),
                    ],
                );

                $nieuw += $melding->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $nieuw;
    }

    /**
     * Vult alsnog de deadline in van verplichtingen waarvan het ankerpunt pas
     * later bekend werd.
     *
     * Het Cbw-eindverslag rekent vanaf de melding uit art. 27 lid 1, en bij een
     * voortdurend incident vanaf de afhandeling (art. 29 lid 2) — beide zijn
     * onbekend op het moment dat de verplichting ontstaat. Daarom staat
     * `uiterlijk_op` dan op `null` ("verplicht, nog geen datum") en vult deze
     * methode hem aan zodra het ankerpunt er is.
     *
     * Werkt uitsluitend rijen bij die nog géén datum hebben. Een eenmaal
     * vastgestelde deadline is een besluit, geen berekening die mag meebewegen.
     *
     * @return int het aantal bijgewerkte verplichtingen
     */
    public static function werkAfgeleideTermijnenBij(Incident $incident): int
    {
        $bijgewerkt = 0;

        foreach ($incident->meldingen()->whereNull('uiterlijk_op')->get() as $melding) {
            $regel = config("meldplicht.grondslagen.{$melding->grondslag}.fasen.{$melding->fase}");

            if ($regel === null || ($uiterlijk = self::uiterlijk($incident, $regel)) === null) {
                continue;
            }

            $melding->update(['uiterlijk_op' => $uiterlijk]);
            $bijgewerkt++;
        }

        return $bijgewerkt;
    }

    /**
     * De uiterste datum, of `null` als die (nog) niet te bepalen is.
     *
     * `null` is hier een normale uitkomst en geen fout: AVG art. 34 kent geen
     * termijn, en het Cbw-eindverslag bij een voortdurend incident krijgt er pas
     * een bij afhandeling. Het scherm toont die als "verplicht, nog geen datum"
     * en niet als een berekende datum die stilzwijgend opschuift.
     *
     * @param  array<string, mixed>  $regel
     */
    private static function uiterlijk(Incident $incident, array $regel): ?Carbon
    {
        if ($regel['uren'] === null) {
            return null;
        }

        $start = match ($regel['anker']) {
            // Het wettelijke startmoment. Zonder kennisname geen deadline: liever
            // geen datum dan een datum die vanaf het verkeerde moment telt.
            'kennisname' => $incident->kennisname_op,
            // Cbw art. 29 lid 1: één maand na de melding uit art. 27 lid 1.
            'melding' => $incident->meldingen()
                ->where('fase', 'melding')->whereNotNull('gemeld_op')->value('gemeld_op'),
            // Cbw art. 29 lid 2: pas te bepalen als het incident is afgehandeld.
            'afhandeling' => $incident->gesloten_op,
            default => null,
        };

        return $start === null ? null : Carbon::parse($start)->addHours($regel['uren']);
    }
}
