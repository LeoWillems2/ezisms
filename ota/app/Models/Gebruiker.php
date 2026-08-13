<?php

namespace App\Models;

use App\Listeners\RegistreerMislukteLoginpoging;
use App\Models\Concerns\Auditeerbaar;
use App\Support\Adreswijziging;
use App\Support\Uitnodiging;
use Database\Factories\GebruikerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class Gebruiker extends Authenticatable
{
    /** @use HasFactory<GebruikerFactory> */
    use Auditeerbaar, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $table = 'gebruikers';

    /** @var list<string> */
    protected $fillable = [
        'naam',
        'email',
        'wachtwoord',
        'status',
        'geblokkeerd_op',
        'geblokkeerd_door_id',
        'blokkade_reden',
        'vervalt_op',
        'organisatie_eenheid_id',
        'nda_getekend_op',
        'screening_type',
        'screening_op',
        'accounts_ingetrokken_op',
        'email_geverifieerd_op',
        'uitnodiging_verstuurd_op',
        'nieuw_email',
        'nieuw_email_aangevraagd_op',
    ];

    /**
     * Toegestane screeningsvormen (A.6.1): een VOG of een aannemelijk gemaakte
     * referentiecheck.
     *
     * @var array<string, string>
     */
    public const SCREENING_TYPES = [
        'vog' => 'VOG',
        'referentiecheck' => 'Referentiecheck',
    ];

    /** @var list<string> */
    protected $hidden = [
        'wachtwoord',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_geverifieerd_op' => 'datetime',
            'uitnodiging_verstuurd_op' => 'datetime',
            'nieuw_email_aangevraagd_op' => 'datetime',
            'laatst_ingelogd_op' => 'datetime',
            'geblokkeerd_op' => 'datetime',
            'vervalt_op' => 'date',
            'nda_getekend_op' => 'date',
            'screening_op' => 'date',
            'accounts_ingetrokken_op' => 'date',
            'tweefactor_deadline' => 'date',
            'wachtwoord' => 'hashed',
        ];
    }

    /**
     * Laravel's auth-laag vraagt standaard om een 'password'-kolom; het
     * domeinmodel gebruikt 'wachtwoord' (conventies §3).
     */
    public function getAuthPassword(): string
    {
        return $this->wachtwoord;
    }

    /**
     * De afdeling waar de gebruiker bij hoort — een organisatie-eenheid van
     * type 'afdeling'. Nullable: CISO, auditor of externen hebben er soms geen,
     * en vallen dan buiten elke afdelingsgerichte leesbevestiging (§6).
     */
    public function afdeling(): BelongsTo
    {
        return $this->belongsTo(OrganisatieEenheid::class, 'organisatie_eenheid_id');
    }

    public function rolToewijzingen(): HasMany
    {
        return $this->hasMany(RolToewijzing::class, 'gebruiker_id');
    }

    /** Gekoppelde bewijsstukken (getekende NDA, VOG, offboarding-checklist). */
    public function bewijsKoppelingen(): MorphMany
    {
        return $this->morphMany(BewijsKoppeling::class, 'entiteit', 'entiteit_type', 'entiteit_id');
    }

    // --- Personeelsbeveiliging (A.6) — gap-signalen, geen harde blokkade ----

    public function screeningLabel(): ?string
    {
        return $this->screening_type !== null ? self::SCREENING_TYPES[$this->screening_type] : null;
    }

    /** Getekende NDA én een uitgevoerde screening: de pre-employment-controles. */
    public function preEmploymentCompleet(): bool
    {
        return $this->nda_getekend_op !== null && $this->screening_op !== null;
    }

    /**
     * Wat er nog ontbreekt aan pre-employment, als leesbare labels (voor het
     * signaal in de lijst). Leeg = compleet.
     *
     * @return list<string>
     */
    public function preEmploymentOntbrekend(): array
    {
        $ontbreekt = [];
        if ($this->nda_getekend_op === null) {
            $ontbreekt[] = 'getekende NDA';
        }
        if ($this->screening_op === null) {
            $ontbreekt[] = 'screening (VOG/referentiecheck)';
        }

        return $ontbreekt;
    }

    /**
     * Een actief account waarvan de pre-employment-controles nog niet rond zijn.
     * Bewust alleen bij 'actief': bij 'uitgenodigd' loopt het traject nog, en
     * bij vertrokken accounts is het historie.
     */
    public function preEmploymentGap(): bool
    {
        return $this->status === 'actief' && ! $this->preEmploymentCompleet();
    }

    /**
     * Een gedeactiveerd account waarvan de offboarding (accounts ingetrokken)
     * nog niet is bevestigd.
     */
    public function offboardingGap(): bool
    {
        return $this->status === 'gedeactiveerd' && $this->accounts_ingetrokken_op === null;
    }

    public function rollen(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_toewijzingen', 'gebruiker_id', 'rol_id')
            ->withPivot(['toegekend_door_id', 'toegekend_op']);
    }

    public function loginpogingen(): HasMany
    {
        return $this->hasMany(Loginpoging::class, 'gebruiker_id');
    }

    /**
     * De awareness-doelgroepen waar deze gebruiker lid van is (blok 10). Een
     * ander begrip dan de afdeling (`afdeling()`): doelgroepen lopen dwars door
     * afdelingen heen.
     */
    public function doelgroepen(): BelongsToMany
    {
        return $this->belongsToMany(Doelgroep::class, 'doelgroep_gebruiker', 'gebruiker_id', 'doelgroep_id');
    }

    public function heeftRol(string $naam): bool
    {
        return $this->rollen->contains('naam', $naam);
    }

    /**
     * Alleen actieve accounts zijn kiesbaar als eigenaar of toegewezene. Een
     * uitgenodigd, geblokkeerd of gedeactiveerd account hoort niet als nieuwe
     * keuze in een selectielijst te verschijnen — je wijst geen werk toe aan
     * iemand die niet kan inloggen.
     */
    public function scopeSelecteerbaar(Builder $query): Builder
    {
        return $query->where('status', 'actief');
    }

    /**
     * Gebruikers voor een keuzelijst: alle actieve accounts, plus de al gekozen
     * gebruiker(s) via $behoud. Dat behoud voorkomt dat een bestaande —
     * inmiddels niet-actieve — eigenaar stilzwijgend uit het formulier valt en
     * bij opslaan ongemerkt verandert (zie resources/views/components/keuzelijst).
     *
     * @param  int|string|array<int|string|null>|null  $behoud  al gekozen id('s)
     * @return Collection<int, static>
     */
    public static function kiesbaar(int|string|array|null $behoud = null): Collection
    {
        $behoudIds = collect(is_array($behoud) ? $behoud : [$behoud])
            ->reject(fn ($id) => $id === null || $id === '')
            ->map(fn ($id) => (int) $id)
            ->all();

        return static::query()
            ->where(fn (Builder $q) => $q->selecteerbaar()
                ->when($behoudIds !== [], fn (Builder $sub) => $sub->orWhereIn('id', $behoudIds)))
            ->orderBy('naam')
            ->get();
    }

    public function isActief(): bool
    {
        return $this->status === 'actief';
    }

    // --- Blokkade (implementatie/01f) ---------------------------------------

    /**
     * Blokkeren wegens een security-incident, of automatisch na te veel
     * mislukte inlogpogingen.
     *
     * Geen einddatum: de weg terug is `heffBlokkadeOp()`, een bewuste
     * CISO-handeling. Een blokkade die vanzelf afloopt verdwijnt op een moment
     * dat niemand heeft beoordeeld of de aanleiding weg is (01f §0).
     *
     * `$door` is null bij de automatische blokkade uit
     * {@see RegistreerMislukteLoginpoging}; dat onderscheid
     * stuurt de melding op het loginscherm.
     *
     * Eén `update()` en dus één auditregel met status én reden erin — geen twee
     * regels die apart gelezen moeten worden.
     */
    public function blokkeer(?self $door, ?string $reden = null): void
    {
        $this->update([
            'status' => 'geblokkeerd',
            'geblokkeerd_op' => now(),
            'geblokkeerd_door_id' => $door?->id,
            'blokkade_reden' => $reden,
        ]);
    }

    /** Terug naar actief; dát het geblokkeerd stond blijft in de audit trail. */
    public function heffBlokkadeOp(): void
    {
        $this->update([
            'status' => 'actief',
            'geblokkeerd_op' => null,
            'geblokkeerd_door_id' => null,
            'blokkade_reden' => null,
        ]);
    }

    /**
     * Naam en e-mailadres corrigeren van een account dat nog niet in gebruik is,
     * en in dezelfde beweging de uitnodigingslink intrekken (01g §0).
     *
     * Die rotatie is geen bijvangst maar de kern: het uitnodigingstoken hangt
     * aan de wachtwoord-hash ({@see Uitnodiging::token()}), dus zonder nieuwe
     * hash houdt de ontvanger van het foute adres zijn werkende link — en
     * verstuurt `uitnodigingOpnieuwVersturen()` daarna diezelfde link naar het
     * nieuwe adres.
     *
     * Eén methode en geen twee losse updates, zodat een latere aanroeper de
     * rotatie niet kan vergeten. De `hashed`-cast maakt er een hash van, net als
     * bij het uitnodigen zelf; in de audit trail levert dit één regel `gewijzigd`
     * op met oude en nieuwe naam en adres, want `wachtwoord` staat in
     * {@see self::auditUitgesloten()}.
     */
    public function corrigeerUitnodiging(string $naam, string $email): void
    {
        $this->update([
            'naam' => $naam,
            'email' => $email,
            'wachtwoord' => Str::random(32),
        ]);
    }

    /**
     * Verstuurd, maar de link is verlopen zonder dat er iets mee gebeurd is
     * (01g §4).
     *
     * De drempel is de geldigheidsduur van de link zelf en geen nieuw getal in
     * een configuratiebestand: op het moment dat de link verloopt, is er niets
     * meer wat vanzelf nog goed kan komen.
     */
    public function uitnodigingVerlopen(): bool
    {
        return $this->status === 'uitgenodigd'
            && $this->uitnodiging_verstuurd_op !== null
            && $this->uitnodiging_verstuurd_op->addDays(Uitnodiging::GELDIGHEID_DAGEN)->isPast();
    }

    /**
     * Vraagt een adreswijziging aan voor een account dat in gebruik is
     * (implementatie/01h §3).
     *
     * Het adres zelf verandert hier **niet**: dat gebeurt pas in
     * {@see self::bevestigAdreswijziging()}. Dat is de hele opzet — een typefout
     * mag een werkend account niet onbereikbaar maken, en onbereikbaar betekent
     * hier geen inlog, geen wachtwoordherstel en geen notificaties.
     *
     * Het wachtwoord roteert bewust **niet**, anders dan bij
     * {@see self::corrigeerUitnodiging()}. Daar moest de uitnodigingslink sterven
     * en is de rotatie de kern; hier zou ze de rechtmatige gebruiker buitensluiten
     * bij een handeling die niets met zijn wachtwoord te maken heeft.
     */
    public function vraagAdreswijzigingAan(string $email): void
    {
        $this->update([
            'nieuw_email' => $email,
            'nieuw_email_aangevraagd_op' => now(),
        ]);
    }

    /**
     * Het aangevraagde adres wordt het adres.
     *
     * Pas hier verhuist `email_geverifieerd_op` mee: zolang de wijziging loopt is
     * het huidige adres nog steeds het werkende adres en nog steeds het adres
     * waarvan bewezen is dat er post aankomt.
     */
    public function bevestigAdreswijziging(): void
    {
        $this->update([
            'email' => $this->nieuw_email,
            'email_geverifieerd_op' => now(),
            'nieuw_email' => null,
            'nieuw_email_aangevraagd_op' => null,
        ]);
    }

    public function trekAdreswijzigingIn(): void
    {
        $this->update([
            'nieuw_email' => null,
            'nieuw_email_aangevraagd_op' => null,
        ]);
    }

    public function adreswijzigingLoopt(): bool
    {
        return $this->nieuw_email !== null;
    }

    /** Aangevraagd, maar de link is verlopen zonder dat er iets mee gebeurd is. */
    public function adreswijzigingVerlopen(): bool
    {
        return $this->adreswijzigingLoopt()
            && $this->nieuw_email_aangevraagd_op
                ->copy()->addDays(Adreswijziging::GELDIGHEID_DAGEN)->isPast();
    }

    /** Door een mens gezet, niet door de teller op mislukte inlogpogingen. */
    public function blokkadeIsHandmatig(): bool
    {
        return $this->status === 'geblokkeerd' && $this->geblokkeerd_door_id !== null;
    }

    public function geblokkeerdDoor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'geblokkeerd_door_id');
    }

    /**
     * Alleen een actief account mag daadwerkelijk inloggen; de overige
     * statussen leveren een specifieke melding op (zie implementatie/05 §3).
     */
    public function magInloggen(): bool
    {
        return $this->isActief();
    }

    public function initials(): string
    {
        return static::initialenVan($this->naam);
    }

    /**
     * Initialen uit een losse naam. Nodig waar er geen account (meer) is: de
     * audit trail bewaart de naam als momentopname, juist zodat hij leesbaar
     * blijft als het account verdwijnt (implementatie/06 §3b).
     */
    public static function initialenVan(string $naam): string
    {
        return Str::of($naam)
            ->explode(' ')
            ->map(fn (string $deel) => Str::of($deel)->substr(0, 1))
            ->implode('');
    }

    /**
     * Het anonimiseringsschema voor documenten die het pand verlaten: initialen
     * plus rol, bijvoorbeeld "DW (Management)".
     *
     * Eén definitie voor `isms:exporteer` en voor de schermkopieën
     * (implementatie/12h §8). Twee kopieën van dit schema lopen uit elkaar
     * zonder dat het opvalt — het verschil is dan één document met een naam erin
     * dat er precies zo uitziet als de rest.
     *
     * De rol hoort erbij en is niet optioneel: zonder rol is "DW" in een
     * auditdocument een letterreeks zonder betekenis, en de vraag die zo'n
     * document beantwoordt is bij welke *functie* iets belegd is.
     *
     * @param  bool  $metStatus  neemt een niet-actief account als kenmerk mee. Dat
     *                           is geen persoonsgegeven maar een bevinding: werk
     *                           dat belegd is bij iemand die niet meer kan werken,
     *                           is feitelijk onbelegd.
     */
    public function anoniemLabel(bool $metStatus = true): string
    {
        $kenmerken = [$this->rollen->pluck('naam')->implode(', ') ?: 'onbekend'];

        if ($metStatus && ! $this->isActief()) {
            $kenmerken[] = $this->status;
        }

        return $this->initials().' ('.implode(', ', $kenmerken).')';
    }

    /**
     * De starter-kit-views spreken de ingelogde gebruiker als $user->name aan.
     * Deze accessor houdt die views werkend zonder de domeinnaamgeving los te laten.
     */
    public function getNameAttribute(): string
    {
        return $this->naam;
    }

    public function auditBlok(): string
    {
        return 'identity-access';
    }

    /**
     * Beveiligingscontrole, geen opmaak: zonder deze uitsluiting belandt de
     * wachtwoordhash leesbaar in een tabel die de Auditor mag inzien.
     *
     * Hetzelfde geldt voor het 2FA-secret en de herstelcodes (01d §4). Ze zijn
     * versleuteld met `APP_KEY` en dus niet direct bruikbaar, maar het is wel
     * het geheim zelf in een register dat op omloop is ontworpen — de Auditor
     * mag de trail inzien én exporteren.
     *
     * Prettig neveneffect: een `updated` die alleen 2FA-kolommen raakt valt
     * daarmee weg in `filterAuditVelden()` en levert geen logregel op. De
     * betekenisvolle regels komen van de Fortify-gebeurtenissen (01d §5).
     *
     * @return list<string>
     */
    public function auditUitgesloten(): array
    {
        return ['wachtwoord', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
    }

    public function tweefactorActief(): bool
    {
        return $this->hasEnabledTwoFactorAuthentication();
    }

    /** Respijt verlopen: er is een deadline én die ligt in het verleden. */
    public function tweefactorRespijtVerlopen(): bool
    {
        return $this->tweefactor_deadline !== null
            && $this->tweefactor_deadline->isPast();
    }
}
