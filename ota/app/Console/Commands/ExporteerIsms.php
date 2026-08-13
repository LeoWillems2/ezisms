<?php

namespace App\Console\Commands;

use App\Models\Afwijking;
use App\Models\Asset;
use App\Models\AssetToewijzing;
use App\Models\Auditprogramma;
use App\Models\AuditprogrammaDekking;
use App\Models\Belanghebbende;
use App\Models\Beleidsdocument;
use App\Models\BewijsKoppeling;
use App\Models\Bewijsstuk;
use App\Models\Classificatieschema;
use App\Models\Contractclausule;
use App\Models\Doelgroep;
use App\Models\Gebruiker;
use App\Models\Incident;
use App\Models\IntegratieAdapter;
use App\Models\Issue;
use App\Models\KpiDefinitie;
use App\Models\Leesbevestiging;
use App\Models\Leverancier;
use App\Models\Meting;
use App\Models\OrganisatieEenheid;
use App\Models\RestrisicoSnapshot;
use App\Models\Reviewsessie;
use App\Models\Risico;
use App\Models\RisicocriteriaVersie;
use App\Models\ScopeInterface;
use App\Models\ScopeVerklaring;
use App\Models\SoaRegel;
use App\Models\Systeem;
use App\Models\Taak;
use App\Models\Trainingsmodule;
use App\Models\Trainingsvoltooiing;
use App\Models\Uitsluiting;
use App\Models\Wijziging;
use App\Support\Beoordelingsschaal;
use App\Support\Maatregelkenmerken;
use App\Support\Normprofiel;
use App\Support\Stappenreeks;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Exporteert het ISMS als een mens-leesbare Markdown-mapstructuur, zodat een CISO
 * de inhoud kan lezen en overnemen in een ander ISMS. Bewust GEEN 100%-behoud:
 * de operationele/interne data (audit-trail, taken, notificaties, loginpogingen)
 * blijft weg; het gaat om de registers en hun onderbouwing.
 *
 * Standaard:
 *   - persoonsgegevens als initialen + rol (bv. "JD (CISO)"); vrije-tekst
 *     naamvelden (goedgekeurd_door, deelnemers) blijven weg tenzij
 *     --met-persoonsgegevens;
 *   - geen bestanden meegekopieerd tenzij --met-bewijs;
 *   - elke run een eigen map met datumstempel (nooit overschrijven).
 */
class ExporteerIsms extends Command
{
    protected $signature = 'isms:exporteer
        {--doel= : Doelmap voor de export (standaard storage/app/exports)}
        {--met-bewijs : Kopieer de bewijsstukken en beleidsdocumenten mee in _bewijs/}
        {--met-persoonsgegevens : Toon volledige namen i.p.v. initialen + rol}';

    protected $description = 'Exporteert het ISMS als mens-leesbare Markdown-mapstructuur voor overname in een ander ISMS';

    private string $basis;

    private bool $metBewijs = false;

    private bool $metPersoon = false;

    /** @var Collection<int, Gebruiker> */
    private Collection $gebruikers;

    /** @var array<int, string> bewijsstuk-id => relatief pad in de export */
    private array $bewijsPaden = [];

    /** @var array<string, list<Bewijsstuk>> "morphalias:id" => bewijsstukken */
    private array $bewijsPerEntiteit = [];

    public function handle(): int
    {
        $this->metBewijs = (bool) $this->option('met-bewijs');
        $this->metPersoon = (bool) $this->option('met-persoonsgegevens');
        $this->gebruikers = Gebruiker::with('rollen')->get()->keyBy('id');

        $this->basis = $this->bepaalDoelmap();
        File::ensureDirectoryExists($this->basis);

        if ($this->metBewijs) {
            $this->kopieerBewijs();
        }

        // Ná kopieerBewijs(): de paden moeten bekend zijn voordat een domein een
        // bewijsregel schrijft.
        $this->laadBewijskoppelingen();

        // Elk domein schrijft één bestand en meldt een telling voor het overzicht.
        $tellingen = [
            '01-context-scope.md' => $this->contextScope(),
            '02-assets.md' => $this->assets(),
            '03-risico-en-soa.md' => $this->risicoSoa(),
            '04-beleid.md' => $this->beleid(),
            '05-leveranciers.md' => $this->leveranciers(),
            '06-incidenten-en-afwijkingen.md' => $this->incidentenAfwijkingen(),
            '07-audits.md' => $this->audits(),
            '08-meten-en-directiebeoordeling.md' => $this->metenReview(),
            '09-bewustzijn-en-training.md' => $this->bewustzijnTraining(),
            '10-integraties.md' => $this->integraties(),
            // Het A.8.32-bewijsstuk: welke wijzigingen zijn er geweest, en met
            // welke goedkeuring. De sjablonen horen hier niet in — dat is
            // configuratie, geen register (implementatie/15 §11).
            '11-wijzigingen.md' => $this->wijzigingen(),
        ];

        $this->schrijfOverzicht($tellingen);

        $this->info("ISMS geëxporteerd naar: {$this->basis}");
        $this->line('- Persoonsgegevens: '.($this->metPersoon ? 'volledige namen' : 'initialen + rol'));
        $this->line('- Bewijs/documenten: '.($this->metBewijs ? count($this->bewijsPaden).' bestand(en) meegekopieerd' : 'niet meegekopieerd (alleen benoemd)'));

        return self::SUCCESS;
    }

    // --- Opzet --------------------------------------------------------------

    private function bepaalDoelmap(): string
    {
        $ouder = $this->option('doel') ?: storage_path('app/exports');
        $naam = 'isms-export-'.now()->format('Y-m-d');
        $pad = rtrim($ouder, '/').'/'.$naam;

        // Nooit overschrijven: bij een tweede run vandaag een tijdsuffix.
        if (File::exists($pad)) {
            $pad .= '_'.now()->format('His');
        }

        return $pad;
    }

    private function kopieerBewijs(): void
    {
        $doel = $this->basis.'/_bewijs';
        File::ensureDirectoryExists($doel);
        $disk = Storage::disk(Bewijsstuk::DISK);
        $gemist = 0;

        foreach (Bewijsstuk::all() as $stuk) {
            if (! $disk->exists($stuk->opslaglocatie_referentie)) {
                $gemist++;

                continue;
            }

            $bestand = $stuk->id.'-'.Str::slug(pathinfo($stuk->bestandsnaam, PATHINFO_FILENAME))
                .'.'.pathinfo($stuk->bestandsnaam, PATHINFO_EXTENSION);
            File::put($doel.'/'.$bestand, $disk->get($stuk->opslaglocatie_referentie));
            $this->bewijsPaden[$stuk->id] = '_bewijs/'.$bestand;
        }

        if ($gemist > 0) {
            $this->warn("{$gemist} bewijsstuk(ken) niet gevonden op de disk; overgeslagen.");
        }
    }

    /**
     * Alle bewijskoppelingen in één query, gegroepeerd op "morphalias:id".
     *
     * Plan 00d §3: het bewijs komt bij de entiteit te staan, niet in een apart
     * register. Zonder deze index werd dat een query per SoA-regel, en dat zijn
     * er 93 — nog los van de risico's en bevindingen.
     */
    private function laadBewijskoppelingen(): void
    {
        foreach (BewijsKoppeling::with('bewijsstuk')->get() as $koppeling) {
            if ($koppeling->bewijsstuk === null) {
                continue;
            }

            $this->bewijsPerEntiteit[$koppeling->entiteit_type.':'.$koppeling->entiteit_id][] = $koppeling->bewijsstuk;
        }
    }

    /**
     * De bewijsregel onder een entiteit, of een lege string als er niets hangt.
     *
     * Werkt bewust ook zónder `--met-bewijs`: dan staat de bestandsnaam er, en
     * die wijst de vindplaats in het oorspronkelijke systeem aan. Zo blijft de
     * keten risico → maatregel → SoA → bewijs leesbaar in beide standen.
     *
     * `$inspring` maakt het bruikbaar onder een geneste opsomming (bevindingen).
     * `$behalve` laat een stuk weg dat de aanroeper al zelf noemt — bij beleid is
     * het gekoppelde bewijsstuk vaak precies het documentbestand dat er een regel
     * hoger al staat, en twee keer dezelfde link is geen extra informatie.
     */
    private function bewijs(string $type, ?int $id, string $inspring = '', ?int $behalve = null): string
    {
        $stukken = array_filter(
            $this->bewijsPerEntiteit[$type.':'.$id] ?? [],
            fn (Bewijsstuk $stuk) => $stuk->id !== $behalve,
        );

        if ($stukken === []) {
            return '';
        }

        $namen = array_map(function (Bewijsstuk $stuk) {
            $pad = $this->bewijsPaden[$stuk->id] ?? null;

            return $pad === null ? $this->cel($stuk->bestandsnaam) : "[{$this->cel($stuk->bestandsnaam)}]({$pad})";
        }, $stukken);

        return $inspring.'- Bewijs: '.implode(', ', $namen)."\n";
    }

    private function schrijfOverzicht(array $tellingen): void
    {
        $regels = [];
        foreach ($tellingen as $bestand => $aantal) {
            $titel = Str::of($bestand)->after('-')->before('.md')->replace('-', ' ')->title();
            $regels[] = "| [{$titel}]({$bestand}) | {$aantal} |";
        }

        $anon = $this->metPersoon
            ? 'Volledige namen zijn opgenomen.'
            : 'Persoonsgegevens zijn geanonimiseerd tot initialen + rol; vrije-tekst naamvelden zijn weggelaten.';

        $md = "# ISMS-export\n\n"
            .'Gegenereerd op **'.now()->lokaal()->format('d-m-Y H:i').'**. Dit is een mens-leesbare '
            .'momentopname om over te nemen in een ander ISMS — **geen** volledige of '
            ."machine-importeerbare kopie.\n\n"
            // De belangrijkste van de drie normstempels (implementatie/00h §5):
            // een export verlaat het systeem, en dan is er niets meer dat de
            // lezer vertelt tegen welke norm deze beoordelingen zijn gemaakt.
            .'**Norm:** dit ISMS is ingericht op **'.Normprofiel::label('naam').'**. '
            .'De maatregelen komen uit '.Normprofiel::label('bijlage').'; de '
            .'beoordelingen hieronder gelden voor die norm en niet vanzelf voor een '
            ."andere.\n\n"
            // Expliciet in plaats van "en dergelijke": een export die zwijgt over
            // wat hij weglaat, laat de lezer raden of iets ontbreekt of niet
            // bestaat. Deze lijst hoort mee te bewegen met de code.
            .'**Bewust niet opgenomen:** de audit-trail, taken en taaksjablonen, '
            .'notificaties en notificatieregels, loginpogingen, raadplegingen van '
            .'bewijsstukken, synchronisatielogs, rollen/permissies en interne '
            .'identifiers. Dat is operationele en interne data; deze export bevat de '
            ."registers en hun onderbouwing.\n\n"
            ."> **Privacy.** {$anon} "
            .($this->metBewijs ? 'De map `_bewijs/` bevat gekopieerde bestanden — behandel de export als vertrouwelijk.'
                : 'Bewijsstukken zijn niet meegekopieerd (alleen benoemd).')."\n\n"
            ."## Inhoud\n\n"
            ."| Domein | Aantal | \n|---|---|\n".implode("\n", $regels)."\n";

        $this->schrijf('00-overzicht.md', $md);
    }

    // --- Domeinen -----------------------------------------------------------

    private function contextScope(): int
    {
        $md = "# Context & scope\n\n## Scopeverklaringen\n\n";
        $verklaringen = ScopeVerklaring::with('interfaces')->orderByDesc('versienummer')->get();
        foreach ($verklaringen as $v) {
            $md .= "### Versie {$v->versienummer} ({$v->status})\n\n"
                ."- Geldig vanaf: {$this->datum($v->geldig_vanaf)}\n"
                ."- Goedgekeurd door: {$this->vrijeNaam($v->goedgekeurd_door)}\n"
                ."- Volgende herziening: {$this->datum($v->volgende_herziening_gepland)}\n"
                .$this->bewijs('scope_verklaring', $v->id)."\n"
                .$this->blok($v->scopetekst)."\n\n";

            // Interfaces naar buiten-scope onderdelen: een verplicht onderdeel
            // van §4.3, en juist de plek waar verantwoordelijkheid overgaat.
            $interfaces = $v->interfaces ?? collect();
            if ($interfaces->isNotEmpty()) {
                $md .= "**Interfaces naar buiten de scope**\n\n".$this->tabel(
                    ['Omschrijving', 'Risico-implicatie'],
                    $interfaces->map(fn (ScopeInterface $i) => [$i->omschrijving, $i->risico_implicatie ?? '—'])
                )."\n";
            }
        }

        $eenheden = OrganisatieEenheid::orderBy('naam')->get()->keyBy('id');
        $md .= "## Organisatie-eenheden\n\n".$this->tabel(['Naam', 'Type', 'Valt onder'],
            $eenheden->map(fn (OrganisatieEenheid $e) => [
                $e->naam, $e->type ?? '—', $eenheden->get($e->bovenliggende_eenheid_id)?->naam ?? '—',
            ]));

        // Issues (§4.1) vóór de belanghebbenden: dat volgt de volgorde van de
        // norm (§4.1 → §4.2) en die van het scherm.
        //
        // De kolom "Landt in" is de reden dat dit register de moeite waard is.
        // Een lijst kwesties zonder doorvertaling is wat §4.1 in de meeste
        // ISMS'en is: een register dat nergens op uitkomt. Met de risicotitels
        // erbij beantwoordt de export zelf de vraag die een auditor stelt, en is
        // zichtbaar welke kwesties nergens landen.
        $issues = Issue::with('risicos')->orderBy('aard')->orderBy('categorie')->get();
        if ($issues->isNotEmpty()) {
            $md .= "\n## Issues (§4.1)\n\n".$this->tabel(
                ['Aard', 'Categorie', 'Omschrijving', 'Laatst beoordeeld', 'Landt in'],
                $issues->map(fn (Issue $i) => [
                    $i->aard,
                    $i->categorie,
                    $i->omschrijving,
                    $this->datum($i->laatst_beoordeeld_op),
                    $i->risicos->pluck('titel')->implode('; ') ?: '—',
                ])
            );
        }

        $belanghebbenden = Belanghebbende::with('eisen')->orderBy('naam')->get();
        $md .= "\n## Belanghebbenden & eisen\n\n";
        foreach ($belanghebbenden as $b) {
            $md .= "### {$b->naam}".($b->aard ? " ({$b->aard})" : '')."\n\n"
                .($b->relevantie_voor_isms ? $this->blok($b->relevantie_voor_isms)."\n\n" : '');
            $eisen = $b->eisen ?? collect();
            foreach ($eisen as $eis) {
                $md .= "- {$this->cel($eis->omschrijving)}".($eis->bron ? " _(bron: {$eis->bron})_" : '')."\n";
            }
            $md .= "\n";
        }

        $uitsluitingen = Uitsluiting::orderBy('id')->get();
        if ($uitsluitingen->isNotEmpty()) {
            $md .= "## Uitsluitingen\n\n".$this->tabel(['Omschrijving', 'Motivatie'],
                $uitsluitingen->map(fn (Uitsluiting $u) => [$u->omschrijving, $u->motivatie ?? '—']));
        }

        $this->schrijf('01-context-scope.md', $md);

        return $verklaringen->count();
    }

    private function assets(): int
    {
        $eenheden = OrganisatieEenheid::pluck('naam', 'id');
        $assets = Asset::orderBy('naam')->get();

        // Let op: de kolom "Persoonsgegevens" staat er altijd, ook zonder
        // --met-persoonsgegevens. Die vlag gaat over namen van personen in de
        // export; de vaststelling dát een asset bijzondere persoonsgegevens
        // bevat, is zelf geen persoonsgegeven en hoort bij de auditor thuis.
        $md = "# Assets\n\n".$this->tabel(
            ['Naam', 'Type', 'Eenheid', 'Accountable', 'In scope', 'V', 'I', 'B', 'Persoonsgegevens'],
            $assets->map(fn (Asset $a) => [
                $a->naam, $a->type ?? '—', $eenheden->get($a->organisatie_eenheid_id) ?? '—',
                $this->persoonId($a->accountable_id), $this->janee($a->binnen_scope),
                $a->vertrouwelijkheidsniveau ?? '—', $a->integriteitsniveau ?? '—', $a->beschikbaarheidsniveau ?? '—',
                $a->persoonsgegevens ?? 'nog niet beoordeeld',
            ])
        );

        $schema = Classificatieschema::orderBy('dimensie')->orderBy('niveau')->get();
        if ($schema->isNotEmpty()) {
            $md .= "\n## Classificatieschema\n\n".$this->tabel(['Dimensie', 'Niveau', 'Omschrijving', 'Omgangsregels'],
                $schema->map(fn (Classificatieschema $c) => [$c->dimensie, $c->niveau, $c->omschrijving ?? '—', $c->omgangsregels ?? '—']));
        }

        $leveranciers = Leverancier::pluck('naam', 'id');
        $systemen = Systeem::orderBy('naam')->get();
        if ($systemen->isNotEmpty()) {
            $md .= "\n## Systemen\n\n".$this->tabel(
                ['Naam', 'Hosting', 'Leverancier', 'Status', 'Beschikbaarheidseis', 'Redundant'],
                $systemen->map(fn (Systeem $s) => [
                    $s->naam, $s->hostingtype ?? '—', $leveranciers->get($s->leverancier_id) ?? '—',
                    $s->status ?? '—', $s->beschikbaarheidseis ?? '—',
                    $this->janee($s->redundant).($s->redundantie_toelichting ? " — {$s->redundantie_toelichting}" : ''),
                ])
            );
        }

        // A.5.11 gaat over uitgifte én retour. Geretourneerde toewijzingen blijven
        // daarom staan: juist de afgeronde retourregistratie is het bewijs dat die
        // stap heeft plaatsgevonden (besluit plan 00d §8).
        if ($this->metPersoon) {
            $toewijzingen = AssetToewijzing::with('asset')
                ->orderBy('asset_id')->orderByDesc('toegewezen_op')->get();

            $md .= "\n## Uitgifte van bedrijfsmiddelen (A.5.11)\n\n"
                .($toewijzingen->isEmpty() ? "_Geen toewijzingen geregistreerd._\n" : $this->tabel(
                    ['Middel', 'Persoon', 'Toegewezen', 'Geretourneerd'],
                    $toewijzingen->map(fn (AssetToewijzing $t) => [
                        $t->asset?->naam ?? '—', $this->persoonId($t->gebruiker_id),
                        $this->datum($t->toegewezen_op), $this->datum($t->geretourneerd_op),
                    ])
                ));
        }

        $this->schrijf('02-assets.md', $md);

        return $assets->count();
    }

    private function risicoSoa(): int
    {
        $assets = Asset::pluck('naam', 'id');
        $leveranciers = Leverancier::pluck('naam', 'id');

        // De criteria vóór de risico's, want het is het kader waarbinnen de rest
        // gelezen moet worden en geen bijlage. Zonder dit ziet een lezer "score
        // 16, geaccepteerd door de directie" en kan hij niet vaststellen of dat
        // binnen het mandaat viel — de drempels zijn instelbaar, dus ook niet uit
        // de code af te leiden.
        $md = "# Risico's & Verklaring van Toepasselijkheid\n\n## Risicocriteria (§6.1.2 a)\n\n";
        $actief = RisicocriteriaVersie::actief();

        if ($actief === null) {
            $md .= '_Geen criteria vastgesteld; de applicatie viel terug op de standaarddrempels '
                .Risico::DREMPEL_STANDAARD.' (acceptatie) en '.Risico::WAARSCHUWINGSDREMPEL_STANDAARD
                ." (waarschuwing)._\n\n";
        } else {
            // Wie het kader heeft vastgesteld en wanneer, vóór wat het inhoudt:
            // dat is de eerste vraag bij §6.1.2 a), en zonder antwoord is de rest
            // van dit hoofdstuk een instelling in plaats van een criterium.
            $md .= "**Versie {$actief->versienummer}**, geldig vanaf "
                .$this->datum($actief->geldig_vanaf)
                .', goedgekeurd door '.$this->vrijeNaam($actief->goedgekeurd_door)."\n\n"
                .'- Vastgesteld in: '
                .($actief->beleidsdocument
                    ? $this->cel($actief->beleidsdocument->titel)
                    : '_niet herleidbaar naar vastgesteld beleid_')."\n"
                .($actief->besluit
                    ? '- Besluit: '.$this->cel($actief->besluit->omschrijving)."\n"
                    : '')
                ."- Acceptatiedrempel **{$actief->drempelwaarde_score}**, waarschuwing vanaf "
                ."**{$actief->waarschuwingsdrempel_score}**\n\n"
                .$this->blok($actief->omschrijving)."\n\n";
        }

        // De schaal hoort bij de criteria en niet bij de risico's: zonder hem
        // staat er straks "kans 3 × impact 4" en is niet vast te stellen wat een
        // 4 was. Dat is hetzelfde argument als hierboven bij de drempels, en het
        // weegt hier zwaarder — de schaal is vastgesteld door de organisatie zelf
        // en kan per versie verschillen (04g §8.1).
        //
        // Zonder actieve versie is er geen vastgestelde schaal en blijft dit
        // stuk leeg, in plaats van dat de hele export klapt — dezelfde
        // faalrichting als de zin hierboven over de standaarddrempels.
        foreach ($actief === null ? [] : ['kans', 'impact'] as $as) {
            $schaal = Beoordelingsschaal::as($as);

            $md .= "### Schaal — {$schaal['label']}\n\n".$this->blok($schaal['leidraad'])."\n\n"
                .$this->tabel(
                    ['Niveau', 'Betekenis', 'Kwantitatieve band'],
                    array_map(
                        fn (int $niveau, array $definitie) => [
                            $niveau.' — '.$definitie['naam'],
                            $definitie['omschrijving'],
                            $definitie['kwantitatieve_band'] ?? '—',
                        ],
                        array_keys($schaal['niveaus']),
                        $schaal['niveaus'],
                    ),
                )."\n";
        }

        // De versiehistorie: zonder deze tabel is de kolom "beoordeeld onder" bij
        // de risico's hieronder niet te lezen.
        $historie = RisicocriteriaVersie::orderBy('versienummer')->get();

        if ($historie->count() > 1) {
            $md .= "### Versiehistorie van de criteria\n\n"
                .$this->tabel(
                    ['Versie', 'Status', 'Geldig vanaf', 'Drempels (amber/rood)', 'Goedgekeurd door', 'Wijzigingsreden'],
                    $historie->map(fn (RisicocriteriaVersie $v) => [
                        (string) $v->versienummer,
                        $v->status,
                        $this->datum($v->geldig_vanaf),
                        $v->waarschuwingsdrempel_score.' / '.$v->drempelwaarde_score,
                        $this->vrijeNaam($v->goedgekeurd_door),
                        $this->cel($v->wijzigingsreden ?? '—'),
                    ])->all(),
                )."\n";
        }

        $md .= "## Risico's\n\n";
        $risicos = Risico::with(['behandelingen', 'aanleidingen', 'criteriaVersie'])->orderByDesc('risicoscore')->get();
        foreach ($risicos as $r) {
            $assetNaam = $this->cel($assets->get($r->gekoppeld_asset_id) ?? '—');
            $levNaam = $this->cel($leveranciers->get($r->gekoppeld_leverancier_id) ?? '—');
            $md .= "### {$r->titel} ({$r->status})\n\n"
                ."- Score: {$r->risicoscore} (kans {$r->kans_niveau} × impact {$r->impact_niveau})\n"
                // Onder welk kader deze score tot stand kwam. Zonder dit is van
                // een risico dat vorig jaar als aanvaardbaar gold niet meer vast
                // te stellen tegen welke drempel dat gebeurde (04g §2.6a).
                .($r->criteriaVersie ? "- Beoordeeld onder: risicocriteria v{$r->criteriaVersie->versienummer}\n" : '')
                ."- Eigenaar: {$this->persoonId($r->risico_eigenaar_id)}\n"
                .($r->gekoppeld_asset_id ? "- Asset: {$assetNaam}\n" : '')
                .($r->gekoppeld_leverancier_id ? "- Leverancier: {$levNaam}\n" : '')
                .($r->dreiging ? "- Dreiging: {$this->cel($r->dreiging)}\n" : '')
                .($r->kwetsbaarheid ? "- Kwetsbaarheid: {$this->cel($r->kwetsbaarheid)}\n" : '');
            foreach ($r->behandelingen as $b) {
                $rest = $b->restrisico_score ?? '—';
                $md .= "- Behandeling: **{$b->behandeloptie}**, restrisico {$rest}"
                    .($b->geaccepteerd_op ? ", geaccepteerd {$this->datum($b->geaccepteerd_op)} door {$this->vrijeNaam($b->geaccepteerd_door)}" : '')."\n";
            }
            $md .= $this->bewijs('risico', $r->id)
                .$this->aanleiding($r)."\n";
        }

        // SoA per control: de kern die een CISO overneemt.
        $regels = SoaRegel::with(['maatregel', 'restrisicoSnapshots'])->get()
            ->sortBy(fn (SoaRegel $s) => $s->maatregel?->annex_a_referentie);
        $md .= "## Verklaring van Toepasselijkheid (SoA)\n\n";
        foreach ($regels as $s) {
            $m = $s->maatregel;
            if ($m === null) {
                continue;
            }
            $vt = $s->van_toepassing === true ? 'Van toepassing' : ($s->van_toepassing === false ? 'Uitgesloten' : 'Onbeslist');
            $md .= "### A.{$m->annex_a_referentie} — {$m->naam}\n\n"
                ."- Status: **{$vt}**".($s->implementatiestatus ? " · implementatie: {$s->implementatiestatus}" : '')."\n"
                .($s->motivatie ? "- Motivatie: {$this->cel($s->motivatie)}\n" : '')
                .($s->beleidreferentie ? "- Beleidreferentie: {$this->cel($s->beleidreferentie)}\n" : '')
                .($s->procesreferentie ? "- Procesreferentie: {$this->cel($s->procesreferentie)}\n" : '')
                .$this->classificatie($s)
                .$this->bewijs('soa_regel', $s->id)
                .$this->restrisicoTrend($s)."\n";
        }

        $this->schrijf('03-risico-en-soa.md', $md);

        return $risicos->count();
    }

    /** De §4.1-kwesties waaruit dit risico is geïdentificeerd (plan 02b). */
    private function aanleiding(Risico $risico): string
    {
        $issues = $risico->aanleidingen;

        return $issues->isEmpty()
            ? ''
            : '- Aanleiding (§4.1): '.$this->cel($issues->pluck('omschrijving')->implode('; '))."\n";
    }

    /**
     * De jaartrend per control (plan 04c). Eén regel per peiljaar; zonder
     * snapshots niets, want een control die nooit is gemeten hoort geen lege
     * kop te krijgen.
     */
    private function restrisicoTrend(SoaRegel $regel): string
    {
        $snapshots = $regel->restrisicoSnapshots;

        if ($snapshots->isEmpty()) {
            return '';
        }

        $punten = $snapshots->map(fn (RestrisicoSnapshot $s) => "{$s->peiljaar}: max {$s->max_restrisico}"
            ." over {$s->aantal_risicos} risico('s)")->implode(' · ');

        return "- Restrisico per jaar: {$punten}\n";
    }

    private function beleid(): int
    {
        $documenten = Beleidsdocument::with(['versies' => fn ($q) => $q->orderByDesc('versienummer')])
            ->orderBy('titel')->get();

        $md = "# Beleid\n\n";
        foreach ($documenten as $d) {
            $actief = $d->versies->firstWhere('status', 'gepubliceerd') ?? $d->versies->first();
            $md .= "## {$d->titel} ({$d->status})\n\n"
                .($d->type ? "- Type: {$d->type}\n" : '')
                ."- Eigenaar: {$this->persoonId($d->eigenaar_id)}\n"
                ."- Leesbevestiging vereist: {$this->janee($d->leesbevestiging_vereist)}\n";
            if ($actief !== null) {
                $md .= "- Actieve versie: {$actief->versienummer}"
                    .($actief->gepubliceerd_op ? ", gepubliceerd {$this->datum($actief->gepubliceerd_op)}" : '')
                    .($actief->goedgekeurd_op ? ", goedgekeurd {$this->datum($actief->goedgekeurd_op)} door {$this->persoonId($actief->goedgekeurd_door_id)}" : '')."\n";
                if ($this->metBewijs && isset($this->bewijsPaden[$actief->bewijsstuk_id])) {
                    $md .= "- Document: [{$this->bewijsPaden[$actief->bewijsstuk_id]}]({$this->bewijsPaden[$actief->bewijsstuk_id]})\n";
                }
            }
            $md .= $this->bewijs('beleidsdocument', $d->id, behalve: $actief?->bewijsstuk_id)
                .($d->omschrijving ? "\n".$this->blok($d->omschrijving)."\n" : '')."\n";
        }

        $this->schrijf('04-beleid.md', $md);

        return $documenten->count();
    }

    private function leveranciers(): int
    {
        $leveranciers = Leverancier::with([
            'beoordelingen' => fn ($q) => $q->orderByDesc('uitgevoerd_op'),
            'diensten',
            'contractclausules',
        ])->orderBy('naam')->get();

        $md = "# Leveranciers\n\n";
        foreach ($leveranciers as $l) {
            $md .= "## {$l->naam} ({$l->status})\n\n"
                ."- Risiconiveau: {$l->risiconiveau}\n"
                .($l->eigen_certificering_geldig_tot ? "- Certificering geldig tot: {$this->datum($l->eigen_certificering_geldig_tot)}\n" : '')
                .($l->data_teruggave_bevestigd_op ? "- Data-teruggave bevestigd: {$this->datum($l->data_teruggave_bevestigd_op)} door {$this->persoonId($l->data_teruggave_door_id)}\n" : '');
            foreach ($l->diensten ?? [] as $d) {
                $md .= "- Dienst: {$this->cel($d->omschrijving)}\n";
            }
            foreach ($l->beoordelingen ?? [] as $b) {
                $bev = $this->cel($b->bevindingen ?? '—');
                $md .= "- Beoordeling {$this->datum($b->uitgevoerd_op)} door {$this->persoonId($b->uitgevoerd_door_id)}: {$bev}\n";
            }
            $md .= $this->bewijs('leverancier', $l->id);

            // A.5.19-5.23: juist de ontbrekende clausules zijn het signaal, dus
            // aanwezig én afwezig tonen in plaats van alleen wat er is.
            $clausules = $l->contractclausules ?? collect();
            if ($clausules->isNotEmpty()) {
                $md .= "\n".$this->tabel(['Contractclausule', 'Aanwezig'],
                    $clausules->map(fn (Contractclausule $c) => [$c->type, $this->janee($c->aanwezig)]));
            }

            $md .= "\n";
        }

        $this->schrijf('05-leveranciers.md', $md);

        return $leveranciers->count();
    }

    /**
     * Het wijzigingenregister (A.8.32). De stappen staan erbij met hun uitkomst:
     * "wie heeft dit geautoriseerd, en wanneer" is de vraag waarvoor dit
     * register bestaat, en die is niet te beantwoorden met alleen de
     * dossierstatus.
     */
    private function wijzigingen(): int
    {
        $wijzigingen = Wijziging::with(['leverancier', 'systemen'])
            ->orderByDesc('gepland_op')->orderBy('id')->get();

        $md = "# Wijzigingen\n\n";

        foreach ($wijzigingen as $w) {
            $md .= "## {$this->cel($w->titel)} ({$w->status})\n\n"
                ."- Soort: {$w->soort} · zwaarte: {$w->zwaarte}\n"
                .($w->leverancier ? "- Leverancier: {$this->cel($w->leverancier->naam)}\n" : '')
                .($w->externe_referentie ? "- Externe referentie: {$this->cel($w->externe_referentie)}\n" : '')
                .($w->aangekondigd_op ? "- Aangekondigd: {$this->datum($w->aangekondigd_op)}\n" : '')
                .($w->gepland_op ? "- Gepland: {$this->datum($w->gepland_op)}\n" : '')
                .($w->uitgevoerd_op ? "- Uitgevoerd: {$this->datum($w->uitgevoerd_op)}\n" : '')
                ."- Aangemeld door: {$this->persoonId($w->aangemeld_door_id)}\n";

            if ($w->systemen->isNotEmpty()) {
                $md .= '- Geraakte systemen: '.$this->cel($w->systemen->pluck('naam')->join(', '))."\n";
            }

            $md .= '- Terugvalplan: '.($w->terugvalplan ? $this->cel($w->terugvalplan) : '**ontbreekt**')."\n"
                .($w->impact_toelichting ? "- Impact: {$this->cel($w->impact_toelichting)}\n" : '');

            if ($w->status === 'gesloten') {
                $md .= '- Evaluatie: '.($w->geslaagd ? 'geslaagd' : 'niet geslaagd')
                    .($w->teruggedraaid ? ', teruggedraaid' : '')
                    .' — '.$this->cel($w->evaluatie ?? '—')."\n";
            }

            $md .= $this->bewijs('wijziging', $w->id);

            $stappen = Stappenreeks::voorEntiteit($w);

            if ($stappen->isNotEmpty()) {
                $md .= "\n".$this->tabel(
                    ['#', 'Stap', 'Eigenaar', 'Deadline', 'Status', 'Uitkomst'],
                    $stappen->map(fn (Taak $s) => [
                        (string) $s->volgorde,
                        $this->cel($s->titel),
                        $this->persoonId($s->eigenaar_id),
                        $this->datum($s->deadline),
                        $s->status,
                        $s->uitkomst ?? '—',
                    ]),
                );
            }

            $md .= "\n";
        }

        $this->schrijf('11-wijzigingen.md', $md);

        return $wijzigingen->count();
    }

    /**
     * De beoordeling van de externe meldplicht plus de verplichtingen die
     * eruit volgden (implementatie/08b).
     *
     * Bij een incident zonder raakvlak blijft het bij één regel: er is dan geen
     * documentatieplicht, want AVG art. 33 lid 5 gaat over inbreuken in verband
     * met persoonsgegevens en niet over elk beveiligingsincident. Waar de wet
     * wél speelt, staat de motivatie er ook bij een "nee" — dat is precies wat
     * een toezichthouder dan wil lezen.
     */
    private function externeMeldplicht(Incident $incident): string
    {
        if ($incident->raakt_persoonsgegevens === null) {
            return "- Externe meldplicht: nog niet beoordeeld\n";
        }

        if (! $incident->heeftDocumentatieplicht()) {
            return '- Externe meldplicht: beoordeeld, geen raakvlak (geen persoonsgegevens'
                .($incident->is_netwerk_informatie_incident === false ? ', geen netwerk- of informatiesysteem' : '')
                .")\n";
        }

        if ($incident->extern_meldingsplichtig === null) {
            return '- Externe meldplicht: raakvlak vastgesteld ('
                .implode(' + ', array_map('strtoupper', $incident->meldgrondslagen()))
                ."), beoordeling loopt nog\n";
        }

        $oordeel = $incident->extern_meldingsplichtig ? 'meldingsplichtig' : 'niet meldingsplichtig';
        $md = "- Externe meldplicht: {$oordeel}"
            .($incident->meldplicht_beoordeeld_op ? ' (beoordeeld '.$this->datum($incident->meldplicht_beoordeeld_op).')' : '')
            .($incident->meldplicht_motivatie ? ' — '.$this->cel($incident->meldplicht_motivatie) : '')
            ."\n";

        foreach ($incident->meldingen as $melding) {
            $stand = match (true) {
                $melding->isGemeld() && $melding->isTeLaat() => 'te laat gemeld '.$this->datum($melding->gemeld_op),
                $melding->isGemeld() => 'gemeld '.$this->datum($melding->gemeld_op),
                $melding->isTeLaat() => 'OPEN, termijn verstreken',
                default => 'open',
            };

            $md .= '  - '.strtoupper($melding->grondslag).' '.$melding->label()
                .($melding->artikel() ? " ({$melding->artikel()})" : '')
                .': '.($melding->uiterlijk_op ? 'uiterlijk '.$this->datum($melding->uiterlijk_op) : 'geen termijn')
                .", {$stand}\n";
        }

        return $md;
    }

    private function incidentenAfwijkingen(): int
    {
        $md = "# Incidenten & afwijkingen\n\n## Incidenten\n\n";
        $incidenten = Incident::with('meldingen')->orderByDesc('gemeld_op')->get();
        foreach ($incidenten as $i) {
            $md .= "### {$i->titel} ({$i->status}, ernst {$i->ernst})\n\n"
                ."- Gemeld: {$this->datum($i->gemeld_op)} door {$this->persoonId($i->gemeld_door_id)}\n"
                .($i->kennisname_op ? "- Kennisname: {$this->datum($i->kennisname_op)}\n" : '')
                .($i->gesloten_op ? "- Gesloten: {$this->datum($i->gesloten_op)}\n" : '')
                .$this->externeMeldplicht($i)
                .$this->bewijs('incident', $i->id)
                .($i->omschrijving ? "\n".$this->blok($i->omschrijving)."\n" : '')."\n";
        }

        $md .= "## Afwijkingen (§10.2)\n\n";
        $afwijkingen = Afwijking::with(['grondoorzaken', 'maatregelen'])->orderByDesc('id')->get();
        foreach ($afwijkingen as $a) {
            $md .= "### Afwijking #{$a->id} — {$a->bron} ({$a->status})\n\n"
                ."- Eigenaar: {$this->persoonId($a->eigenaar_id)}\n"
                .($a->omschrijving ? "\n".$this->blok($a->omschrijving)."\n\n" : '');
            foreach ($a->grondoorzaken ?? [] as $g) {
                $md .= '- Grondoorzaak'.($g->methodiek ? " ({$g->methodiek})" : '').": {$this->cel($g->omschrijving)}\n";
            }
            foreach ($a->maatregelen ?? [] as $c) {
                $md .= "- Maatregel ({$c->status}".($c->deadline ? ", deadline {$this->datum($c->deadline)}" : '')."): {$this->cel($c->omschrijving)}\n";
            }
            $md .= $this->bewijs('afwijking', $a->id)."\n";
        }

        $this->schrijf('06-incidenten-en-afwijkingen.md', $md);

        return $incidenten->count() + $afwijkingen->count();
    }

    private function audits(): int
    {
        $md = "# Audits (§9.2)\n\n";
        $programmas = Auditprogramma::with(['auditplannen.rondes.bevindingen.maatregel', 'dekkingen.auditobject'])
            ->orderByDesc('start_datum')->get();

        foreach ($programmas as $p) {
            $md .= "## Programma: {$p->naam} ({$p->status})\n\n"
                .ucfirst($p->aard)." · cyclus {$p->venster()}\n\n";
            foreach ($p->auditplannen->sortBy('jaar') as $plan) {
                $md .= "### Jaarplan {$plan->jaar} ({$plan->status})\n\n";
                foreach ($plan->rondes as $ronde) {
                    $uitvoerder = $ronde->isIntern()
                        ? $this->persoonId($ronde->auditor_gebruiker_id)
                        : $this->vrijeNaam($ronde->extern_auditor_naam);
                    // De dekkingsvlag hoort in het bewijs: een lezer moet kunnen
                    // zien waarom een uitgevoerde ronde niet in de matrix staat.
                    $md .= "- **{$ronde->typeLabel()}** ({$ronde->status})"
                        .($ronde->gepland_op ? ", gepland {$this->datum($ronde->gepland_op)}" : '')
                        .", uitvoerder: {$uitvoerder}"
                        .($ronde->telt_mee_voor_dekking ? '' : ' — telt niet mee voor de dekking')
                        ."\n"
                        .$this->bewijs('auditronde', $ronde->id, '  ');
                    foreach ($ronde->bevindingen as $b) {
                        $ref = $b->maatregel ? "A.{$b->maatregel->annex_a_referentie}: " : '';
                        $md .= "  - Bevinding ({$b->type}, {$b->status}): {$ref}{$this->cel($b->omschrijving)}\n"
                            .$this->bewijs('bevinding', $b->id, '    ');
                    }
                }
                $md .= "\n";
            }

            $md .= $this->dekking($p);
        }

        $this->schrijf('07-audits.md', $md);

        return $programmas->count();
    }

    /**
     * De dekkingsafspraak per auditobject (§9.2.2): met welk interval een
     * clausule of maatregel aan de beurt komt en in welk programmajaar de reeks
     * begint.
     *
     * Zonder dit is uit de export niet op te maken of het programma H4-H10 en
     * Bijlage A überhaupt afdekt — je ziet dan alleen de rondes die zijn
     * uitgevoerd, niet waartegen die dekking is afgesproken.
     */
    private function dekking(Auditprogramma $programma): string
    {
        $dekkingen = $programma->dekkingen
            ->sortBy(fn (AuditprogrammaDekking $d) => [$d->auditobject?->soort, $d->auditobject?->volgorde]);

        if ($dekkingen->isEmpty()) {
            return '';
        }

        return "### Dekking\n\n".$this->tabel(
            ['Object', 'Soort', 'Interval (jaren)', 'Start in programmajaar', 'Toelichting'],
            $dekkingen->map(fn (AuditprogrammaDekking $d) => [
                trim(($d->auditobject?->clausule_nummer ?? '').' '.($d->auditobject?->titel ?? '')) ?: '—',
                $d->auditobject?->soort ?? '—',
                $d->interval_jaren,
                $d->gepland_start_programmajaar,
                $d->toelichting ?? '—',
            ])
        )."\n";
    }

    private function metenReview(): int
    {
        $md = "# Meten & directiebeoordeling\n\n## KPI's\n\n";
        $kpis = KpiDefinitie::with(['metingen' => fn ($q) => $q->orderByDesc('gemeten_op')->limit(1)])
            ->orderBy('sleutel')->get();
        $md .= $this->tabel(['Sleutel', 'Naam', 'Fase', 'Eenheid', 'Laatste meting'],
            $kpis->map(function (KpiDefinitie $k) {
                $m = $k->metingen->first();
                $waarde = $m ? "{$m->teller}/{$m->noemer} ({$this->datum($m->gemeten_op)})" : '—';

                return [$k->sleutel, $k->naam, $k->fase ?? '—', $k->eenheid ?? '—', $waarde];
            }));

        // De onderbouwing onder de cijfers (plan 00e). De overzichtstabel
        // hierboven blijft de laatste stand tonen; hier staat wát er gemeten
        // wordt, hóé, en waartegen — dat is wat §9.1 vraagt en wat een
        // ontvangend ISMS nodig heeft om de reeks voort te zetten.
        //
        // Een blok per KPI en geen bredere tabel: met alle velden erbij zou het
        // een twaalfkoloms tabel worden met een alinea proza in één cel.
        $md .= "\n### Meetaanpak per KPI\n\n";
        foreach ($kpis as $k) {
            $md .= "#### {$this->cel($k->sleutel)} — {$this->cel($k->naam)}\n\n"
                ."- Fase: {$k->fase} · eenheid: {$k->eenheid} · richting: {$this->richtingLabel($k)}\n"
                ."- Bron: {$this->bronLabel($k)}\n"
                ."- Norm: {$this->normLabel($k)}\n"
                ."- Definitieversie: {$k->definitie_versie}"
                .($k->actief ? '' : ' · **inactief: wordt niet meer gemeten**')."\n\n"
                .$this->blok($k->berekeningswijze)."\n\n";
        }

        // De historie náást de laatste stand, niet in plaats daarvan: die eerste
        // tabel hoort de huidige waarde te tonen. Zonder historie is §9.1 in een
        // ontvangend ISMS niet aan te tonen, en meethistorie is achteraf niet te
        // reconstrueren — daarom gaat hij mee (plan 00d §4).
        $metingen = Meting::with('kpiDefinitie')->orderBy('kpi_definitie_id')->orderBy('gemeten_op')->get();
        if ($metingen->isNotEmpty()) {
            $md .= "\n### Meethistorie\n\n".$this->tabel(
                // 'Vastgelegd door' is leeg bij een berekend meetpunt. Zonder die
                // kolom leest een handmatig ingevoerd cijfer in de export als een
                // machinaal berekend cijfer, en dat is een andere bewijssoort.
                // 'Norm toen' en 'Def.' erbij (plan 00e §5): daarmee is elke
                // meetrij te interpreteren zonder de definitie erbij te halen —
                // ook nadat de norm of de berekening is bijgesteld.
                ['Sleutel', 'Gemeten op', 'Teller', 'Noemer', 'Norm toen', 'Def.', 'Vastgelegd door', 'Toelichting'],
                $metingen->map(fn (Meting $m) => [
                    $m->kpiDefinitie?->sleutel ?? '—',
                    $this->datum($m->gemeten_op),
                    $m->teller,
                    $m->noemer,
                    $this->normPaar($m->streefwaarde, $m->signaalwaarde, $m->kpiDefinitie?->eenheid),
                    'v'.$m->definitie_versie,
                    $this->persoonId($m->ingevoerd_door_id),
                    $m->toelichting ?? '—',
                ])
            );
        }

        $md .= "\n## Directiebeoordelingen\n\n";
        $sessies = Reviewsessie::with(['agendapunten', 'besluiten.verbeteracties'])->orderByDesc('datum')->get();
        foreach ($sessies as $s) {
            $md .= "### {$this->datum($s->datum)} ({$s->status})\n\n"
                ."- Deelnemers: {$this->vrijeNaam($s->deelnemers)}\n\n";
            foreach ($s->agendapunten ?? [] as $a) {
                $md .= "- _{$a->categorie}_: {$this->cel($a->samenvatting)}\n";
            }
            foreach ($s->besluiten ?? [] as $b) {
                $md .= "- **Besluit:** {$this->cel($b->omschrijving)}\n";
                // Zonder de verbeteracties loopt een besluit in de export dood:
                // je leest wát is besloten, maar niet of er iets mee gebeurd is.
                foreach ($b->verbeteracties ?? [] as $v) {
                    $md .= "  - Verbeteractie ({$v->status}"
                        .($v->deadline ? ", deadline {$this->datum($v->deadline)}" : '')
                        .($v->voltooid_op ? ", voltooid {$this->datum($v->voltooid_op)}" : '')
                        ."), eigenaar {$this->persoonId($v->eigenaar_id)}: {$this->cel($v->omschrijving)}\n";
                }
            }
            $md .= $this->bewijs('reviewsessie', $s->id)."\n";
        }

        $this->schrijf('08-meten-en-directiebeoordeling.md', $md);

        return $kpis->count();
    }

    private function bewustzijnTraining(): int
    {
        $modules = Trainingsmodule::orderBy('titel')->get();
        $md = "# Bewustzijn & training\n\n## Trainingsmodules\n\n".$this->tabel(
            ['Titel', 'Geldigheid (mnd)', 'Actief'],
            $modules->map(fn (Trainingsmodule $t) => [$t->titel, $t->geldigheidsduur_maanden ?? '—', $this->janee($t->actief)])
        );

        $doelgroepen = Doelgroep::orderBy('naam')->get();
        if ($doelgroepen->isNotEmpty()) {
            $md .= "\n## Doelgroepen\n\n".$this->tabel(['Naam', 'Omschrijving'],
                $doelgroepen->map(fn (Doelgroep $d) => [$d->naam, $d->omschrijving ?? '—']));
        }

        // Persoonsgebonden bewijs van bewustzijn (§7.2 d, §7.3). Zonder dit begint
        // een ontvangend ISMS op nul: voltooiingen en bevestigingen zijn
        // onherroepelijk vastgelegd en juist daarom bruikbaar als bewijs.
        //
        // Let op bij wijzigen: de tekst in de else-tak en wat deze tak werkelijk
        // exporteert moeten kloppen. Ze liepen eerder uiteen — de melding
        // beloofde deelnamegegevens achter de vlag terwijl die nergens werden
        // geschreven, en dat is erger dan een stil gat: de lezer denkt dat hij
        // het compleet heeft. ExporteerIsmsTest toetst beide takken als paar.
        if ($this->metPersoon) {
            $md .= "\n## Trainingsdeelname\n\n".$this->deelname();
            $md .= "\n## Leesbevestigingen\n\n".$this->leesbevestigingen();
        } else {
            $md .= "\n> Individuele trainingsdeelname en leesbevestigingen (persoonsgebonden) "
                ."zijn weggelaten. Draai met `--met-persoonsgegevens` om die op te nemen.\n";
        }

        $this->schrijf('09-bewustzijn-en-training.md', $md);

        return $modules->count();
    }

    /**
     * Wie welke training heeft afgerond. Verlopen voltooiingen blijven staan:
     * dat is de herhaalcyclus en geen ruis — eruit filteren zou de historie
     * wegpoetsen die A.6.3 juist vraagt.
     */
    private function deelname(): string
    {
        $voltooiingen = Trainingsvoltooiing::with('module')
            ->orderBy('trainingsmodule_id')
            ->orderByDesc('voltooid_op')
            ->get();

        if ($voltooiingen->isEmpty()) {
            return "_Geen voltooiingen geregistreerd._\n";
        }

        return $this->tabel(['Module', 'Persoon', 'Voltooid', 'Verloopt', 'Bron'],
            $voltooiingen->map(fn (Trainingsvoltooiing $v) => [
                $v->module?->titel ?? '—',
                $this->persoonId($v->gebruiker_id),
                $this->datum($v->voltooid_op),
                $this->datum($v->verloopt_op),
                $v->bron ?? '—',
            ]));
    }

    /**
     * Bevestigingen mét het versienummer erbij. Dat veld is het halve punt: een
     * bevestiging op v1.2 zegt niets over v3.0, en zonder die kolom leest de
     * export alsof iedereen het huidige beleid heeft gezien.
     */
    private function leesbevestigingen(): string
    {
        $bevestigingen = Leesbevestiging::with('versie.document')
            ->orderByDesc('bevestigd_op')
            ->get();

        if ($bevestigingen->isEmpty()) {
            return "_Geen leesbevestigingen geregistreerd._\n";
        }

        return $this->tabel(['Document', 'Versie', 'Persoon', 'Bevestigd op'],
            $bevestigingen->map(fn (Leesbevestiging $l) => [
                $l->versie?->document?->titel ?? '—',
                $l->versie?->versienummer ?? '—',
                $this->persoonId($l->gebruiker_id),
                $this->datum($l->bevestigd_op),
            ]));
    }

    private function integraties(): int
    {
        $adapters = IntegratieAdapter::orderBy('naam')->get();
        $md = "# Integraties\n\n".$this->tabel(['Naam', 'Type', 'Status', 'Laatste synchronisatie'],
            $adapters->map(fn (IntegratieAdapter $a) => [
                $a->naam, $a->type ?? '—', $a->status ?? '—', $this->datum($a->laatste_synchronisatie_op),
            ]));

        $this->schrijf('10-integraties.md', $md);

        return $adapters->count();
    }

    // --- Helpers ------------------------------------------------------------

    private function schrijf(string $bestand, string $inhoud): void
    {
        File::put($this->basis.'/'.$bestand, $inhoud);
    }

    /** Persoon uit een gebruiker-FK: initialen + rol, of de volle naam met de vlag. */
    private function persoonId(?int $id): string
    {
        $g = $id === null ? null : $this->gebruikers->get($id);
        if ($g === null) {
            return '—';
        }
        if ($this->metPersoon) {
            return $g->naam;
        }

        // Eén definitie van het schema, gedeeld met de schermkopieën (12h §8).
        // Zonder de accountstatus: die hoort bij een register waar het beleggen
        // van werk de vraag is, niet bij een integrale export.
        return $g->anoniemLabel(metStatus: false);
    }

    /** Vrije-tekst naamveld (bv. goedgekeurd_door): alleen tonen met de vlag. */
    private function vrijeNaam(?string $waarde): string
    {
        if ($waarde === null || $waarde === '') {
            return '—';
        }

        return $this->metPersoon ? $waarde : '— (naam weggelaten)';
    }

    private function datum($waarde): string
    {
        if ($waarde === null || $waarde === '') {
            return '—';
        }

        return $waarde instanceof \DateTimeInterface ? $waarde->format('d-m-Y') : (string) $waarde;
    }

    private function janee(?bool $waarde): string
    {
        return $waarde === null ? '—' : ($waarde ? 'ja' : 'nee');
    }

    /**
     * De effectieve maatregelclassificatie per control (plan 04d fase 4), met
     * een markering waar de organisatie zelf iets heeft vastgesteld.
     *
     * Dat onderscheid is de hele reden dat dit in de export staat: een auditor
     * moet kunnen zien wat is overgenomen en wat een eigen beoordeling is.
     * Alleen actieve dimensies, zodat een uitgeschakelde dimensie hier net zo
     * afwezig is als in het scherm.
     */
    private function classificatie(SoaRegel $regel): string
    {
        $kenmerken = $regel->kenmerken();
        $regels = '';

        foreach (Maatregelkenmerken::dimensies() as $sleutel => $dimensie) {
            if (empty($kenmerken[$sleutel])) {
                continue;
            }

            $regels .= "  - {$dimensie['label']}: ".$this->cel(implode(', ', $kenmerken[$sleutel]))."\n";
        }

        if ($regels === '') {
            return '';
        }

        $herkomst = match (true) {
            $regel->wijktAfVanUitgangspunt() => 'eigen vaststelling, afwijkend van het meegeleverde uitgangspunt',
            $regel->heeftEigenClassificatie() => 'eigen vaststelling',
            default => 'meegeleverd uitgangspunt, niet door de organisatie vastgesteld',
        };

        return "- Classificatie ({$herkomst}):\n".$regels;
    }

    /** Inline cel: pipes/regeleindes weg zodat een Markdown-tabel/regel heel blijft. */
    private function cel(?string $waarde): string
    {
        return str_replace(['|', "\r\n", "\n", "\r"], ['\|', ' ', ' ', ' '], trim((string) $waarde));
    }

    /** Welke kant op is goed — zonder deze vlag leest een dalende ratio als achteruitgang. */
    private function richtingLabel(KpiDefinitie $k): string
    {
        return $k->richting === 'omlaag' ? 'omlaag (lager is beter)' : 'omhoog (hoger is beter)';
    }

    /**
     * Rekent de applicatie deze KPI uit, of voedt iemand hem met de hand? Het
     * ontvangende ISMS moet weten welke reeksen het zelf moet blijven vullen.
     * De meetbron-sleutel gaat mee tussen haakjes: buiten dit product zegt hij
     * niets, erbinnen is hij direct herbruikbaar.
     */
    private function bronLabel(KpiDefinitie $k): string
    {
        return $k->meetbron === null
            ? 'handmatig ingevoerd (de applicatie rekent deze KPI niet uit)'
            : "berekend door de applicatie (`{$this->cel($k->meetbron)}`)";
    }

    /**
     * De norm mét haar status (plan 00e §3). Een streefwaarde zonder die status
     * exporteren is de fout waarvoor `streefwaarde_vastgesteld_op` bestaat,
     * verplaatst naar het ontvangende systeem: een meegeleverd vóórstel wordt
     * daar dan als vastgesteld beleid gelezen.
     */
    private function normLabel(KpiDefinitie $k): string
    {
        if ($k->streefwaarde === null) {
            return 'geen streefwaarde vastgesteld';
        }

        $waarden = $this->normPaar($k->streefwaarde, $k->signaalwaarde, $k->eenheid);

        return $k->streefwaarde_vastgesteld_op === null
            ? "{$waarden} — **voorstel, niet vastgesteld** (telt nergens mee)"
            : "{$waarden} — vastgesteld op {$this->datum($k->streefwaarde_vastgesteld_op)}";
    }

    /** Streef- en signaalwaarde horen bij elkaar; los van elkaar zeggen ze niets. */
    private function normPaar(?float $streef, ?float $signaal, ?string $eenheid): string
    {
        if ($streef === null) {
            return '—';
        }

        $schrijf = fn (float $w) => match ($eenheid) {
            'dagen' => number_format($w, 1, ',', '.').' dagen',
            'aantal' => (string) (int) round($w),
            default => rtrim(rtrim(number_format($w, 1, ',', '.'), '0'), ',').'%',
        };

        return $signaal === null
            ? $schrijf($streef)
            : $schrijf($streef).' / '.$schrijf($signaal);
    }

    /** Meerregelig tekstblok als citaat, ongemoeid qua inhoud. */
    private function blok(?string $waarde): string
    {
        $tekst = trim((string) $waarde);

        return $tekst === '' ? '' : '> '.str_replace("\n", "\n> ", $tekst);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, string|int|null>>  $rijen
     */
    private function tabel(array $headers, iterable $rijen): string
    {
        $md = '| '.implode(' | ', $headers)." |\n|".str_repeat('---|', count($headers))."\n";
        foreach ($rijen as $rij) {
            $md .= '| '.implode(' | ', array_map(fn ($v) => $this->cel((string) $v), $rij))." |\n";
        }

        return $md;
    }
}
