<?php

namespace App\Support\Demo\Handlers;

use App\Models\Beleidsdocument;
use App\Models\Beleidsversie;
use App\Models\Gebruiker;
use App\Models\Leesbevestiging;
use App\Models\SoaRegel;
use App\Support\Beleidspublicatie;
use App\Support\Demo\DemoFixtureFout;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use App\Support\Koppeling;
use Illuminate\Support\Facades\Gate;

/**
 * Beleidsdocumenten, hun versies en de leesbevestigingen.
 *
 * Publiceren is sinds implementatie/01c een goedkeuractie: de CISO schrijft de
 * versie (`muteren`), een directeur stelt hem vast (`goedkeuren`). Die splitsing
 * is de reden dat elke publicatie hier uit twee `Handelt`-blokken bestaat.
 */
final class BeleidHandlers
{
    /**
     * Vanaf welk moment een publicatie meteen om leesbevestigingen vraagt.
     * Vóór de bevestigingsronde van M6 bestond dat programma nog niet; die ronde
     * haalt de achterstand in één keer op.
     */
    private bool $bevestigingenLopen = false;

    /** @return array<string, callable> */
    public function register(): array
    {
        return [
            'beleid_publiceren' => $this->beleidPubliceren(...),
            'leesbevestigingen_uitzetten' => $this->leesbevestigingenUitzetten(...),
        ];
    }

    private function beleidPubliceren(array $g, int $maand, Simulatie $sim): void
    {
        $def = $sim->fixtures()->definitie('beleid', 'documenten', $g['document']);
        $versieDef = $this->versiedefinitie($def, (int) $g['versie']);
        $ciso = $sim->gebruiker('ciske');

        $versie = Handelt::als($ciso)
            ->mits('heeft-niveau', ['beleid-maatregelbeheer', 'muteren'])
            ->bij("M{$maand}/beleid_publiceren/{$g['document']} v{$g['versie']}")
            ->doe(function () use ($def, $versieDef, $g, $sim) {
                $document = $this->borgDocument($def, $sim);

                $versie = Beleidsversie::create([
                    'beleidsdocument_id' => $document->id,
                    'versienummer' => (int) $g['versie'],
                    'wijzigingsreden' => $versieDef['wijzigingsreden'] ?? null,
                    'status' => 'concept',
                ]);

                // Publiceren kan niet zonder bestand (implementatie/05 §4), en
                // dat is precies de bedoeling: een versie zonder document is
                // niets om te lezen en dus niets om te bevestigen.
                $bewijs = $sim->bewijs()->maak(
                    $def['titel']." v{$g['versie']}",
                    $versieDef['wijzigingsreden'] ?? null,
                    $document,
                );

                $versie->update([
                    'bewijsstuk_id' => $bewijs->id,
                    'status' => 'ter_goedkeuring',
                    'volgende_herziening_gepland' => now()->addYear(),
                ]);

                return $versie;
            });

        // De vaststelling zelf. Een aparte handeling, door een andere persoon,
        // en dus een aparte regel in de audit trail.
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['beleid-maatregelbeheer', 'goedkeuren'])
            ->bij("M{$maand}/beleid_vaststellen/{$g['document']} v{$g['versie']}")
            ->doe(fn () => Beleidspublicatie::publiceer($versie, $sim->gebruiker($g['door'])));

        $sim->fixtures()->onthoud("{$g['document']}-v{$g['versie']}", $versie->refresh());

        if ($this->bevestigingenLopen) {
            $this->bevestig($versie, $sim, $maand);
        }
    }

    /**
     * De bevestigingsronde: alles wat op dit moment actief is en om bevestiging
     * vraagt, wordt door de doelgroep bevestigd. Vanaf nu doet elke publicatie
     * dat meteen zelf.
     */
    private function leesbevestigingenUitzetten(array $g, int $maand, Simulatie $sim): void
    {
        $this->bevestigingenLopen = true;

        $versies = Beleidsversie::where('status', 'actief')
            ->whereHas('document', fn ($q) => $q->where('leesbevestiging_vereist', true))
            ->get();

        foreach ($versies as $versie) {
            $this->bevestig($versie, $sim, $maand);
        }
    }

    /**
     * Laat de doelgroep de versie bevestigen — ieder voor zichzelf, want een
     * leesbevestiging namens iemand anders is geen bewijs.
     *
     * Wie in `openstaand_in_eindstand` staat, slaat zijn bevestiging bewust over:
     * een bevestigingsgraad van 100% maakt het signaal betekenisloos.
     */
    private function bevestig(Beleidsversie $versie, Simulatie $sim, int $maand): void
    {
        $document = $versie->document()->first();

        if (! $document->leesbevestiging_vereist) {
            return;
        }

        $open = $this->openstaand($sim, $document, $versie->versienummer);

        foreach ($document->doelgroepGebruikerIds() as $gebruikerId) {
            $gebruiker = Gebruiker::find($gebruikerId);

            if ($gebruiker === null || in_array($gebruiker->id, $open, true)) {
                continue;
            }

            // De Auditor-rol heeft geen `uitvoeren` op dit blok en kan dus geen
            // eigen bevestiging vastleggen. Overslaan in plaats van namens hem
            // tekenen — zie de notitie bij TrainingHandlers.
            if (! Gate::forUser($gebruiker)->allows('heeft-niveau', ['beleid-maatregelbeheer', 'uitvoeren'])) {
                continue;
            }

            if ($versie->isBevestigdDoor($gebruiker->id)) {
                continue;
            }

            Handelt::als($gebruiker)
                ->mits('heeft-niveau', ['beleid-maatregelbeheer', 'uitvoeren'])
                ->bij("M{$maand}/leesbevestiging/{$document->titel}")
                ->doe(fn () => Leesbevestiging::create([
                    'beleidsversie_id' => $versie->id,
                    'gebruiker_id' => $gebruiker->id,
                    'bevestigd_op' => now(),
                ]));
        }
    }

    /**
     * De gebruiker-id's die deze versie bewust níet bevestigen.
     *
     * @return list<int>
     */
    private function openstaand(Simulatie $sim, Beleidsdocument $document, int $versienummer): array
    {
        $regels = $sim->fixtures()->bestand('beleid')['leesbevestigingen']['openstaand_in_eindstand'] ?? [];
        $ids = [];

        foreach ($regels as $regel) {
            $def = $sim->fixtures()->definitie('beleid', 'documenten', $regel['document']);

            if ($def['titel'] === $document->titel && (int) $regel['versie'] === $versienummer) {
                $ids[] = $sim->gebruiker($regel['gebruiker'])->id;
            }
        }

        return $ids;
    }

    /** Het document bestaat vanaf de eerste versie; latere versies vinden het terug. */
    private function borgDocument(array $def, Simulatie $sim): Beleidsdocument
    {
        if ($sim->fixtures()->kent($def['sleutel'])) {
            return $sim->fixtures()->model($def['sleutel']);
        }

        $document = Beleidsdocument::create([
            'titel' => $def['titel'],
            'type' => $def['type'],
            'eigenaar_id' => $sim->gebruiker($def['eigenaar'])->id,
            'leesbevestiging_vereist' => $def['leesbevestiging_vereist'],
        ]);

        Koppeling::sync(
            $document->soaRegels(),
            'maatregelen',
            SoaRegel::whereHas('maatregel', fn ($q) => $q->whereIn('annex_a_referentie', $def['soa_regels']))
                ->pluck('id')->all()
        );

        Koppeling::sync($document->afdelingen(), 'afdelingen', $this->afdelingenVoor($def['doelgroepen'], $sim));

        return $sim->fixtures()->onthoud($def['sleutel'], $document);
    }

    /**
     * De bevestigingsplicht hangt in blok 5 aan afdelingen, terwijl de fixtures
     * doelgroepen met leden noemen. De afdelingen zijn af te leiden uit de
     * eenheden van die leden — zo blijft er één definitie van "wie hoort erbij"
     * en kunnen de twee niet uit elkaar lopen.
     *
     * @return list<int>
     */
    private function afdelingenVoor(array $doelgroepen, Simulatie $sim): array
    {
        $eenheden = [];

        foreach ($doelgroepen as $sleutel) {
            $doelgroep = $sim->fixtures()->definitie('personen', 'doelgroepen', $sleutel);

            foreach ($doelgroep['leden'] as $lid) {
                $persoon = $sim->fixtures()->definitie('personen', 'gebruikers', $lid);
                $eenheden[] = $sim->fixtures()->model($persoon['eenheid'])->id;
            }
        }

        return array_values(array_unique($eenheden));
    }

    /** @return array<string, mixed> */
    private function versiedefinitie(array $def, int $versienummer): array
    {
        foreach ($def['versies'] as $versie) {
            if ((int) $versie['versienummer'] === $versienummer) {
                return $versie;
            }
        }

        throw DemoFixtureFout::bij("beleid/{$def['sleutel']}", "versie {$versienummer} bestaat niet");
    }
}
