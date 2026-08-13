<?php

namespace App\Livewire;

use App\Mail\AdreswijzigingAangevraagd;
use App\Mail\AdreswijzigingBevestigen;
use App\Mail\GebruikerUitgenodigd;
use App\Models\BewijsKoppeling;
use App\Models\Gebruiker;
use App\Models\OrganisatieEenheid;
use App\Models\Rol;
use App\Support\Domeincontrole;
use App\Support\Rolregels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class GebruikersOverzicht extends Component
{
    public bool $toontUitnodigingsformulier = false;

    public string $naam = '';

    public string $email = '';

    public string $rolId = '';

    public string $afdelingId = '';

    public ?string $vervaltOp = null;

    // Personeelsdossier-modal (A.6): pre-employment + offboarding.
    public bool $toontDossier = false;

    public ?int $dossierGebruikerId = null;

    public ?string $ndaGetekendOp = null;

    public string $screeningType = '';

    public ?string $screeningOp = null;

    public ?string $accountsIngetrokkenOp = null;

    // Blokkade-modal (01f). Een `wire:confirm` volstaat hier niet: die levert
    // alleen ja/nee, en de reden is verplicht.
    public bool $toontBlokkadeformulier = false;

    public ?int $blokkadeGebruikerId = null;

    public string $blokkadeReden = '';

    // Correctie-modal (01g). Een modal en geen `wire:confirm` om twee redenen:
    // er zijn twee invoervelden nodig, en het neveneffect — de oude link sterft
    // — is precies wat de CISO wil weten en niet vanzelf begrijpt.
    public bool $toontCorrectieformulier = false;

    public ?int $correctieGebruikerId = null;

    public string $correctieNaam = '';

    public string $correctieEmail = '';

    // Adreswijziging bij een actief account (01h). Bewust een aparte modal en
    // niet de correctiemodal met een extra tak: de twee handelingen doen het
    // tegenovergestelde en delen alleen het invoerveld.
    public bool $toontAdreswijziging = false;

    public ?int $adreswijzigingGebruikerId = null;

    public string $adreswijzigingEmail = '';

    /**
     * Elke actiemethode herhaalt deze check ondanks de route-middleware: de
     * pagina is bereikbaar met `lezen`, maar de knoppen mogen alleen iets doen
     * met `muteren` (implementatie/00-stack-en-conventies.md §4).
     */
    private function vereisMuteren(): void
    {
        abort_unless(Gate::allows('heeft-niveau', ['identity-access', 'muteren']), 403);
    }

    public function magMuteren(): bool
    {
        return Gate::allows('heeft-niveau', ['identity-access', 'muteren']);
    }

    /**
     * Staat de keuzelijst op de rol die met geen enkele andere samengaat?
     *
     * Alleen om het in het formulier te kunnen zeggen. Een validatieregel zou
     * hier dode code zijn: `uitnodigen()` maakt altijd een níeuwe gebruiker en
     * kent precies één rol toe, dus de verboden combinatie is via dit scherm
     * niet te maken. Het slot dat er wél toe doet, zit op `RolToewijzing` en
     * geldt voor élk pad (implementatie/01e §2.4).
     */
    public function kiestExclusieveRol(): bool
    {
        return $this->rolId !== ''
            && Rol::whereKey((int) $this->rolId)->value('naam') === Rolregels::EXCLUSIEF;
    }

    public function openUitnodigingsformulier(): void
    {
        $this->vereisMuteren();
        $this->reset(['naam', 'email', 'rolId', 'afdelingId', 'vervaltOp']);
        $this->resetValidation();
        $this->toontUitnodigingsformulier = true;
    }

    public function sluitUitnodigingsformulier(): void
    {
        $this->toontUitnodigingsformulier = false;
    }

    public function uitnodigen(): void
    {
        $this->vereisMuteren();

        // Normaliseren vóór de validatie en niet erna (01g §6). De unique-regel
        // hieronder hoort over het genormaliseerde adres te gaan, anders kunnen
        // `Jan.Jansen@…` en `jan.jansen@…` alsnog naast elkaar bestaan — precies
        // wat die paragraaf wil voorkomen. Een meegeplakte spatie levert anders
        // een adres op dat er goed uitziet en niet werkt.
        $this->email = Str::lower(trim($this->email));

        $this->validate([
            'naam' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('gebruikers', 'email')],
            'rolId' => ['required', Rule::exists('rollen', 'id')],
            'afdelingId' => ['nullable', Rule::exists('organisatie_eenheden', 'id')->where('type', OrganisatieEenheid::TYPE_AFDELING)],
            'vervaltOp' => ['nullable', 'date', 'after:today'],
        ], attributes: [
            'naam' => 'naam',
            'email' => 'e-mailadres',
            'rolId' => 'rol',
            'afdelingId' => 'afdeling',
            'vervaltOp' => 'vervaldatum',
        ]);

        $gebruiker = Gebruiker::create([
            'naam' => $this->naam,
            'email' => $this->email,
            // Tijdelijk wachtwoord: onbruikbaar om mee in te loggen en wordt
            // overschreven zodra de uitnodiging geaccepteerd wordt.
            'wachtwoord' => Str::random(32),
            'status' => 'uitgenodigd',
            'organisatie_eenheid_id' => $this->afdelingId !== '' ? (int) $this->afdelingId : null,
            'vervalt_op' => $this->vervaltOp,
        ]);

        $gebruiker->rolToewijzingen()->create([
            'rol_id' => (int) $this->rolId,
            'toegekend_door_id' => auth()->id(),
            'toegekend_op' => now(),
        ]);

        $this->verstuurUitnodiging($gebruiker);

        $this->toontUitnodigingsformulier = false;
        $this->reset(['naam', 'email', 'rolId', 'afdelingId', 'vervaltOp']);
    }

    /**
     * Wijzigt de afdeling van een bestaande gebruiker vanuit de lijst. Een lege
     * keuze haalt de afdeling weg (bijv. bij een externe). De wijziging valt
     * onder de audit trail via het model (identity-access).
     */
    public function stelAfdelingIn(Gebruiker $gebruiker, ?string $afdelingId): void
    {
        $this->vereisMuteren();

        $id = $afdelingId !== null && $afdelingId !== '' ? (int) $afdelingId : null;

        // Alleen een échte afdeling accepteren; een locatie of proces is geen
        // doelgroep. Een ongeldige keuze negeren we stil — de lijst rendert
        // daarna gewoon de bestaande waarde.
        if ($id !== null && ! OrganisatieEenheid::afdelingen()->whereKey($id)->exists()) {
            return;
        }

        $gebruiker->update(['organisatie_eenheid_id' => $id]);
        session()->flash('melding', "Afdeling van {$gebruiker->naam} bijgewerkt.");
    }

    public function uitnodigingOpnieuwVersturen(Gebruiker $gebruiker): void
    {
        $this->vereisMuteren();

        if ($gebruiker->status !== 'uitgenodigd') {
            return;
        }

        $this->verstuurUitnodiging($gebruiker);
    }

    /**
     * Een typefout in naam of adres herstellen zolang het account nog niet in
     * gebruik is (01g).
     *
     * Alleen bij `uitgenodigd`, en dat is geen beperking maar de definitie: was
     * het adres fout, dan heeft de bedoelde persoon nooit kunnen accepteren, dus
     * staat het account per definitie nog open. Staat het op `actief`, dan heeft
     * iemand met dát adres een wachtwoord ingesteld en is het geen invulfout
     * meer maar toegang die ingetrokken moet worden — de weg is dan
     * **Blokkeren** (01g §0/§8). De gevaarlijke situatie kan deze knop dus niet
     * bereiken omdat de knop daar niet staat; dat is sterker dan een
     * waarschuwingstekst.
     */
    public function openCorrectieformulier(Gebruiker $gebruiker): void
    {
        $this->vereisMuteren();

        if ($gebruiker->status !== 'uitgenodigd') {
            return;
        }

        $this->correctieGebruikerId = $gebruiker->id;
        $this->correctieNaam = $gebruiker->naam;
        $this->correctieEmail = $gebruiker->email;
        $this->resetValidation();
        $this->toontCorrectieformulier = true;
    }

    public function corrigeren(): void
    {
        $this->vereisMuteren();

        // `openCorrectieformulier()` keert zonder id terug als het account niet
        // meer `uitgenodigd` staat. Zonder deze regel liep `findOrFail(null)`
        // daarna op een ModelNotFoundException — een 500 op een pad dat gewoon
        // niets hoort te doen.
        if ($this->correctieGebruikerId === null) {
            $this->toontCorrectieformulier = false;

            return;
        }

        $this->correctieEmail = Str::lower(trim($this->correctieEmail));

        $this->validate([
            'correctieNaam' => ['required', 'string', 'max:255'],
            'correctieEmail' => ['required', 'email', 'max:255'],
        ], attributes: ['correctieNaam' => 'naam', 'correctieEmail' => 'e-mailadres']);

        $gebruiker = Gebruiker::findOrFail($this->correctieGebruikerId);

        // Nog een keer, net als bij blokkeren: tussen het openen van de modal en
        // het opslaan zit een tweede verzoek, en in die tijd kan de uitnodiging
        // geaccepteerd zijn. Juist dán mag deze knop niets meer doen — het
        // account is dan van iemand.
        if ($gebruiker->status !== 'uitgenodigd') {
            $this->toontCorrectieformulier = false;
            session()->flash('fout', 'Deze uitnodiging is inmiddels geaccepteerd; corrigeren kan niet meer.');

            return;
        }

        if (! $this->adresIsVrij($this->correctieEmail, $gebruiker)) {
            return;
        }

        $this->toontCorrectieformulier = false;

        // Niets veranderd: dan alleen opnieuw versturen. Zonder deze afslag
        // roteert de link wél maar levert de update geen auditregel op — er
        // blijft na het filteren van `wachtwoord` niets over — en dat is precies
        // de combinatie die je niet wilt: een ingetrokken link zonder spoor.
        if ($gebruiker->naam === $this->correctieNaam && $gebruiker->email === $this->correctieEmail) {
            $this->verstuurUitnodiging($gebruiker);

            return;
        }

        $gebruiker->corrigeerUitnodiging($this->correctieNaam, $this->correctieEmail);

        // `fresh()`: de link wordt uit de nieuwe wachtwoord-hash afgeleid, en op
        // het meegegeven model staat nog de oude.
        $this->verstuurUitnodiging($gebruiker->fresh());
    }

    /**
     * De unique-index loopt over de hele tabel, inclusief gedeactiveerde en
     * geblokkeerde accounts. Een kale `Rule::unique(...)` levert "dit adres is al
     * in gebruik" op en stuurt de CISO op zoek; deze controle zegt bij wie
     * (01g §7).
     *
     * Het adres wordt niet vrijgemaakt, ook niet als dat andere account
     * gedeactiveerd is: de audit trail van dat account hangt aan die identiteit,
     * en het adres is vaak nog een echte mailbox. De weg vooruit is een gesprek,
     * geen knop. De unique-index blijft het echte slot; dit is de leesbare
     * melding ervoor.
     */
    private function adresIsVrij(string $email, Gebruiker $gebruiker, string $veld = 'correctieEmail'): bool
    {
        $bezet = Gebruiker::where('email', $email)->whereKeyNot($gebruiker->id)->first();

        if ($bezet === null) {
            return true;
        }

        $this->addError($veld, "Dit adres hoort bij het account van {$bezet->naam} ({$bezet->status}).");

        return false;
    }

    /**
     * Een adreswijziging aanvragen bij een account dat in gebruik is
     * (implementatie/01h §5).
     *
     * Alleen bij `actief`, en dat is de spiegel van `openCorrectieformulier()`:
     * die staat er alleen bij `uitgenodigd`. De twee handelingen lijken op elkaar
     * maar zijn tegengesteld — corrigeren wijzigt direct en trekt de
     * uitnodigingslink in, wijzigen laat alles staan tot het nieuwe adres
     * bevestigd is.
     */
    public function openAdreswijziging(Gebruiker $gebruiker): void
    {
        $this->vereisMuteren();

        if ($gebruiker->status !== 'actief') {
            return;
        }

        $this->adreswijzigingGebruikerId = $gebruiker->id;
        $this->adreswijzigingEmail = '';
        $this->resetValidation();
        $this->toontAdreswijziging = true;
    }

    public function wijzigAdres(): void
    {
        $this->vereisMuteren();

        // Zelfde guard als bij `corrigeren()`: `openAdreswijziging()` keert
        // zonder id terug als het account niet actief is, en `findOrFail(null)`
        // zou daarna een 500 opleveren op een pad dat niets hoort te doen.
        if ($this->adreswijzigingGebruikerId === null) {
            $this->toontAdreswijziging = false;

            return;
        }

        $this->adreswijzigingEmail = Str::lower(trim($this->adreswijzigingEmail));

        $this->validate([
            'adreswijzigingEmail' => ['required', 'email', 'max:255'],
        ], attributes: ['adreswijzigingEmail' => 'e-mailadres']);

        $gebruiker = Gebruiker::findOrFail($this->adreswijzigingGebruikerId);

        if ($gebruiker->status !== 'actief') {
            $this->toontAdreswijziging = false;
            session()->flash('fout', 'Dit account is niet meer actief; een adreswijziging heeft geen zin.');

            return;
        }

        // Niets te wijzigen. Geen aanvraag en geen post: anders krijgt iemand een
        // bevestigingsmail voor een adres dat hij al heeft.
        if ($gebruiker->email === $this->adreswijzigingEmail) {
            $this->toontAdreswijziging = false;
            session()->flash('melding', 'Dat is al het huidige adres; er is niets gewijzigd.');

            return;
        }

        if (! $this->adresIsVrij($this->adreswijzigingEmail, $gebruiker, 'adreswijzigingEmail')) {
            return;
        }

        $this->toontAdreswijziging = false;

        // Staat dit adres al open, dan alleen opnieuw versturen — géén nieuwe
        // aanvraag. Het token hangt aan het aanvraagmoment, dus een tweede
        // aanvraag voor hetzelfde adres zou de link doden die al onderweg is,
        // en dat is precies wat er op 13-08-2026 gebeurde: twee keer op de knop,
        // vier seconden ertussen, en de zojuist verstuurde link deed het niet
        // meer (§15.6). Herhalen heeft hier geen enkel voordeel.
        if ($gebruiker->nieuw_email === $this->adreswijzigingEmail) {
            $this->verstuurAdreswijziging($gebruiker, $gebruiker->email, herhaling: true);

            return;
        }

        // Het oude adres wordt vastgehouden vóór de aanvraag; daarna is het nog
        // steeds `email`, maar dit leest als wat het is.
        $oudAdres = $gebruiker->email;

        $gebruiker->vraagAdreswijzigingAan($this->adreswijzigingEmail);
        $gebruiker->refresh();

        $this->verstuurAdreswijziging($gebruiker, $oudAdres);
    }

    public function trekAdreswijzigingIn(Gebruiker $gebruiker): void
    {
        $this->vereisMuteren();

        if (! $gebruiker->adreswijzigingLoopt()) {
            return;
        }

        $gebruiker->trekAdreswijzigingIn();

        session()->flash('melding', "Adreswijziging voor {$gebruiker->naam} ingetrokken; de link werkt niet meer.");
    }

    /**
     * De twee berichten uit 01h §6: de link naar het nieuwe adres, een
     * waarschuwing naar het oude.
     *
     * Ze worden apart afgevangen, want ze mislukken om verschillende redenen en
     * met verschillende gevolgen. Komt het bericht naar het **oude** adres niet
     * aan, dan is dat vaak juist de aanleiding voor de wijziging — de aanvraag
     * blijft dan staan, maar de CISO hoort het te weten. Komt de **link** niet
     * aan, dan is er niets te bevestigen en heeft de aanvraag geen zin.
     */
    private function verstuurAdreswijziging(Gebruiker $gebruiker, string $oudAdres, bool $herhaling = false): void
    {
        $aanvrager = auth()->user()?->naam ?? 'de beheerder';

        try {
            Mail::to($oudAdres)->send(
                new AdreswijzigingAangevraagd($gebruiker, $gebruiker->nieuw_email, $aanvrager)
            );
        } catch (\Throwable $e) {
            Log::error('Melding adreswijziging naar oud adres mislukt', [
                'gebruiker_id' => $gebruiker->id, 'fout' => $e->getMessage(),
            ]);
            session()->flash('fout', "De waarschuwing naar het huidige adres ({$oudAdres}) kon niet worden verstuurd.");
        }

        try {
            Mail::to($gebruiker->nieuw_email)->send(new AdreswijzigingBevestigen($gebruiker));

            session()->flash('melding', $herhaling
                ? "Dit adres stond al open; de bevestiging is opnieuw verstuurd naar {$gebruiker->nieuw_email}. "
                    .'De eerdere link blijft werken.'
                : "Bevestiging verstuurd naar {$gebruiker->nieuw_email}. "
                    .'Het adres wijzigt pas als daar op de link is gedrukt.');
        } catch (\Throwable $e) {
            Log::error('Versturen bevestiging adreswijziging mislukt', [
                'gebruiker_id' => $gebruiker->id, 'fout' => $e->getMessage(),
            ]);
            session()->flash('fout', "De bevestigingsmail naar {$gebruiker->nieuw_email} kon niet worden verstuurd. "
                .'Trek de wijziging in en probeer het opnieuw.');
        }
    }

    public function deactiveren(Gebruiker $gebruiker): void
    {
        $this->vereisMuteren();

        // Zonder deze check kan de laatste CISO zichzelf buitensluiten en is er
        // niemand meer die accounts kan beheren.
        if ($gebruiker->is(auth()->user())) {
            session()->flash('fout', 'U kunt uw eigen account niet deactiveren.');

            return;
        }

        $gebruiker->update(['status' => 'gedeactiveerd']);
        session()->flash('melding', "Account van {$gebruiker->naam} is gedeactiveerd.");
    }

    /**
     * Blokkeren wegens een security-incident (01f). De statusmachine kende deze
     * overgang al (`deelproducten/01` §3, "te veel mislukte loginpogingen /
     * security-incident"); alleen de automatische helft was gebouwd.
     */
    public function openBlokkadeformulier(Gebruiker $gebruiker): void
    {
        $this->vereisMuteren();

        if ($gebruiker->status !== 'actief') {
            return;
        }

        if ($gebruiker->is(auth()->user())) {
            session()->flash('fout', 'U kunt uw eigen account niet blokkeren.');

            return;
        }

        $this->blokkadeGebruikerId = $gebruiker->id;
        $this->blokkadeReden = '';
        $this->resetValidation();
        $this->toontBlokkadeformulier = true;
    }

    public function blokkeren(): void
    {
        $this->vereisMuteren();

        $this->validate([
            'blokkadeReden' => ['required', 'string', 'max:255'],
        ], attributes: ['blokkadeReden' => 'reden']);

        $gebruiker = Gebruiker::findOrFail($this->blokkadeGebruikerId);

        // De twee checks uit openBlokkadeformulier() nóg een keer: tussen het
        // openen van de modal en het opslaan zit een tweede request, en in die
        // tijd kan de status veranderd zijn — of kan er met de id gerommeld
        // zijn. Zelf blokkeren sluit de laatste CISO permanent buiten: opheffen
        // kan alleen een CISO, dus de uitweg zou `isms:eerste-ciso` zijn.
        abort_if($gebruiker->is(auth()->user()), 403);

        if ($gebruiker->status !== 'actief') {
            $this->toontBlokkadeformulier = false;

            return;
        }

        $gebruiker->blokkeer(auth()->user(), $this->blokkadeReden);
        $this->beeindigSessies($gebruiker);

        $this->toontBlokkadeformulier = false;
        session()->flash('melding', "Account van {$gebruiker->naam} is geblokkeerd.");
    }

    public function blokkadeOpheffen(Gebruiker $gebruiker): void
    {
        $this->vereisMuteren();

        if ($gebruiker->status !== 'geblokkeerd') {
            return;
        }

        $gebruiker->heffBlokkadeOp();
        session()->flash('melding', "Blokkade van {$gebruiker->naam} is opgeheven.");
    }

    /**
     * De lopende sessies van de geblokkeerde er meteen uit gooien, in plaats van
     * te wachten tot zijn volgende verzoek.
     *
     * Aanvulling, geen vervanging: de garantie is `VereistActiefAccount` op de
     * web-groep, want die dekt ook de remember-me-cookie en werkt op elke
     * sessiedriver. Dit werkt alleen bij `SESSION_DRIVER=database` — vandaar de
     * check, zodat een latere overstap op redis hier geen fout oplevert maar
     * stil terugvalt op de middleware.
     */
    private function beeindigSessies(Gebruiker $gebruiker): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $gebruiker->id)
            ->delete();
    }

    /**
     * De tweede factor van een gebruiker opnieuw instellen
     * (implementatie/01d §8).
     *
     * Voor de gebruiker die zijn telefoon én zijn herstelcodes kwijt is. Dat de
     * CISO dit kan is geen zwakte maar het alternatief voor het echte gevaar:
     * zonder deze knop is de enige uitweg een handmatige `UPDATE` op de
     * database, en dat is precies het soort ingreep dat buiten elke logging
     * omgaat.
     *
     * De auditregel staat op naam van de CISO en op de rij van de betrokkene —
     * dat een ander dit deed is hier juist het interessante.
     */
    public function tweefactorResetten(Gebruiker $gebruiker): void
    {
        $this->vereisMuteren();

        app(DisableTwoFactorAuthentication::class)($gebruiker);

        // Nieuwe respijtperiode: hij moet opnieuw koppelen, maar niet met een
        // deadline die al verstreken is.
        $gebruiker->forceFill([
            'tweefactor_deadline' => now()->addDays(config('tweefactor.respijt_dagen')),
        ])->save();

        $gebruiker->schrijfAuditregel('gewijzigd', oud: null, nieuw: [
            'tweefactor' => 'gereset door de CISO',
        ]);

        session()->flash('melding', "Tweefactor van {$gebruiker->naam} is gereset. Bij de volgende login volgt opnieuw de instelprocedure.");
    }

    /**
     * Personeelsdossier openen (A.6): NDA, screening en offboarding. Geen harde
     * poort — wat ontbreekt is een gap-signaal, geen blokkade (keuze p14).
     */
    public function openDossier(Gebruiker $gebruiker): void
    {
        $this->vereisMuteren();
        $this->dossierGebruikerId = $gebruiker->id;
        $this->ndaGetekendOp = $gebruiker->nda_getekend_op?->format('Y-m-d');
        $this->screeningType = $gebruiker->screening_type ?? '';
        $this->screeningOp = $gebruiker->screening_op?->format('Y-m-d');
        $this->accountsIngetrokkenOp = $gebruiker->accounts_ingetrokken_op?->format('Y-m-d');
        $this->resetValidation();
        $this->toontDossier = true;
    }

    public function slaDossierOp(): void
    {
        $this->vereisMuteren();

        $gevalideerd = $this->validate([
            'ndaGetekendOp' => ['nullable', 'date', 'before_or_equal:today'],
            // Type en datum horen samen: een screening zonder een van beide is
            // geen registreerbare screening.
            'screeningType' => ['nullable', Rule::in(array_keys(Gebruiker::SCREENING_TYPES)), 'required_with:screeningOp'],
            'screeningOp' => ['nullable', 'date', 'before_or_equal:today', 'required_with:screeningType'],
            'accountsIngetrokkenOp' => ['nullable', 'date', 'before_or_equal:today'],
        ], attributes: [
            'ndaGetekendOp' => 'NDA-datum',
            'screeningType' => 'screeningstype',
            'screeningOp' => 'screeningsdatum',
            'accountsIngetrokkenOp' => 'datum accounts ingetrokken',
        ]);

        $gebruiker = Gebruiker::findOrFail($this->dossierGebruikerId);
        $gebruiker->update([
            'nda_getekend_op' => $gevalideerd['ndaGetekendOp'] ?: null,
            'screening_type' => $gevalideerd['screeningType'] ?: null,
            'screening_op' => $gevalideerd['screeningOp'] ?: null,
            'accounts_ingetrokken_op' => $gevalideerd['accountsIngetrokkenOp'] ?: null,
        ]);

        $this->toontDossier = false;
        session()->flash('melding', "Personeelsdossier van {$gebruiker->naam} bijgewerkt.");
    }

    private function verstuurUitnodiging(Gebruiker $gebruiker): void
    {
        try {
            Mail::to($gebruiker->email)->send(new GebruikerUitgenodigd($gebruiker));

            // Pas hier, en niet vóór de verzending: de kolom registreert dat er
            // post uit is gegaan, niet dat er op een knop is gedrukt. Faalt de
            // mail, dan blijft de oude datum staan en blijft het signaal uit
            // 01g §4 de aandacht vragen — wat dan klopt.
            $gebruiker->update(['uitnodiging_verstuurd_op' => now()]);

            session()->flash('melding', "Uitnodiging verstuurd naar {$gebruiker->email}.");
        } catch (\Throwable $e) {
            // Het account is al aangemaakt; alleen de mail faalde. Dat expliciet
            // melden is beter dan een 500 waarna de CISO niet weet wat er wel
            // en niet gelukt is.
            Log::error('Versturen uitnodiging mislukt', ['gebruiker_id' => $gebruiker->id, 'fout' => $e->getMessage()]);
            session()->flash('fout', "Account aangemaakt, maar de uitnodigingsmail naar {$gebruiker->email} kon niet worden verstuurd. Probeer 'Uitnodiging opnieuw versturen'.");
        }
    }

    public function render()
    {
        // geblokkeerdDoor mee: de statuskolom noemt wie er geblokkeerd heeft, en
        // dat zou anders een query per rij zijn.
        $gebruikers = Gebruiker::with('rollen', 'afdeling', 'geblokkeerdDoor')->orderBy('naam')->get();

        // De domeinen die al in gebruik zijn, uit de collectie die hierboven
        // toch al volledig geladen is (01g §5). Geen extra query.
        $domeintellingen = Domeincontrole::tellingen($gebruikers->pluck('email'));

        return view('livewire.gebruikers-overzicht', [
            'gebruikers' => $gebruikers,
            'rollen' => Rol::orderBy('naam')->get(),
            'afdelingen' => OrganisatieEenheid::afdelingen()->orderBy('naam')->pluck('naam', 'id')->all(),
            'screeningTypes' => Gebruiker::SCREENING_TYPES,
            // Rapportagesignalen (A.6): actieve accounts zonder afgeronde
            // pre-employment, en gedeactiveerde zonder bevestigde offboarding.
            'preEmploymentGaps' => $gebruikers->filter->preEmploymentGap()->count(),
            'offboardingGaps' => $gebruikers->filter->offboardingGap()->count(),
            // Uitnodigingen die verliepen zonder dat het account in gebruik is
            // genomen (01g §4). Dit is het deel dat de meeste typefouten vangt,
            // inclusief die op adressen die netjes bouncen en dus nooit tot een
            // telefoontje leiden.
            'verlopenUitnodigingen' => $gebruikers->filter->uitnodigingVerlopen()->count(),
            // Kant-en-klaar naar de view: de regel zelf staat in Domeincontrole,
            // en een blade hoort geen support-klasse aan te roepen.
            'bijnaTrefferUitnodiging' => Domeincontrole::bijnaTreffer($this->email, $domeintellingen),
            'bijnaTrefferCorrectie' => Domeincontrole::bijnaTreffer($this->correctieEmail, $domeintellingen),
            // Bij een actief account weegt de bijna-treffer zwaarder dan bij een
            // uitnodiging: het account bestaat al en heeft historie (01h §5).
            'bijnaTrefferAdreswijziging' => Domeincontrole::bijnaTreffer($this->adreswijzigingEmail, $domeintellingen),
            // Aantal gekoppelde bewijsstukken bij het geopende dossier.
            'dossierBewijsAantal' => $this->dossierGebruikerId
                ? BewijsKoppeling::where('entiteit_type', 'gebruiker')
                    ->where('entiteit_id', $this->dossierGebruikerId)->count()
                : 0,
        ]);
    }
}
