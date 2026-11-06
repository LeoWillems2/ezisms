<?php

namespace App\Providers;

use App\Listeners\RegistreerSchedulerHartslag;
use App\Listeners\RegistreerTweefactorGebeurtenis;
use App\Models\Afwijking;
use App\Models\Asset;
use App\Models\AssetToewijzing;
use App\Models\Auditplan;
use App\Models\Auditprogramma;
use App\Models\Auditronde;
use App\Models\Beleidsdocument;
use App\Models\Beleidsherziening;
use App\Models\Beleidsversie;
use App\Models\Beoordelingsniveau;
use App\Models\Beveiligingssignaal;
use App\Models\Bevinding;
use App\Models\BewijsKoppeling;
use App\Models\Bewijsstuk;
use App\Models\Contractclausule;
use App\Models\CorrigerendeMaatregel;
use App\Models\Dienst;
use App\Models\Doelgroep;
use App\Models\Effectiviteitstoets;
use App\Models\ExterneIdentiteit;
use App\Models\Gebruiker;
use App\Models\Grondoorzaak;
use App\Models\Incident;
use App\Models\IncidentMelding;
use App\Models\IntegratieAdapter;
use App\Models\KpiDefinitie;
use App\Models\Leesbevestiging;
use App\Models\Leverancier;
use App\Models\Leveranciersbeoordeling;
use App\Models\Notificatieregel;
use App\Models\OverheidsmaatregelBeoordeling;
use App\Models\RestrisicoSnapshot;
use App\Models\Reviewsessie;
use App\Models\Risico;
use App\Models\Risicobehandeling;
use App\Models\RisicocriteriaVersie;
use App\Models\RolPermissie;
use App\Models\RolToewijzing;
use App\Models\ScopeVerklaring;
use App\Models\Sjabloonstap;
use App\Models\SoaRegel;
use App\Models\Systeem;
use App\Models\Taak;
use App\Models\Taaksjabloon;
use App\Models\Toetsopdracht;
use App\Models\Trainingsmodule;
use App\Models\Trainingsvoltooiing;
use App\Models\Uitsluiting;
use App\Models\Verbeteractie;
use App\Models\Wijziging;
use App\Models\Wijzigingssjabloon;
use App\Support\Assistent\Aanroepcontext;
use App\Support\Assistent\Koppelingen;
use App\Support\Autorisatiegeheugen;
use App\Support\Ketenhash;
use App\Support\Normlabels;
use App\Support\Pandoc;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Pandoc met het geconfigureerde pad; als singleton zodat een test hem
        // via de container kan vervangen door een dubbel (geen echte binary).
        $this->app->singleton(Pandoc::class, fn () => new Pandoc(
            config('services.pandoc.bin', 'pandoc')
        ));

        // Scoped: per request weggegooid, zodat het geheugen nooit een request
        // overleeft. Zie de klassenkop van Autorisatiegeheugen.
        $this->app->scoped(Autorisatiegeheugen::class);

        // Wat een MCP-tool over zijn eigen aanroep meldt, voor de aanroeplog
        // (implementatie/14b §7.1). Per verzoek, net als hierboven.
        $this->app->scoped(Aanroepcontext::class);

        // De Passport-routes registreert routes/ai.php zelf, en alleen als de
        // assistenttoegang aan staat (14b §3.1).
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Minimaal 12 tekens, geen verplichte samenstelling van hoofdletters/
        // cijfers/symbolen (implementatie/01-identity-access.md §9). Lengte doet
        // meer tegen raden dan verplichte tekensoorten, en die laatste leveren
        // vooral Wachtwoord2026! op — één patroon dat elke aanvaller kent.
        //
        // Eén plek: elk scherm dat een wachtwoord aanneemt gebruikt
        // `Password::defaults()`, zodat een volgende wijziging hier gebeurt en
        // niet op vier plaatsen half.
        Password::defaults(fn () => Password::min(12));

        // Fortify levert alleen de 2FA-machinerie (implementatie/01d §2). Zijn
        // routes gaan uit: de loginroute in `routes/auth.php` blijft de enige,
        // en een tweede weg naar hetzelfde scherm is een tweede plek waar de
        // statuscontrole vergeten kan worden.
        Fortify::ignoreRoutes();

        // Vier gebeurtenissen, één listener — dus expliciet en niet via
        // event-discovery, want die koppelt op het type in de handtekening.
        Event::listen(
            RegistreerTweefactorGebeurtenis::gebeurtenissen(),
            RegistreerTweefactorGebeurtenis::class,
        );

        // Drie gebeurtenissen, één listener — zelfde reden als hierboven
        // (implementatie/00m §0.2).
        Event::listen(
            RegistreerSchedulerHartslag::gebeurtenissen(),
            RegistreerSchedulerHartslag::class,
        );

        $this->registreerMorphMap();
        $this->registreerAutorisatie();
        $this->registreerGeauditeerdeMassaUpdate();
        $this->registreerNormlabels();
        $this->registreerLokaleTijd();
        $this->registreerAssistenttoegang();
    }

    /**
     * Passport en de limieten voor de assistenttoegang (implementatie/14b §3).
     * De routes staan in routes/ai.php; dit is wat daar niet kan.
     */
    private function registreerAssistenttoegang(): void
    {
        Passport::tokensExpireIn(now()->addMinutes((int) config('assistent.toegangstoken_minuten', 60)));
        Passport::refreshTokensExpireIn(now()->addDays((int) config('assistent.verversingstoken_dagen', 30)));

        if (filled(config('assistent.sleutelmap'))) {
            Passport::loadKeysFrom((string) config('assistent.sleutelmap'));
        }

        // Ons toestemmingsscherm in plaats van dat van Passport (14b §3.3).
        Passport::authorizationView('assistent.toestemming');

        // Per gebruiker en niet per IP: connectoren van een AI-dienst delen
        // adressen met andere klanten (14b §10.2).
        RateLimiter::for('assistent', fn (Request $request) => Limit::perMinute(
            (int) config('assistent.aanroepen_per_minuut', 120)
        )->by('gebruiker:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Anoniem, dus wel per IP.
        RateLimiter::for('assistent-registratie', fn (Request $request) => Limit::perHour(
            (int) config('assistent.registraties_per_uur', 10)
        )->by($request->ip()));

        RateLimiter::for('assistent-token', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Koppelingen intrekken zodra iemand ze niet meer zou mogen maken
        // (14b §3.5). De middleware van /mcp blokkeert al per verzoek; dit
        // ruimt de tokens op, zodat ze bij een terugdraaiing niet vanzelf
        // weer werken.
        Gebruiker::updated(function (Gebruiker $gebruiker) {
            if ($gebruiker->wasChanged('status') && ! $gebruiker->magInloggen()) {
                Koppelingen::trekIn($gebruiker, reden: 'account '.$gebruiker->status);
            }
        });
        RolToewijzing::deleted(fn () => Koppelingen::ruimOpVoorWieNietMeerMag());
        RolPermissie::deleted(fn () => Koppelingen::ruimOpVoorWieNietMeerMag());
        RolPermissie::updated(fn () => Koppelingen::ruimOpVoorWieNietMeerMag());
    }

    /**
     * `->lokaal()` op elk tijdstip dat aan een mens getoond wordt
     * (implementatie/00o §2).
     *
     * De applicatie draait en slaat op in UTC; op een Nederlandse server staat
     * de kolom daarmee twee uur (zomer) of één uur (winter) achter de klok aan
     * de muur. Zonder omzetting leest dat als lokale tijd — voor een audit trail
     * de gevaarlijke soort fout: wie een regel naast een mail of een ticket legt
     * zit ernaast, zonder enige aanwijzing dát hij ernaast zit.
     *
     * Omzetten bij het tónen en niet bij het opslaan: `tijdstip` is het eerste
     * veld in {@see Ketenhash} en de trail is over de opgeslagen
     * waarden gehasht. Zie `config/tijd.php`.
     *
     * `copy()` is niet optioneel — `setTimezone()` op een mutable Carbon wijzigt
     * het object zelf, en dat object is het gecachete attribuut van het model.
     * Zonder de kopie verschuift een tweede aanroep in dezelfde request opnieuw.
     */
    private function registreerLokaleTijd(): void
    {
        $lokaal = function () {
            /** @var CarbonInterface $this */
            return $this->copy()->setTimezone(config('tijd.weergave'));
        };

        // Op beide klassen: een modelcast levert een mutable Carbon, de
        // demoklok en enkele support-klassen werken met de immutable variant.
        Carbon::macro('lokaal', $lokaal);
        CarbonImmutable::macro('lokaal', $lokaal);
    }

    /**
     * `$norm->bijlage` in elke view — implementatie/00h §3.
     *
     * Eén object en geen Blade-directive, omdat een directive niet compileert
     * binnen component-attributen (`label="…"` van een Flux-component is een
     * stringliteral). Zie {@see Normlabels}.
     */
    private function registreerNormlabels(): void
    {
        View::share('norm', new Normlabels);
    }

    /**
     * `Model::where(...)->update([...])` gaat rechtstreeks naar de database en
     * vuurt géén Eloquent-events. Op een model met de `Auditeerbaar`-trait
     * betekent dat: de wijziging gebeurt wél, maar komt niet in de audit trail.
     * Dat is stil en daarom gevaarlijk: bij de retrofit van blok 6 keken we
     * naar de modellen, niet naar wat eromheen schrijft, waardoor drie
     * bestaande massa-updates buiten de audit trail vielen.
     *
     * `updateGeaudit()` leest bijna hetzelfde als `update()` maar loopt de
     * modellen langs, zodat observers en de audit trail wél aan bod komen.
     * Gebruik dit overal waar het model auditeerbaar is.
     */
    private function registreerGeauditeerdeMassaUpdate(): void
    {
        Builder::macro('updateGeaudit', function (array $attributen): int {
            $aantal = 0;

            // Bewust ->get() en niet ->each()/chunk(): die pagineren tijdens het
            // muteren, en juist bij een filter op de kolom die je wijzigt
            // (status = 'actief' -> 'gedeactiveerd') slaat dat rijen over.
            /** @var Builder $this */
            foreach ($this->get() as $model) {
                if ($model->update($attributen)) {
                    $aantal++;
                }
            }

            return $aantal;
        });

        // Zelfde verhaal voor verwijderen: ->delete() op de query builder gaat
        // om de model-events heen.
        Builder::macro('deleteGeaudit', function (): int {
            $aantal = 0;

            /** @var Builder $this */
            foreach ($this->get() as $model) {
                if ($model->delete()) {
                    $aantal++;
                }
            }

            return $aantal;
        });
    }

    /**
     * De niveaus vormen een oplopende ladder: een hoger niveau impliceert alle
     * lagere. Dat is de bedoeling achter de rechtenmatrix, die per (rol, blok)
     * meestal één niveau geeft — de CISO krijgt `muteren` en kan daarmee ook
     * lezen, zonder dat er een aparte `lezen`-rij bij hoeft.
     *
     * @var list<string>
     */
    private const NIVEAU_LADDER = ['lezen', 'uitvoeren', 'muteren'];

    /**
     * Niveaus die bewust BUITEN de ladder staan. Ze zijn geen "meer dan
     * muteren" maar een andere sóórt bevoegdheid, en impliceren alleen lezen:
     * wie ermee werkt moet per definitie kunnen inzien.
     *
     * - `exporteren` (implementatie/06 §8): data naar buiten brengen. In de
     *   ladder zou het de Auditor — de rol die per definitie onafhankelijk moet
     *   zijn — muteer- en goedkeurrechten geven.
     * - `goedkeuren` (implementatie/01c): vaststellen. Stond tot 29-07-2026
     *   bovenaan de ladder, waardoor "goedkeuren maar niet bewerken" niet uit
     *   te drukken was en de rol Management het hele register had kunnen
     *   herschrijven. Vaststellen is een andere bevoegdheid dan bewerken, geen
     *   grotere hoeveelheid ervan.
     *
     * @var array<string, list<string>>
     */
    private const LOSSE_NIVEAUS = [
        'exporteren' => ['lezen'],
        'goedkeuren' => ['lezen'],
    ];

    /**
     * Eén generieke ability voor alle blokken — rechten zijn data
     * (rijen in rol_permissies), geen hardcoded Policy-classes.
     * Zie implementatie/00-stack-en-conventies.md §4 en 01-identity-access.md §5.
     */
    private function registreerAutorisatie(): void
    {
        Gate::define('heeft-niveau', function (Gebruiker $gebruiker, string $blokCode, string $niveau): bool {
            // Lijstschermen stellen deze vraag per rij; het antwoord verandert
            // binnen een request niet (zie Autorisatiegeheugen).
            return $this->app->make(Autorisatiegeheugen::class)->onthoud(
                "{$gebruiker->id}|{$blokCode}|{$niveau}",
                function () use ($gebruiker, $blokCode, $niveau): bool {
                    $voldoendeNiveaus = self::voldoendeNiveaus($niveau);

                    if ($voldoendeNiveaus === []) {
                        return false; // onbekend niveau: nooit toestaan
                    }

                    return RolPermissie::query()
                        ->whereIn('rol_id', $gebruiker->rollen->pluck('id'))
                        ->whereIn('niveau', $voldoendeNiveaus)
                        ->whereHas('blok', fn ($q) => $q->where('code', $blokCode))
                        ->exists();
                },
            );
        });

        // De enige autorisatiecheck op een rolnáám, en dat is met opzet zo smal
        // mogelijk gehouden: hij dekt uitsluitend het downloaden van een
        // kennisartikel. De kennisbank staat buiten het blokkenmodel (naslag
        // voor iedereen), dus er is geen (blok, niveau) om op te toetsen — en
        // de vraag is hier ook niet "wie mag dit domein muteren" maar "wie is
        // de ISMS-eigenaar". Hij hoort hier en niet in een view, zodat de
        // rolnaam op één plek staat en de blades bij `@can` blijven.
        Gate::define('kennisartikel-downloaden', fn (Gebruiker $gebruiker): bool => $gebruiker->heeftRol('CISO'));
    }

    /**
     * Welke toegekende niveaus voldoen aan een gevraagd niveau: het gevraagde
     * zelf, alles erboven in de ladder, en elk los niveau dat het impliceert.
     *
     * @return list<string>
     */
    private static function voldoendeNiveaus(string $gevraagd): array
    {
        $rang = array_search($gevraagd, self::NIVEAU_LADDER, true);

        $voldoende = $rang === false ? [] : array_slice(self::NIVEAU_LADDER, $rang);

        foreach (self::LOSSE_NIVEAUS as $los => $impliceert) {
            if ($los === $gevraagd || in_array($gevraagd, $impliceert, true)) {
                $voldoende[] = $los;
            }
        }

        return array_values(array_unique($voldoende));
    }

    /**
     * Morph-aliassen voor de generieke koppelingen van blok 6
     * (implementatie/06 §4). Aliassen i.p.v. classnamen: een FQCN in de
     * database breekt bij een namespace-refactor, en een auditor die de tabel
     * leest hoort geen PHP-paden te zien.
     *
     * `enforceMorphMap` (niet `morphMap`) gooit bij een niet-gemapt model een
     * exception — zo word je bij een nieuw blok gedwongen een alias te kiezen.
     */
    private function registreerMorphMap(): void
    {
        Relation::enforceMorphMap([
            'gebruiker' => Gebruiker::class,
            'rol_toewijzing' => RolToewijzing::class,
            // De rechtenmatrix (implementatie/14b §8.1).
            'rol_permissie' => RolPermissie::class,
            // Koppeling met de externe identiteitsprovider (01j §2).
            'externe_identiteit' => ExterneIdentiteit::class,
            'scope_verklaring' => ScopeVerklaring::class,
            'uitsluiting' => Uitsluiting::class,
            'asset' => Asset::class,
            'asset_toewijzing' => AssetToewijzing::class,
            'systeem' => Systeem::class,
            'soa_regel' => SoaRegel::class,
            // De BIO-verplichting onder een beheersmaatregel (blok 4b). Alleen
            // de beoordeling staat hier: `Overheidsmaatregel` zelf is
            // referentiedata en niet auditeerbaar, dus die heeft geen alias
            // nodig.
            'overheidsmaatregel_beoordeling' => OverheidsmaatregelBeoordeling::class,
            'risico' => Risico::class,
            // De alias `risicoacceptatiecriterium` is met 04g vervallen. Hij
            // hoeft niet resolvebaar te blijven: `audit_logregels` bewaart
            // `entiteit_type` als tekst plus een gedenormaliseerde omschrijving
            // en heeft nergens een `morphTo`, dus de historische regels blijven
            // gewoon leesbaar.
            'risicocriteria_versie' => RisicocriteriaVersie::class,
            'beoordelingsniveau' => Beoordelingsniveau::class,
            'risicobehandeling' => Risicobehandeling::class,
            'restrisico_snapshot' => RestrisicoSnapshot::class,
            'bewijsstuk' => Bewijsstuk::class,
            'bewijs_koppeling' => BewijsKoppeling::class,
            'taak' => Taak::class,
            'taaksjabloon' => Taaksjabloon::class,
            'beleidsdocument' => Beleidsdocument::class,
            'beleidsversie' => Beleidsversie::class,
            'leesbevestiging' => Leesbevestiging::class,
            'beleidsherziening' => Beleidsherziening::class,
            'incident' => Incident::class,
            'incident_melding' => IncidentMelding::class,
            'afwijking' => Afwijking::class,
            'grondoorzaak' => Grondoorzaak::class,
            'corrigerende_maatregel' => CorrigerendeMaatregel::class,
            'effectiviteitstoets' => Effectiviteitstoets::class,
            'leverancier' => Leverancier::class,
            'dienst' => Dienst::class,
            'leveranciersbeoordeling' => Leveranciersbeoordeling::class,
            'contractclausule' => Contractclausule::class,
            'trainingsmodule' => Trainingsmodule::class,
            'doelgroep' => Doelgroep::class,
            'trainingsvoltooiing' => Trainingsvoltooiing::class,
            'toetsopdracht' => Toetsopdracht::class,
            'auditplan' => Auditplan::class,
            'auditronde' => Auditronde::class,
            'bevinding' => Bevinding::class,
            'auditprogramma' => Auditprogramma::class,
            'reviewsessie' => Reviewsessie::class,
            'verbeteractie' => Verbeteractie::class,
            // Sinds 12e beheert de CISO de meetaanpak zelf; "wie heeft die
            // streefwaarde verlaagd, en wanneer" is precies één auditorvraag.
            'kpi_definitie' => KpiDefinitie::class,
            'notificatieregel' => Notificatieregel::class,
            'integratie_adapter' => IntegratieAdapter::class,
            // Blok 15. Ook de sjablonen: het zijn geen registerrijen maar wel
            // configuratie die de compliance-uitkomst bepaalt, en dus
            // auditeerbaar — zelfde afweging als bij de risicocriteria (04g).
            'wijziging' => Wijziging::class,
            'wijzigingssjabloon' => Wijzigingssjabloon::class,
            'sjabloonstap' => Sjabloonstap::class,
            // 01l: een signaal van de bewaking op inloggegevens. Auditeerbaar,
            // want de trailregel is het bewijs dat er gemonitord is.
            'beveiligingssignaal' => Beveiligingssignaal::class,
        ]);
    }
}
