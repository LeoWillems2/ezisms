<?php

namespace App\Support;

use App\Models\Risico;
use Illuminate\Database\Eloquent\Builder;

/**
 * De risico's zoals het risicoregister ze toont: de query, de filters en de
 * volgorde van `RisicosOverzicht`, op één plek.
 *
 * Bestaat sinds de assistenttoegang (implementatie/14b §4.3): de tool
 * `risicos` hoort dezelfde rijen in dezelfde volgorde te zien als het scherm.
 * Geen record-scoping: op `risico-soa` heeft de Medewerker geen recht, en wie
 * het wel heeft, ziet het hele register.
 */
final class Risicoselectie
{
    /**
     * De statuslabels van het register. Eén plek, zodat scherm, schermkopie en
     * assistent niet uiteenlopen.
     */
    public const STATUS_LABELS = [
        'geidentificeerd' => 'Geïdentificeerd',
        'beoordeeld' => 'Beoordeeld',
        'behandelplan_opgesteld' => 'Behandelplan opgesteld',
        'geaccepteerd' => 'Geaccepteerd',
        'in_uitvoering' => 'In uitvoering',
        'gemitigeerd' => 'Gemitigeerd',
    ];

    /**
     * De rollen van de eigenaar worden meegeladen voor de anonimisering in de
     * schermkopie; het scherm zelf gebruikt ze niet, maar een tweede query per
     * eigenaar tijdens het bouwen van de kopie is een N+1 die niemand ziet.
     *
     * @return Builder<Risico>
     */
    public static function query(string $status = '', bool $alleenBovenDrempel = false, string $issue = ''): Builder
    {
        $drempel = Risico::drempelwaarde();

        return Risico::query()
            ->with(['eigenaar.rollen'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($alleenBovenDrempel, fn ($q) => $q->where('risicoscore', '>', $drempel))
            ->when($issue !== '', fn ($q) => $q->whereHas(
                'aanleidingen', fn ($i) => $i->whereKey($issue)
            ))
            ->orderByDesc('risicoscore')
            ->orderBy('titel');
    }
}
