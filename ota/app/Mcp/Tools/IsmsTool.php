<?php

namespace App\Mcp\Tools;

use App\Support\Assistent\Aanroepcontext;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Basis voor elke ISMS-tool (implementatie/14b §4.3).
 *
 * Een tool die bij één scherm hoort, noemt in `vereist()` het (blok, niveau)
 * van de route van dat scherm. Dan:
 *
 * - staat hij alleen in `tools/list` voor wie dat recht heeft
 *   (`shouldRegister`, door laravel/mcp gelezen), en
 * - weigert `handle()` nog eens, voor het geval de lijst en de aanroep uit
 *   elkaar lopen. Een weigering gaat als `geweigerd` de aanroeplog in.
 *
 * Een tool die meerdere blokken raakt (`isms_overzicht`, `zoek`), geeft `null`
 * en toetst per onderdeel zelf. Die laat weg wat niet mag en zegt dat in de
 * uitvoer; een lege lijst zonder uitleg leest een model als "er is niets".
 *
 * **De regel uit §4.3:** een tool haalt zijn gegevens op met dezelfde scope,
 * query of klasse als het scherm. Geen tweede antwoord op de vraag wat deze
 * gebruiker mag zien.
 */
abstract class IsmsTool extends Tool
{
    /** Hoeveel rijen een lijst hooguit teruggeeft (14b §4.4). */
    protected const MAX_RIJEN = 200;

    /** @return array{0: string, 1: string}|null (blokcode, niveau), of null bij meerdere blokken */
    abstract protected function vereist(): ?array;

    abstract protected function beantwoord(Request $request): Response|ResponseFactory;

    public function shouldRegister(): bool
    {
        $vereist = $this->vereist();

        return $vereist === null || Gate::allows('heeft-niveau', $vereist);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if (! $this->shouldRegister()) {
            app(Aanroepcontext::class)->weigering();

            return Response::error('Geen leesrecht op dit onderdeel van het ISMS.');
        }

        return $this->beantwoord($request);
    }

    /**
     * Een enum als code én label, zodat het model het label niet zelf hoeft
     * te verzinnen (14b §4.4).
     *
     * @param  array<string, string>  $labels
     * @return array{code: string, label: string}|null
     */
    protected static function enum(?string $code, array $labels = []): ?array
    {
        if ($code === null) {
            return null;
        }

        return ['code' => $code, 'label' => $labels[$code] ?? ucfirst(str_replace('_', ' ', $code))];
    }
}
