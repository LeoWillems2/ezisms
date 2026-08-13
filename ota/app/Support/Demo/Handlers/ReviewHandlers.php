<?php

namespace App\Support\Demo\Handlers;

use App\Models\Agendapunt;
use App\Models\Besluit;
use App\Models\Reviewsessie;
use App\Models\Verbeteractie;
use App\Support\Demo\DemoFixtureFout;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;

/**
 * De directiebeoordeling (§9.3).
 *
 * Twee handen, net als bij beleid en scope: de CISO bereidt de sessie voor en
 * legt de negen verplichte inputs vast (`muteren`), de directie stelt vast dat
 * de sessie is gehouden (`goedkeuren`, implementatie/01c §4). Die laatste stap
 * is het bewijs dat de directie de beoordeling daadwerkelijk heeft gedaan — en
 * dus geen veldje in een formulier van de CISO.
 */
final class ReviewHandlers
{
    /** @return array<string, callable> */
    public function register(): array
    {
        return ['directiebeoordeling' => $this->directiebeoordeling(...)];
    }

    private function directiebeoordeling(array $g, int $maand, Simulatie $sim): void
    {
        $def = $sim->fixtures()->bestand('reviews')['sessies'][$g['sleutel']]
            ?? throw DemoFixtureFout::bij('reviews/sessies', "geen sessie '{$g['sleutel']}'");

        $this->controleerAantallen($g, $def);

        $sessie = Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['management-review-verbetercyclus', 'muteren'])
            ->bij("M{$maand}/directiebeoordeling/{$g['sleutel']} (voorbereiden)")
            ->doe(function () use ($g, $def, $sim) {
                $sessie = Reviewsessie::create([
                    'datum' => now(),
                    'deelnemers' => collect($g['deelnemers'])
                        ->map(fn (string $s) => $sim->gebruiker($s)->naam)
                        ->implode(', '),
                    'status' => 'gepland',
                ]);

                foreach ($def['agendapunten'] as $punt) {
                    Agendapunt::create([
                        'reviewsessie_id' => $sessie->id,
                        'categorie' => $punt['categorie'],
                        'samenvatting' => $punt['samenvatting'],
                        'gekoppeld_blok_naam' => $punt['gekoppeld_blok_naam'] ?? null,
                    ]);
                }

                $besluiten = [];

                foreach ($def['besluiten'] as $index => $besluit) {
                    $besluiten[$index + 1] = Besluit::create([
                        'reviewsessie_id' => $sessie->id,
                        'omschrijving' => $besluit['omschrijving'],
                    ]);
                }

                foreach ($def['verbeteracties'] as $actie) {
                    Verbeteractie::create([
                        'besluit_id' => $besluiten[$actie['besluit']]->id,
                        'omschrijving' => $actie['omschrijving'],
                        'eigenaar_id' => $sim->gebruiker($actie['eigenaar'])->id,
                        'deadline' => $sim->klok()->datum((int) $actie['deadline_maand'])->endOfMonth()->startOfDay(),
                        'status' => $actie['voltooid'] ? 'voltooid' : 'open',
                        'voltooid_op' => $actie['voltooid']
                            ? $sim->klok()->datum((int) $actie['deadline_maand'])
                            : null,
                    ]);
                }

                return $sessie;
            });

        Handelt::als($sim->gebruiker($g['gehouden_door']))
            ->mits('heeft-niveau', ['management-review-verbetercyclus', 'goedkeuren'])
            ->bij("M{$maand}/directiebeoordeling/{$g['sleutel']} (vaststellen)")
            ->doe(fn () => $sessie->update(['status' => 'gehouden']));

        $sim->fixtures()->onthoud($g['sleutel'], $sessie->refresh());
    }

    /**
     * De tijdlijn noemt aantallen, dit bestand de inhoud. Lopen ze uiteen, dan
     * toont de demo iets anders dan het scenario beschrijft — beter hier
     * omvallen dan dat achteraf in een screenshot ontdekken.
     */
    private function controleerAantallen(array $g, array $def): void
    {
        $verwacht = [
            'agendapunten' => count(Reviewsessie::VERPLICHTE_CATEGORIEEN),
            'besluiten' => (int) $g['besluiten'],
            'verbeteracties' => (int) $g['verbeteracties'],
        ];

        foreach ($verwacht as $wat => $aantal) {
            if (count($def[$wat]) !== $aantal) {
                throw DemoFixtureFout::bij(
                    "reviews/{$g['sleutel']}",
                    sprintf('%d %s verwacht, %d gevonden', $aantal, $wat, count($def[$wat]))
                );
            }
        }

        $open = count(array_filter($def['verbeteracties'], fn (array $a) => ! $a['voltooid']));

        if ($open !== (int) ($g['verbeteracties_open'] ?? 0)) {
            throw DemoFixtureFout::bij(
                "reviews/{$g['sleutel']}",
                sprintf('%d openstaande verbeteractie(s) verwacht, %d gevonden', $g['verbeteracties_open'] ?? 0, $open)
            );
        }
    }
}
