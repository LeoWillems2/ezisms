<?php

namespace App\Livewire;

use App\Models\AssistentAanroep;
use App\Models\Blok;
use App\Models\Gebruiker;
use App\Models\Rol;
use App\Models\RolPermissie;
use App\Support\Assistent\Configuratie;
use App\Support\Assistent\Koppelingen;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Token;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * De assistenttoegang beheren (implementatie/14b §7.2, §8.1): welke rollen een
 * AI-assistent mogen koppelen, wie dat gedaan heeft, en wat er is opgevraagd.
 *
 * Bij het integratieblok, op dezelfde niveaus als het integratieregister:
 * lezen voor de Auditor (wie heeft toegang en wat is er opgevraagd), muteren
 * voor de CISO (aanzetten per rol, intrekken). Het aanzetten per rol is een
 * besluit over een gegevensstroom en daarmee een ISMS-besluit, geen
 * technische instelling; daarom hier en niet bij de Administrator.
 */
#[Layout('components.layouts.app')]
class AssistenttoegangBeheer extends Component
{
    private const LOG_REGELS = 100;

    #[Url]
    public string $filterGebruiker = '';

    #[Url]
    public string $filterNaam = '';

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['notificatie-integratielaag', 'muteren']);
    }

    public function zetRol(int $rolId, bool $aan): void
    {
        abort_unless($this->magMuteren(), 403);

        $rol = Rol::query()->whereKeyNot($this->administratorId())->findOrFail($rolId);
        $blokId = Blok::where('code', Configuratie::BLOK)->value('id');

        if ($aan) {
            RolPermissie::firstOrCreate(['rol_id' => $rol->id, 'blok_id' => $blokId, 'niveau' => 'lezen']);
            session()->flash('melding', "De rol {$rol->naam} mag nu een assistent koppelen.");

            return;
        }

        // Per rij via het model: dat schrijft de audit trail, wist de
        // onthouden autorisatieantwoorden en trekt koppelingen in die nu niet
        // meer mogen (AppServiceProvider::registreerAssistenttoegang).
        RolPermissie::query()->where('rol_id', $rol->id)->where('blok_id', $blokId)->get()->each->delete();
        session()->flash('melding', "De rol {$rol->naam} mag geen assistent meer koppelen; bestaande koppelingen zonder recht zijn ingetrokken.");
    }

    public function trekIn(int $gebruikerId, string $clientId): void
    {
        abort_unless($this->magMuteren(), 403);

        $gebruiker = Gebruiker::findOrFail($gebruikerId);
        $aantal = Koppelingen::trekIn($gebruiker, $clientId, reden: 'door de CISO');

        session()->flash('melding', $aantal > 0 ? "Koppeling van {$gebruiker->naam} ingetrokken." : 'Er was niets meer in te trekken.');
    }

    public function render()
    {
        $blokId = Blok::where('code', Configuratie::BLOK)->value('id');
        $toegestaan = RolPermissie::query()->where('blok_id', $blokId)->pluck('rol_id')->all();

        $gebruikersMetToken = Gebruiker::query()
            ->whereKey(Token::query()->where('revoked', false)->distinct()->pluck('user_id'))
            ->orderBy('naam')
            ->get();

        $log = AssistentAanroep::query()
            ->with('gebruiker:id,naam')
            ->when($this->filterGebruiker !== '', fn ($q) => $q->where('gebruiker_id', (int) $this->filterGebruiker))
            // Zelfde escape als in de tool `zoek`: werkt op MySQL én SQLite.
            ->when($this->filterNaam !== '', fn ($q) => $q->whereRaw(
                "naam like ? escape '!'",
                [str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->filterNaam).'%'],
            ))
            ->orderByDesc('aangeroepen_op')
            ->orderByDesc('id')
            ->limit(self::LOG_REGELS)
            ->get();

        return view('livewire.assistenttoegang-beheer', [
            'rollen' => Rol::query()->whereKeyNot($this->administratorId())->orderBy('naam')->get(),
            'toegestaan' => $toegestaan,
            'koppelingen' => $gebruikersMetToken->map(fn (Gebruiker $g) => [
                'gebruiker' => $g,
                'clients' => Koppelingen::van($g),
            ])->filter(fn ($rij) => $rij['clients']->isNotEmpty())->values(),
            'log' => $log,
            'logGebruikers' => Gebruiker::query()
                ->whereKey(AssistentAanroep::query()->distinct()->pluck('gebruiker_id'))
                ->orderBy('naam')
                ->get(['id', 'naam']),
            'mcpUrl' => url('/mcp'),
            'logRegels' => self::LOG_REGELS,
        ]);
    }

    /** De Administrator heeft per definitie geen ISMS-rechten (01e §0). */
    private function administratorId(): ?int
    {
        return Rol::where('naam', 'Administrator')->value('id');
    }
}
