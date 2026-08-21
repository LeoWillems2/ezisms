<?php

use App\Http\Controllers\DownloadBewijsstuk;
use App\Http\Controllers\DownloadKennisartikel;
use App\Http\Controllers\ToetsCallbackController;
use App\Http\Controllers\ToonBewijsstukPreview;
use App\Http\Controllers\ToonToets;
use App\Http\Controllers\ToonToetsVoorbeeld;
use App\Livewire\AfwijkingDetail;
use App\Livewire\AfwijkingenOverzicht;
use App\Livewire\AssetDetail;
use App\Livewire\AssetsOverzicht;
use App\Livewire\AuditLogOverzicht;
use App\Livewire\AuditProgrammaBeheer;
use App\Livewire\AuditrondeDetail;
use App\Livewire\AuditsOverzicht;
use App\Livewire\BelanghebbendenOverzicht;
use App\Livewire\BeleidsdocumentDetail;
use App\Livewire\BeleidsdocumentenOverzicht;
use App\Livewire\BevestigAdreswijziging;
use App\Livewire\BevindingenOverzicht;
use App\Livewire\BewijsstukkenOverzicht;
use App\Livewire\Bouwhulp;
use App\Livewire\Dashboard;
use App\Livewire\Dekkingsmatrix;
use App\Livewire\DoelgroepenOverzicht;
use App\Livewire\ExportBeheer;
use App\Livewire\GebruikersOverzicht;
use App\Livewire\IncidentDetail;
use App\Livewire\IncidentenOverzicht;
use App\Livewire\IntegratieRegister;
use App\Livewire\IssuesOverzicht;
use App\Livewire\Kennisbank;
use App\Livewire\LeverancierDetail;
use App\Livewire\LeveranciersOverzicht;
use App\Livewire\ManagementReviewOverzicht;
use App\Livewire\MeetaanpakOverzicht;
use App\Livewire\MijnTrainingen;
use App\Livewire\NotificatieBeheer;
use App\Livewire\OrganisatieEenhedenOverzicht;
use App\Livewire\RestrisicoTrend;
use App\Livewire\ReviewsessieDetail;
use App\Livewire\RisicoCriteria;
use App\Livewire\RisicoDetail;
use App\Livewire\RisicoMatrix;
use App\Livewire\RisicosOverzicht;
use App\Livewire\SchermkopieenOverzicht;
use App\Livewire\ScopeBeheer;
use App\Livewire\SoaOverzicht;
use App\Livewire\SystemenOverzicht;
use App\Livewire\TaaksjablonenOverzicht;
use App\Livewire\TakenOverzicht;
use App\Livewire\ToetsbestandenBeheer;
use App\Livewire\ToetsenResultaten;
use App\Livewire\ToetsenUitzetten;
use App\Livewire\TrainingenOverzicht;
use App\Livewire\UitnodigingAccepteren;
use App\Livewire\WijzigingDetail;
use App\Livewire\WijzigingenOverzicht;
use App\Livewire\WijzigingssjablonenBeheer;
use App\Support\Navigatie;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Bijna altijd het dashboard. Wie geen enkel ISMS-blok heeft komt daar op een
// leeg scherm — Navigatie::startroute() stuurt hem naar wat hij wél heeft
// (implementatie/01e §2.3).
Route::get('/', function () {
    return redirect()->route(Navigatie::startroute());
})->name('home');

// Publiek, maar alleen bereikbaar met een geldige signed URL + token
// (implementatie/01-identity-access.md §6).
Route::get('uitnodiging/{gebruiker}/{token}', UitnodigingAccepteren::class)
    ->middleware('signed')
    ->name('uitnodiging.accepteren');

// Idem voor het bevestigen van een nieuw e-mailadres bij een actief account
// (implementatie/01h §7). Het scherm toont een knop; deze route muteert niets
// bij het openen, want linkscanners van mailfilters volgen hem.
Route::get('adreswijziging/{gebruiker}/{token}', BevestigAdreswijziging::class)
    ->middleware('signed')
    ->name('adreswijziging.bevestigen');

// Blok 10 (Bewustzijn & Toetsen): het terugmeldkanaal van een toets. Publiek,
// zonder sessie — de token is het bewijs. CSRF-uitgezonderd (bootstrap/app.php)
// en getthrottled tegen misbruik (implementatie/10 §7).
Route::post('toetsen/callback/{token}', ToetsCallbackController::class)
    ->middleware('throttle:60,1')
    ->name('toetsen.callback');

// De toets zelf. Ook publiek en om dezelfde reden: de deelnemer heeft geen
// account nodig, de token is het bewijs. Tot 01e was dit geen route maar een
// bestand in public/toetsen/ — het uitserveren door de applicatie is wat de
// CSP-sandbox mogelijk maakt (implementatie/01e §1.3).
Route::get('toetsen/tonen/{token}', ToonToets::class)
    ->middleware('throttle:60,1')
    ->name('toetsen.tonen');

// Geen blok-gate op het dashboard zelf: elk paneel checkt zijn eigen blok
// (implementatie/12c §2). Een Medewerker houdt zijn takenlijst over, een CISO
// ziet alles — met één gate hier zou de Medewerker het dashboard kwijt zijn.
Route::get('dashboard', Dashboard::class)
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Lijst tonen vereist 'lezen'; de knoppen binnen het component checken
    // zelf nogmaals op 'muteren' (conventies §4).
    // Let op de enkele quotes: zonder quotes zoekt de `can`-middleware de
    // argumenten op als routeparameters en geeft die als null door aan de Gate.
    Route::get('gebruikers', GebruikersOverzicht::class)
        ->middleware("can:heeft-niveau,'identity-access','lezen'")
        ->name('gebruikers.index');

    // Blok 2 (Context & Scope). Zelfde patroon: lezen op de pagina, de
    // muteer-acties in het component checken nogmaals op 'muteren'.
    Route::middleware("can:heeft-niveau,'context-scope','lezen'")->group(function () {
        Route::get('scope', ScopeBeheer::class)->name('scope.show');
        Route::get('organisatie-eenheden', OrganisatieEenhedenOverzicht::class)->name('organisatie-eenheden.index');
        Route::get('issues', IssuesOverzicht::class)->name('issues.index');
        Route::get('belanghebbenden', BelanghebbendenOverzicht::class)->name('belanghebbenden.index');
    });

    // Blok 3 (Asset & Classificatie).
    Route::middleware("can:heeft-niveau,'asset-classificatie','lezen'")->group(function () {
        Route::get('assets', AssetsOverzicht::class)->name('assets.index');
        Route::get('assets/{asset}', AssetDetail::class)->name('assets.detail');
        Route::get('systemen', SystemenOverzicht::class)->name('systemen.index');
    });

    // Blok 4 (Risico & SoA). Medewerker heeft hier bewust geen rij in de
    // rechtenmatrix en dus geen toegang.
    Route::middleware("can:heeft-niveau,'risico-soa','lezen'")->group(function () {
        Route::get('soa', SoaOverzicht::class)->name('soa.index');
        Route::get('soa/restrisico-trend', RestrisicoTrend::class)->name('soa.restrisico-trend');
        Route::get('risicos', RisicosOverzicht::class)->name('risicos.index');
        // Vóór risicos/{risico}, anders vangt de route-model-binding het pad.
        Route::get('risicos/matrix', RisicoMatrix::class)->name('risicos.matrix');
        Route::get('risicos/criteria', RisicoCriteria::class)->name('risicos.criteria');
        Route::get('risicos/{risico}', RisicoDetail::class)->name('risicos.detail');
    });

    // Blok 6 (Bewijsrepository & Audit Trail). De bewijsstukkenlijst staat op
    // 'uitvoeren' in plaats van 'lezen': Medewerker mag eigen bewijs uploaden,
    // en ziet in het component alleen zijn eigen rijen (implementatie/06 §8).
    Route::get('bewijsstukken', BewijsstukkenOverzicht::class)
        ->middleware("can:heeft-niveau,'bewijsrepository-audit-trail','uitvoeren'")
        ->name('bewijsstukken.index');

    Route::middleware("can:heeft-niveau,'bewijsrepository-audit-trail','lezen'")->group(function () {
        // Geen directe URL naar de schijf: de download loopt door de Gate heen.
        Route::get('bewijsstukken/{bewijsstuk}/download', DownloadBewijsstuk::class)
            ->name('bewijsstukken.download');
        // HTML-preview van een RTF-bewijsstuk; dezelfde leespoort als de download.
        Route::get('bewijsstukken/{bewijsstuk}/preview', ToonBewijsstukPreview::class)
            ->name('bewijsstukken.preview');
        Route::get('audit-log', AuditLogOverzicht::class)->name('audit-log.index');
        // Wat er als schermkopie is meegegeven (implementatie/12h §9). Bij blok 6
        // en niet bij 12: het is een overdrachtsregister, geen meetwaarde.
        Route::get('schermkopieen', SchermkopieenOverzicht::class)->name('schermkopieen.index');
    });

    // Blok 7 (Taken & Workflow). Net als blok 6 op 'uitvoeren': de Medewerker
    // werkt hier zijn eigen taken af en ziet in het component alleen die rijen.
    Route::get('taken', TakenOverzicht::class)
        ->middleware("can:heeft-niveau,'taken-workflow-engine','uitvoeren'")
        ->name('taken.index');

    Route::get('taaksjablonen', TaaksjablonenOverzicht::class)
        ->middleware("can:heeft-niveau,'taken-workflow-engine','muteren'")
        ->name('taaksjablonen.index');

    // Blok 5 (Beleid & Maatregelbeheer). Op 'uitvoeren' omdat de Medewerker
    // hier leesbevestigingen afgeeft — een schrijfhandeling. Anders dan bij
    // blok 6 en 7 is inzage in alle actieve documenten juist de bedoeling; de
    // scoping zit alleen op het leesbevestigingsoverzicht en op concepten
    // (implementatie/05 §9).
    Route::middleware("can:heeft-niveau,'beleid-maatregelbeheer','uitvoeren'")->group(function () {
        Route::get('beleid', BeleidsdocumentenOverzicht::class)->name('beleid.index');
        Route::get('beleid/{beleidsdocument}', BeleidsdocumentDetail::class)->name('beleid.detail');
    });

    // Blok 8 (Incident- & Afwijkingenbeheer). Melden staat op 'uitvoeren':
    // iedereen moet een incident kunnen melden, en het component scopet daarna
    // op de eigen meldingen. Afwijkingen staan bewust hoger — de CAPA-cyclus is
    // geen medewerkerswerk (implementatie/08 §9).
    Route::middleware("can:heeft-niveau,'incident-afwijkingenbeheer','uitvoeren'")->group(function () {
        Route::get('incidenten', IncidentenOverzicht::class)->name('incidenten.index');
        Route::get('incidenten/{incident}', IncidentDetail::class)->name('incidenten.detail');
        // Het detailscherm zelf checkt fijnmaziger: een Medewerker die eigenaar
        // is van een maatregel mag die afmelden, de rest vraagt 'muteren'.
        Route::get('afwijkingen/{afwijking}', AfwijkingDetail::class)->name('afwijkingen.detail');
    });

    Route::get('afwijkingen', AfwijkingenOverzicht::class)
        ->middleware("can:heeft-niveau,'incident-afwijkingenbeheer','muteren'")
        ->name('afwijkingen.index');

    // Blok 9 (Leveranciers- & Derdenrisico). Geen record-scoping: wie 'lezen'
    // heeft ziet alles; de muteer-acties in de componenten checken nogmaals.
    Route::middleware("can:heeft-niveau,'leveranciers-derdenrisico','lezen'")->group(function () {
        Route::get('leveranciers', LeveranciersOverzicht::class)->name('leveranciers.index');
        Route::get('leveranciers/{leverancier}', LeverancierDetail::class)->name('leveranciers.detail');
    });

    // Blok 15 (Wijzigingsbeheer). Op 'lezen': anders dan bij taken is inzage in
    // álle wijzigingen juist de bedoeling — A.8.32 c) verplicht tot het
    // informeren van belanghebbenden (implementatie/15 §5). Aanmelden zit
    // achter 'uitvoeren' en wordt in het component gecontroleerd.
    Route::middleware("can:heeft-niveau,'wijzigingsbeheer','lezen'")->group(function () {
        Route::get('wijzigingen', WijzigingenOverzicht::class)->name('wijzigingen.index');
        Route::get('wijzigingen/{wijziging}', WijzigingDetail::class)->name('wijzigingen.detail');
    });

    Route::get('wijzigingssjablonen', WijzigingssjablonenBeheer::class)
        ->middleware("can:heeft-niveau,'wijzigingsbeheer','muteren'")
        ->name('wijzigingssjablonen.index');

    // Blok 10 (Bewustzijn, Training & Toetsen). Mijn-trainingen staat op
    // 'uitvoeren' (de Medewerker registreert eigen voltooiing); het
    // programmabeheer, doelgroepen en het uitzetten op 'muteren'. Het
    // resultatenoverzicht staat op 'lezen' maar sluit de Medewerker in het
    // component uit (uitvoeren impliceert lezen) — implementatie/10 §11.
    Route::get('mijn-trainingen', MijnTrainingen::class)
        ->middleware("can:heeft-niveau,'bewustzijn-training','uitvoeren'")
        ->name('mijn-trainingen.index');

    // Uitzetten is een pure muteer-actie (maakt taken aan): alleen CISO.
    Route::get('toetsen/uitzetten', ToetsenUitzetten::class)
        ->middleware("can:heeft-niveau,'bewustzijn-training','muteren'")
        ->name('toetsen.uitzetten');

    // Bouwhulp voor toetsmakers: uitleg + download van de onQuizVoltooid-functie.
    // Alleen CISO (muteren), want alleen die zet toetsen op.
    Route::get('toetsen/bouwhulp', Bouwhulp::class)
        ->middleware("can:heeft-niveau,'bewustzijn-training','muteren'")
        ->name('toetsen.bouwhulp');

    // Een toets bekijken zonder token, om hem te kunnen testen vóór het
    // uitzetten. Zelfde autorisatiecheck als de bouwhulp (implementatie/01e §1.3).
    Route::get('toetsen/voorbeeld/{bestand}', ToonToetsVoorbeeld::class)
        ->middleware("can:heeft-niveau,'bewustzijn-training','muteren'")
        ->name('toetsen.voorbeeld');

    Route::get('toetsen/bouwhulp/onquizvoltooid.js', function () {
        return response()->download(
            resource_path('toetsen/onQuizVoltooid.js'),
            'onQuizVoltooid.js',
            ['Content-Type' => 'text/javascript'],
        );
    })
        ->middleware("can:heeft-niveau,'bewustzijn-training','muteren'")
        ->name('toetsen.bouwhulp.download');

    // Het skelet: een werkende toets zonder één externe bron, als vertrekpunt
    // (implementatie/10b §4). Zelfde autorisatie als de helper hierboven.
    Route::get('toetsen/bouwhulp/skelet.html', function () {
        return response()->download(
            resource_path('toetsen/skelet.html'),
            'toets-skelet.html',
            ['Content-Type' => 'text/html'],
        );
    })
        ->middleware("can:heeft-niveau,'bewustzijn-training','muteren'")
        ->name('toetsen.bouwhulp.skelet');

    // Programmabeheer, doelgroepen en het resultatenoverzicht staan op 'lezen'
    // maar sluiten de Medewerker in het component uit via `magAllesZien`
    // (uitvoeren impliceert lezen). Zo is de Auditor read-only welkom en blijft
    // de Medewerker eruit — implementatie/10 §11/§13.
    Route::middleware("can:heeft-niveau,'bewustzijn-training','lezen'")->group(function () {
        Route::get('trainingen', TrainingenOverzicht::class)->name('trainingen.index');
        Route::get('doelgroepen', DoelgroepenOverzicht::class)->name('doelgroepen.index');
        Route::get('toetsen/resultaten', ToetsenResultaten::class)->name('toetsen.resultaten');
    });

    // Blok 11 (Auditmanagement). Op 'lezen' zodat ook de (tijdelijke) Auditor
    // erbij kan; de muteer- en record-guards checken in de componenten nogmaals.
    // Het schrijven van bevindingen loopt via de record-guard, niet via deze Gate
    // (implementatie/11 §4/§8).
    Route::middleware("can:heeft-niveau,'auditmanagement','lezen'")->group(function () {
        Route::get('audits', AuditsOverzicht::class)->name('audits.index');
        Route::get('audits/rondes/{auditronde}', AuditrondeDetail::class)->name('audits.ronde');
        // Het bevindingenregister over de rondes heen. Read-only en onder
        // dezelfde lees-gate; vastleggen/sluiten blijft in het rondedossier
        // achter de record-guard (§4).
        Route::get('audits/bevindingen', BevindingenOverzicht::class)->name('audits.bevindingen');
        // Plan 11b: het meerjarige auditprogramma en de dekkingsmatrix (§9.2.2).
        // Onder dezelfde lees-gate; de planning-acties checken 'muteren' in het
        // component. De matrix is read-only.
        Route::get('audits/programma', AuditProgrammaBeheer::class)->name('audits.programma');
        Route::get('audits/dekking', Dekkingsmatrix::class)->name('audits.dekking');
    });

    // Blok 13 (Management Review & Verbetercyclus). Op 'lezen'; de muteer-acties
    // checken in de componenten nogmaals. Geen record-scoping.
    Route::middleware("can:heeft-niveau,'management-review-verbetercyclus','lezen'")->group(function () {
        Route::get('management-review', ManagementReviewOverzicht::class)->name('management-review.index');
        Route::get('management-review/{reviewsessie}', ReviewsessieDetail::class)->name('management-review.detail');
        // Lichte read-only meetaanpak (§9.1): de KPI-catalogus + vastgelegde
        // meetpunten. Onder dezelfde lees-gate — de meting voedt de review
        // (§9.3). Het volwaardige dashboard/export is blok 4.10 (implementatie/12b).
        Route::get('meetaanpak', MeetaanpakOverzicht::class)->name('meetaanpak.index');
    });

    // Blok 14 (Notificatie & Integratielaag). Op 'lezen' zodat de Auditor de
    // configuratie en de gezondheidslog kan inzien; de muteer-acties checken in
    // de componenten nogmaals (implementatie/14 §8/§9).
    Route::middleware("can:heeft-niveau,'notificatie-integratielaag','lezen'")->group(function () {
        Route::get('notificaties', NotificatieBeheer::class)->name('notificaties.index');
        Route::get('integraties', IntegratieRegister::class)->name('integraties.index');
    });

    // Installatiebeheer (implementatie/01e). Geen ISMS-blok maar een
    // beheerdomein: alleen de Administrator heeft hier een rij, en die heeft
    // nergens anders er een. De prefix is Nederlands zoals de rest van de
    // adressen — /beheer/toetsen, geen /admin/upload-toets.
    Route::prefix('beheer')
        ->middleware("can:heeft-niveau,'installatiebeheer','muteren'")
        ->group(function () {
            Route::get('toetsen', ToetsbestandenBeheer::class)->name('beheer.toetsen');
            // Uitleveren is een handeling aan de installatie, niet een recht op
            // de inhoud: de Administrator start de export, maar leest hem niet
            // (implementatie/01e §3).
            Route::get('export', ExportBeheer::class)->name('beheer.export');
        });

    // Kennisbank: gecureerde ISMS-uitleg. Bewust géén blok-permissie — naslag
    // voor elke ingelogde gebruiker; onbekende slug geeft 404 in het component.
    Route::get('kennisbank/{slug?}', Kennisbank::class)->name('kennisbank');
    // Het artikel als bestand meenemen mag alleen de CISO. Lezen is naslag;
    // een download is meenemen, en dat is een andere handeling. De check zit
    // op de route én in de knop — de knop wegnemen is geen beveiliging.
    Route::get('kennisbank/{slug}/download', DownloadKennisartikel::class)
        ->middleware('can:kennisartikel-downloaden')
        ->name('kennisbank.download');

    // Eigen profiel: bewust géén blok-permissie, elke ingelogde gebruiker mag
    // zijn eigen gegevens zien en wachtwoord wijzigen (implementatie/05 §4).
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    // Tweefactor hoort in dezelfde groep: het zijn de eigen inloggegevens,
    // geen blok-permissie (implementatie/01d §6).
    Volt::route('settings/tweefactor', 'settings.tweefactor')->name('settings.tweefactor');
});

require __DIR__.'/auth.php';
