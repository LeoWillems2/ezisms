<?php

namespace App\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Rolgevoelig navigatiemenu (implementatie/00b-navigatie-en-lay-out.md §2).
 *
 * Geen Livewire-component: het menu heeft geen eigen interactieve state, dus
 * een helper die de Blade-layout rechtstreeks aanroept volstaat. Er staan
 * bewust geen rolchecks in de view zelf — alles loopt via de generieke Gate.
 */
final class Navigatie
{
    /** @return NavigatieItem[] */
    public static function zichtbareItems(): array
    {
        $items = [
            new NavigatieItem('Gebruikers', 'gebruikers.index', 'identity-access', 'users'),
            new NavigatieItem('Context & Scope', 'scope.show', 'context-scope', 'building-office'),
            new NavigatieItem('Assets', 'assets.index', 'asset-classificatie', 'server-stack'),
            new NavigatieItem("SoA & Risico's", 'soa.index', 'risico-soa', 'shield-check'),
            // Op 'uitvoeren': de Medewerker geeft hier zijn leesbevestigingen af.
            new NavigatieItem('Beleid & procedures', 'beleid.index', 'beleid-maatregelbeheer', 'document-text', 'uitvoeren'),
            // Op 'uitvoeren' en niet 'lezen': Medewerker mag hier zijn eigen
            // bewijs uploaden, en dat is precies wat dit item ontsluit.
            new NavigatieItem('Bewijs & audit trail', 'bewijsstukken.index', 'bewijsrepository-audit-trail', 'document-check', 'uitvoeren'),
            // Op 'uitvoeren': melden moet iedereen kunnen. Drempels daarop
            // leveren minder meldingen op, niet minder incidenten.
            new NavigatieItem('Incidenten', 'incidenten.index', 'incident-afwijkingenbeheer', 'exclamation-triangle', 'uitvoeren'),
            new NavigatieItem('Afwijkingen', 'afwijkingen.index', 'incident-afwijkingenbeheer', 'wrench-screwdriver', 'muteren'),
            new NavigatieItem('Leveranciers', 'leveranciers.index', 'leveranciers-derdenrisico', 'truck'),
            // Op 'uitvoeren': de applicatiebeheerder meldt hier de aankondiging
            // van zijn leverancier aan (implementatie/15 §5).
            new NavigatieItem('Wijzigingsbeheer', 'wijzigingen.index', 'wijzigingsbeheer', 'arrow-path', 'uitvoeren'),
            // Op 'lezen', zodat ook het (tijdelijke) Auditor-account de audits ziet.
            new NavigatieItem('Audits', 'audits.index', 'auditmanagement', 'clipboard-document-list'),
            new NavigatieItem('Management review', 'management-review.index', 'management-review-verbetercyclus', 'presentation-chart-line'),
            // Read-only meetaanpak (§9.1): zelfde lees-gate als de review, want de
            // meting voedt de review (§9.3). Volwaardig dashboard = blok 4.10.
            new NavigatieItem("KPI's", 'meetaanpak.index', 'management-review-verbetercyclus', 'chart-bar'),
            // Op 'uitvoeren': de Medewerker ziet en registreert hier zijn eigen
            // trainingen (mijn-trainingen). Programmabeheer/toetsen zitten achter
            // 'muteren' en hebben geen eigen menu-item.
            new NavigatieItem('Bewustzijn & training', 'mijn-trainingen.index', 'bewustzijn-training', 'academic-cap', 'uitvoeren'),
            // Ook op 'uitvoeren': de Medewerker werkt hier zijn eigen taken af.
            new NavigatieItem('Taken', 'taken.index', 'taken-workflow-engine', 'clipboard-document-check', 'uitvoeren'),
            new NavigatieItem('Notificaties & integraties', 'notificaties.index', 'notificatie-integratielaag', 'bell-alert'),
            // Onderaan, want voor iedereen behalve de Administrator is dit item
            // onzichtbaar — en die heeft er verder geen enkel ander (01e §2.5).
            new NavigatieItem('Toetsbestanden', 'beheer.toetsen', 'installatiebeheer', 'document-arrow-up', 'muteren'),
            new NavigatieItem('Export', 'beheer.export', 'installatiebeheer', 'arrow-down-tray', 'muteren'),
        ];

        return array_values(array_filter(
            $items,
            // Een item verschijnt pas zodra dat blok gebouwd is én de gebruiker
            // het vereiste niveau erop heeft (meestal 'lezen', zie NavigatieItem).
            fn (NavigatieItem $item) => Route::has($item->route)
                && Gate::allows('heeft-niveau', [$item->blokCode, $item->niveau])
        ));
    }

    /**
     * Waar een gebruiker na het inloggen terechtkomt.
     *
     * Vrijwel altijd het dashboard, en dat blijft zo: dat scherm heeft bewust
     * geen autorisatiecheck op blokniveau, want elk paneel checkt zijn eigen
     * blok (routes/web.php). Wie geen énkel ISMS-blok heeft, krijgt daar dus een
     * leeg scherm — de Administrator is de eerste rol waarvoor dat geldt
     * (implementatie/01e §2.3).
     *
     * De terugval is het eerste item dat hij wél heeft, en niet een verwijzing
     * naar `beheer.toetsen`: dan lost het zich ook op voor een volgende rol die
     * buiten het ISMS staat.
     */
    public static function startroute(): string
    {
        foreach (self::ISMS_BLOKKEN as $blokCode) {
            if (Gate::allows('heeft-niveau', [$blokCode, 'lezen'])) {
                return 'dashboard';
            }
        }

        $items = self::zichtbareItems();

        return $items[0]->route ?? 'dashboard';
    }

    /**
     * De blokken waarvan het dashboard panelen toont. Wie hier op geen enkele
     * een leesrecht heeft, ziet daar niets.
     *
     * Losse lijst en niet afgeleid uit `zichtbareItems()`: een menu-item en een
     * dashboardpaneel zijn niet hetzelfde. `installatiebeheer` heeft wél een
     * menu-item en géén paneel, en dat is precies het geval dat deze lijst moet
     * onderscheiden.
     */
    private const ISMS_BLOKKEN = [
        'identity-access',
        'context-scope',
        'asset-classificatie',
        'risico-soa',
        'bewijsrepository-audit-trail',
        'taken-workflow-engine',
        'beleid-maatregelbeheer',
        'incident-afwijkingenbeheer',
        'leveranciers-derdenrisico',
        'bewustzijn-training',
        'auditmanagement',
        'management-review-verbetercyclus',
        'notificatie-integratielaag',
    ];
}
