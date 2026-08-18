<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\Auditronde;
use App\Models\Gebruiker;
use App\Models\Leverancier;
use App\Models\OverheidsmaatregelBeoordeling;
use App\Models\Risico;
use App\Models\ScopeVerklaring;
use App\Models\SoaRegel;
use App\Models\Wijziging;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * De entiteiten waaraan een bewijsstuk gekoppeld kan worden, met het blok
 * waarvan het muteerrecht daarvoor nodig is.
 *
 * De leesbare omschrijving komt uit `auditOmschrijving()` van de
 * `Auditeerbaar`-trait: die bestaat al op precies deze modellen en levert per
 * model de juiste weergave ("A.5.1 Beleidsregels...", "Scope-verklaring v2").
 * Een tweede, bijna-identieke labelmethode ernaast zou onvermijdelijk uit de
 * pas gaan lopen.
 */
final class Koppelbaar
{
    /**
     * `capaciteit` is optioneel en verwijst naar een capaciteit uit
     * `config/norm.php`: een type dat alleen bestaat in een installatie die dat
     * begrip kent. Zonder die filter zou de keuzelijst in elk profiel een lege
     * categorie tonen — een type aanbieden waar nooit iets in kan zitten is
     * verwarrender dan het weglaten.
     *
     * @var array<string, array{label: string, model: class-string<Model>, blok: string, capaciteit?: string}>
     */
    public const TYPES = [
        'asset' => ['label' => 'Asset', 'model' => Asset::class, 'blok' => 'asset-classificatie'],
        'risico' => ['label' => 'Risico', 'model' => Risico::class, 'blok' => 'risico-soa'],
        'soa_regel' => ['label' => 'SoA-maatregel', 'model' => SoaRegel::class, 'blok' => 'risico-soa'],
        // De BIO-verplichting onder een beheersmaatregel. Dit is waar het extra
        // detailniveau zich uitbetaalt: bewijs bij 5.24.03 in plaats van bij 5.24,
        // en dat is precies het niveau waarop deel 1 §4 om opzet, bestaan en
        // werking vraagt.
        'overheidsmaatregel_beoordeling' => [
            'label' => 'BIO-overheidsmaatregel',
            'model' => OverheidsmaatregelBeoordeling::class,
            'blok' => 'risico-soa',
            'capaciteit' => 'overheidsmaatregelen',
        ],
        'scope_verklaring' => ['label' => 'Scope-verklaring', 'model' => ScopeVerklaring::class, 'blok' => 'context-scope'],
        'leverancier' => ['label' => 'Leverancier', 'model' => Leverancier::class, 'blok' => 'leveranciers-derdenrisico'],
        'auditronde' => ['label' => 'Auditronde', 'model' => Auditronde::class, 'blok' => 'auditmanagement'],
        // Personeelsbewijs: getekende NDA, VOG/referentiecheck, offboarding-
        // checklist. Muteerrecht op identity-access (CISO) is vereist om te
        // koppelen — een Medewerker koppelt geen bewijs aan accounts.
        'gebruiker' => ['label' => 'Gebruiker', 'model' => Gebruiker::class, 'blok' => 'identity-access'],
        // Release notes, testrapport, acceptatieverklaring, goedkeuringsmail —
        // het bewijs achter A.8.32 d) en g) (blok 15).
        'wijziging' => ['label' => 'Wijziging', 'model' => Wijziging::class, 'blok' => 'wijzigingsbeheer'],
    ];

    /**
     * Alleen de types waarvan de gebruiker het bronblok mag muteren. Dit is
     * geen cosmetica: zonder deze filter lekt de keuzelijst de titels van
     * risico's aan iemand die blok 4 niet eens mag inzien.
     *
     * @return array<string, string> alias => label
     */
    public static function toegestaneTypes(): array
    {
        $toegestaan = [];

        foreach (self::TYPES as $alias => $type) {
            if (isset($type['capaciteit']) && ! Normprofiel::heeft($type['capaciteit'])) {
                continue;
            }

            if (Gate::allows('heeft-niveau', [$type['blok'], 'muteren'])) {
                $toegestaan[$alias] = $type['label'];
            }
        }

        return $toegestaan;
    }

    public static function magKoppelenAan(string $alias): bool
    {
        return array_key_exists($alias, self::toegestaneTypes());
    }

    public static function blokVan(string $alias): ?string
    {
        return self::TYPES[$alias]['blok'] ?? null;
    }

    /**
     * Keuzelijst voor één type, gesorteerd op omschrijving.
     *
     * @return array<int, string> id => omschrijving
     */
    public static function opties(string $alias): array
    {
        if (! self::magKoppelenAan($alias)) {
            return [];
        }

        /** @var class-string<Model> $model */
        $model = self::TYPES[$alias]['model'];

        return $model::query()
            ->when($alias === 'soa_regel', fn ($q) => $q->with('maatregel'))
            ->when($alias === 'overheidsmaatregel_beoordeling', fn ($q) => $q->with('overheidsmaatregel'))
            ->get()
            ->mapWithKeys(fn (Model $rij) => [$rij->getKey() => $rij->auditOmschrijving()])
            ->sort()
            ->all();
    }
}
