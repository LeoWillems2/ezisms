<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Statische bouwhulp voor toetsmakers (blok 10). Alleen bereikbaar op 'muteren'
 * (CISO): het legt uit hoe een toets aan het ISMS koppelt en biedt de
 * kant-en-klare `onQuizVoltooid`-functie als download.
 */
#[Layout('components.layouts.app')]
class Bouwhulp extends Component
{
    public function render()
    {
        return view('livewire.bouwhulp');
    }
}
