<?php

namespace App\Support\Demo;

use App\Support\Demo\Handlers\AfwijkingHandlers;
use App\Support\Demo\Handlers\AssetHandlers;
use App\Support\Demo\Handlers\AuditHandlers;
use App\Support\Demo\Handlers\BeleidHandlers;
use App\Support\Demo\Handlers\KpiHandlers;
use App\Support\Demo\Handlers\LeverancierHandlers;
use App\Support\Demo\Handlers\OrganisatieHandlers;
use App\Support\Demo\Handlers\ReviewHandlers;
use App\Support\Demo\Handlers\RisicoHandlers;
use App\Support\Demo\Handlers\SoaHandlers;
use App\Support\Demo\Handlers\TaakHandlers;
use App\Support\Demo\Handlers\TrainingHandlers;
use App\Support\Demo\Handlers\WijzigingHandlers;

/**
 * Het register: gebeurtenistype uit `tijdlijn.json` → handler.
 *
 * Het ontwerp in `saasdemo/simulatiemotor.md` §0 sprak van "één klasse per
 * type". Bij het bouwen is dat één klasse per **domein** geworden, met een
 * methode per type. De reden achter dat ontwerppunt was een `match` van 400
 * regels vermijden en per type kunnen testen; beide blijven overeind, terwijl
 * dertig bestanden met elk één methode vooral navigeerwerk oplevert. De
 * handlers van één domein delen bovendien hun opzoekhulpjes.
 *
 * De domeinklassen worden hier één keer geïnstantieerd en daarna vastgehouden:
 * sommige onthouden iets tussen gebeurtenissen door (welke taak bewust blijft
 * liggen, of de bevestigingsronde al is begonnen). Per aanroep een verse
 * instantie maken zou dat geheugen stilzwijgend wissen.
 */
final class Handlers
{
    /** @var list<object> */
    private array $domeinen;

    public function __construct()
    {
        $this->domeinen = [
            new OrganisatieHandlers,
            new AssetHandlers,
            new BeleidHandlers,
            new RisicoHandlers,
            new LeverancierHandlers,
            new SoaHandlers,
            new AuditHandlers,
            new AfwijkingHandlers,
            new TaakHandlers,
            new TrainingHandlers,
            new ReviewHandlers,
            new KpiHandlers,
            new WijzigingHandlers,
        ];
    }

    /** @return array<string, callable> */
    public function perType(): array
    {
        $handlers = [];

        foreach ($this->domeinen as $domein) {
            $handlers = [...$handlers, ...$domein->register()];
        }

        return $handlers;
    }

    /**
     * De domeinen die ook aan het einde van elke maand iets doen — het werk dat
     * niet in de tijdlijn staat omdat het niet bijzonder is.
     *
     * @return list<callable>
     */
    public function maandafsluiters(): array
    {
        return array_values(array_map(
            fn (object $domein) => $domein->maandafsluiting(...),
            array_filter($this->domeinen, fn (object $d) => method_exists($d, 'maandafsluiting')),
        ));
    }
}
