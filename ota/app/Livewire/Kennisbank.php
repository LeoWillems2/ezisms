<?php

namespace App\Livewire;

use App\Support\Kennisartikelen;
use App\Support\Kennisbankzoeker;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * De kennisbank: gecureerde ISMS-uitleg als markdown-artikelen uit de repo
 * (`resources/kennisbank/`). Read-only en voor elke ingelogde gebruiker
 * toegankelijk — het is naslag, geen blok-specifieke functionaliteit.
 */
#[Layout('components.layouts.app')]
class Kennisbank extends Component
{
    /** De slug van het getoonde artikel; volgt de route-parameter. */
    public ?string $slug = null;

    /**
     * De zoekterm staat in de URL, zodat een zoekopdracht deelbaar en
     * bookmarkbaar is — dezelfde keuze als bij de filters op /soa en /audit-log.
     */
    #[Url(as: 'q', except: '')]
    public string $zoekterm = '';

    public function mount(?string $slug = null): void
    {
        // Onbekende slug is een 404; geen slug toont het eerste artikel.
        if ($slug !== null && ! Kennisartikelen::bestaat($slug)) {
            abort(404);
        }

        $this->slug = $slug ?? Kennisartikelen::eersteSlug();
    }

    public function render()
    {
        $meta = $this->slug !== null ? Kennisartikelen::metadata($this->slug) : null;
        $zoekterm = trim($this->zoekterm);

        return view('livewire.kennisbank', [
            // Artikelen gegroepeerd per categorie voor de lijst. preserveKeys:
            // true is essentieel — zonder dat hernummert groupBy de slugs naar
            // 0,1,… en linkt de lijst naar /kennisbank/0 (404).
            'perCategorie' => collect(Kennisartikelen::alles())
                ->groupBy('categorie', preserveKeys: true),
            'huidigeSlug' => $this->slug,
            'titel' => $meta['titel'] ?? null,
            // Inhoud komt uit de repo (vertrouwd), niet van gebruikers.
            'inhoudHtml' => $this->slug !== null ? Kennisartikelen::html($this->slug) : null,
            // Niet elk artikel levert een bruikbaar Word-document op; het
            // register bepaalt welke. Hier en niet in de view, zodat de knop en
            // de route dezelfde bron hebben.
            'downloadbaar' => $this->slug !== null && Kennisartikelen::isDownloadbaar($this->slug),
            'zoekt' => $zoekterm !== '',
            'resultaten' => $zoekterm !== '' ? Kennisbankzoeker::zoek($zoekterm) : [],
        ]);
    }
}
