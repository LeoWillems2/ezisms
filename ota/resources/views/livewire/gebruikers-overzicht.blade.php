<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">Gebruikers</flux:heading>
            <flux:subheading>Accounts, rollen en toegangsstatus binnen het ISMS.</flux:subheading>
        </div>

        @if ($this->magMuteren())
            <flux:button variant="primary" icon="plus" wire:click="openUitnodigingsformulier">
                Gebruiker uitnodigen
            </flux:button>
        @endif
    </div>

    @if (session('melding'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('melding') }}" />
    @endif

    @if (session('fout'))
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ session('fout') }}" />
    @endif

    {{-- Rapportagesignalen personeelsbeveiliging (A.6). Signalen, geen blokkade. --}}
    @if ($preEmploymentGaps > 0 || $offboardingGaps > 0)
        <flux:callout variant="warning" icon="user-circle">
            <flux:callout.heading>Aandachtspunten personeelsbeveiliging</flux:callout.heading>
            <flux:callout.text>
                <ul class="mt-1 space-y-1">
                    @if ($preEmploymentGaps > 0)
                        <li>{{ $preEmploymentGaps }} actief account(s) zonder afgeronde pre-employment (NDA en/of screening).</li>
                    @endif
                    @if ($offboardingGaps > 0)
                        <li>{{ $offboardingGaps }} gedeactiveerd account(s) zonder bevestigde offboarding (accounts ingetrokken).</li>
                    @endif
                </ul>
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Uitnodigingen die verliepen zonder resultaat (01g §4). De fout meldt
         zichzelf: corrigeren alléén is reactief en wacht op een telefoontje,
         en een adres dat netjes bounct levert dat telefoontje nooit op. --}}
    @if ($verlopenUitnodigingen > 0)
        <flux:callout variant="warning" icon="envelope">
            <flux:callout.heading>Uitnodigingen zonder resultaat</flux:callout.heading>
            <flux:callout.text>
                {{ $verlopenUitnodigingen }} uitnodiging(en) zijn verlopen zonder dat het account
                in gebruik is genomen. Controleer het e-mailadres.
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Naam</flux:table.column>
            <flux:table.column>E-mail</flux:table.column>
            <flux:table.column>Rol(len)</flux:table.column>
            <flux:table.column>Afdeling</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            {{-- Niet alleen bediening: dit is de dekkingsgraad van A.8.5, en
                 daarmee het bewijs dat de maatregel is uitgerold (01d §8). --}}
            <flux:table.column>Tweefactor</flux:table.column>
            <flux:table.column>Laatst ingelogd</flux:table.column>
            <flux:table.column>Vervalt op</flux:table.column>
            <flux:table.column align="end">Acties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($gebruikers as $gebruiker)
                <flux:table.row wire:key="gebruiker-{{ $gebruiker->id }}">
                    <flux:table.cell variant="strong">{{ $gebruiker->naam }}</flux:table.cell>
                    <flux:table.cell>{{ $gebruiker->email }}</flux:table.cell>

                    <flux:table.cell>
                        @foreach ($gebruiker->rollen as $rol)
                            <flux:badge size="sm" class="mr-1">{{ $rol->naam }}</flux:badge>
                        @endforeach
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($this->magMuteren())
                            {{-- Inline te wijzigen: de afdeling stuurt welke
                                 leesbevestigingen deze gebruiker krijgt (§6).
                                 Bewust een native <select>: @selected compileert
                                 niet binnen een Flux-componenttag. --}}
                            <select wire:change="stelAfdelingIn({{ $gebruiker->id }}, $event.target.value)"
                                class="rounded-lg border border-zinc-200 bg-white py-1.5 pl-3 pr-8 text-sm dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100">
                                <option value="" @selected(is_null($gebruiker->organisatie_eenheid_id))>— geen —</option>
                                @foreach ($afdelingen as $id => $naam)
                                    <option value="{{ $id }}" @selected((int) $gebruiker->organisatie_eenheid_id === $id)>{{ $naam }}</option>
                                @endforeach
                            </select>
                        @else
                            {{ $gebruiker->afdeling?->naam ?? '—' }}
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        @php
                            $kleur = match ($gebruiker->status) {
                                'actief' => 'green',
                                'uitgenodigd' => 'amber',
                                'geblokkeerd' => 'red',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$kleur">{{ ucfirst($gebruiker->status) }}</flux:badge>

                        {{-- Waar de blokkade vandaan komt (01f §3). Dit is wat de
                             CISO nodig heeft op het moment dat hij overweegt hem
                             op te heffen; zonder die regel staat het antwoord
                             alleen in de audit trail. --}}
                        @if ($gebruiker->status === 'geblokkeerd')
                            <flux:text class="mt-1 block text-xs text-zinc-500">
                                Sinds {{ $gebruiker->geblokkeerd_op?->lokaal()->format('d-m-Y H:i') ?? 'onbekend' }}
                                @if ($gebruiker->blokkadeIsHandmatig())
                                    door {{ $gebruiker->geblokkeerdDoor->naam }}<br>
                                    {{ $gebruiker->blokkade_reden }}
                                @else
                                    automatisch, na te veel mislukte inlogpogingen
                                @endif
                            </flux:text>
                        @endif

                        {{-- Wanneer de uitnodiging is verstuurd, en of de link
                             inmiddels verlopen is (01g §4). Dezelfde vorm als de
                             blokkaderegel hierboven: het antwoord staat op de
                             plek waar de vraag opkomt. --}}
                        @if ($gebruiker->status === 'uitgenodigd' && $gebruiker->uitnodiging_verstuurd_op)
                            <flux:text class="mt-1 block text-xs text-zinc-500">
                                Verstuurd op {{ $gebruiker->uitnodiging_verstuurd_op->lokaal()->format('d-m-Y') }}
                                @if ($gebruiker->uitnodigingVerlopen())
                                    — link verlopen
                                @endif
                            </flux:text>
                        @endif

                        {{-- Een lopende adreswijziging (01h §5). Staat hier en niet
                             bij de acties, want het is een eigenschap van het
                             account: het huidige adres erboven is nog steeds het
                             werkende adres. --}}
                        @if ($gebruiker->adreswijzigingLoopt())
                            <flux:text class="mt-1 block text-xs text-zinc-500">
                                @if ($gebruiker->adreswijzigingVerlopen())
                                    Wijziging naar {{ $gebruiker->nieuw_email }} verlopen —
                                    opnieuw aanvragen
                                @else
                                    Wijziging naar {{ $gebruiker->nieuw_email }} wacht op
                                    bevestiging sinds
                                    {{ $gebruiker->nieuw_email_aangevraagd_op->lokaal()->format('d-m-Y') }}
                                @endif
                            </flux:text>
                        @endif

                        {{-- Gap-signalen personeelsbeveiliging (A.6). --}}
                        @if ($gebruiker->preEmploymentGap())
                            <flux:badge size="sm" color="amber" class="mt-1"
                                title="Ontbreekt: {{ implode(', ', $gebruiker->preEmploymentOntbrekend()) }}">
                                Pre-employment onvolledig
                            </flux:badge>
                        @endif
                        @if ($gebruiker->offboardingGap())
                            <flux:badge size="sm" color="amber" class="mt-1">Offboarding open</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($gebruiker->tweefactorActief())
                            <flux:badge size="sm" color="green">Actief</flux:badge>
                        @elseif ($gebruiker->tweefactorRespijtVerlopen())
                            <flux:badge size="sm" color="red">Respijt verlopen</flux:badge>
                        @elseif ($gebruiker->tweefactor_deadline !== null)
                            <flux:badge size="sm" color="amber">
                                Tot {{ $gebruiker->tweefactor_deadline->format('d-m-Y') }}
                            </flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Nog niet</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>{{ $gebruiker->laatst_ingelogd_op?->lokaal()->format('d-m-Y H:i') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $gebruiker->vervalt_op?->format('d-m-Y') ?? '—' }}</flux:table.cell>

                    <flux:table.cell align="end">
                        {{-- Acties volgen de statusmachine uit deelproduct 1 §3:
                             'gedeactiveerd' kent bewust geen weg terug. --}}
                        @if ($this->magMuteren())
                            <flux:button size="sm" variant="ghost" icon="identification"
                                wire:click="openDossier({{ $gebruiker->id }})">
                                Dossier
                            </flux:button>
                            @if ($gebruiker->tweefactorActief())
                                <flux:button size="sm" variant="ghost" icon="key"
                                    wire:click="tweefactorResetten({{ $gebruiker->id }})"
                                    wire:confirm="Tweefactor van {{ $gebruiker->naam }} terugzetten? Hij moet daarna opnieuw een authenticator-app koppelen.">
                                    Tweefactor resetten
                                </flux:button>
                            @endif
                            @if ($gebruiker->status === 'uitgenodigd')
                                {{-- `arrow-path` en niet `paper-airplane`: het
                                     onderscheidende woord is *opnieuw*, en het
                                     verstuurt dezelfde link nog een keer. --}}
                                <flux:button size="sm" variant="ghost" icon="arrow-path"
                                    wire:click="uitnodigingOpnieuwVersturen({{ $gebruiker->id }})">
                                    Uitnodiging opnieuw versturen
                                </flux:button>
                                {{-- Alleen hier, en dat is de hele beveiliging van
                                     01g: bij een actief account is het geen
                                     typefout meer maar iemands account, en dan is
                                     Blokkeren de weg (§8). --}}
                                <flux:button size="sm" variant="ghost" icon="pencil-square"
                                    wire:click="openCorrectieformulier({{ $gebruiker->id }})">
                                    Uitnodiging corrigeren
                                </flux:button>
                            @elseif ($gebruiker->status === 'actief')
                                {{-- Loopt er al een wijziging, dan is intrekken de
                                     enige zinnige actie: een tweede aanvraag zou de
                                     eerste stilzwijgend doden (01h §5/§8). --}}
                                @if ($gebruiker->adreswijzigingLoopt())
                                    <flux:button size="sm" variant="ghost" icon="x-mark"
                                        wire:click="trekAdreswijzigingIn({{ $gebruiker->id }})"
                                        wire:confirm="Adreswijziging naar {{ $gebruiker->nieuw_email }} intrekken? De bevestigingslink werkt daarna niet meer.">
                                        Wijziging intrekken
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" icon="envelope"
                                        wire:click="openAdreswijziging({{ $gebruiker->id }})">
                                        E-mailadres wijzigen
                                    </flux:button>
                                @endif
                                {{-- Niet op de eigen rij: wie blokkeert blijft zelf
                                     actief, en dát is de garantie dat er een CISO
                                     over is om de blokkade weer op te heffen
                                     (01f §0). De component controleert het nog een
                                     keer — het scherm is de vriendelijkheid, de
                                     component is het slot. --}}
                                @unless ($gebruiker->is(auth()->user()))
                                    <flux:button size="sm" variant="ghost" icon="lock-closed"
                                        wire:click="openBlokkadeformulier({{ $gebruiker->id }})">
                                        Blokkeren
                                    </flux:button>
                                @endunless
                                {{-- `user-minus` en niet nog een slot: deactiveren
                                     is geen zwaardere blokkade maar iets anders
                                     — de persoon gaat eruit, en er is geen weg
                                     terug. Een derde hangslot zou die twee juist
                                     op één hoop gooien. --}}
                                <flux:button size="sm" variant="ghost" icon="user-minus"
                                    wire:click="deactiveren({{ $gebruiker->id }})"
                                    wire:confirm="Weet u zeker dat u het account van {{ $gebruiker->naam }} wilt deactiveren? Dit kan niet ongedaan gemaakt worden.">
                                    Deactiveren
                                </flux:button>
                            @elseif ($gebruiker->status === 'geblokkeerd')
                                {{-- De reden in de bevestiging, maar buiten het
                                     attribuut opgebouwd: een Blade-directive
                                     compileert niet binnen een
                                     component-attribuut — dat is een
                                     stringliteral (zie Normlabels in
                                     AppServiceProvider). --}}
                                @php
                                    $bevestiging = "Blokkade van {$gebruiker->naam} opheffen? Dit account kan daarna weer inloggen."
                                        .($gebruiker->blokkadeIsHandmatig()
                                            ? "\n\nReden van de blokkade: {$gebruiker->blokkade_reden}"
                                            : '');
                                @endphp
                                {{-- `lock-open` als tegenhanger van de
                                     `lock-closed` op Blokkeren: de twee acties
                                     horen bij elkaar en zijn zo ook in één
                                     oogopslag uit elkaar te houden. --}}
                                <flux:button size="sm" variant="ghost" icon="lock-open"
                                    wire:click="blokkadeOpheffen({{ $gebruiker->id }})"
                                    wire:confirm="{{ $bevestiging }}">
                                    Blokkade opheffen
                                </flux:button>
                            @else
                                <flux:text>—</flux:text>
                            @endif
                        @else
                            <flux:text>—</flux:text>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="9">
                        <flux:text>Nog geen gebruikers.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="toontUitnodigingsformulier" class="md:w-[32rem]">
        <form wire:submit="uitnodigen" class="space-y-6">
            <div>
                <flux:heading size="lg">Gebruiker uitnodigen</flux:heading>
                <flux:subheading>De uitgenodigde stelt zelf een wachtwoord in via de mail.</flux:subheading>
            </div>

            <flux:input wire:model="naam" label="Naam" required />
            <flux:input wire:model.live.debounce.500ms="email" type="email" label="E-mailadres" required />

            {{-- De bijna-treffer (01g §5). Geen blokkade en geen bevestiging: een
                 zin die je negeert als je een externe uitnodigt, en die je redt
                 als je fruibv.nl typte. --}}
            @if ($bijnaTrefferUitnodiging)
                <flux:callout icon="question-mark-circle" variant="warning">
                    <flux:callout.text>
                        Bedoelde u <strong>&#64;{{ $bijnaTrefferUitnodiging }}</strong>? Dit adres wijkt
                        een enkel teken af van een domein dat al in gebruik is.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <x-keuzelijst wire:model.live="rolId" label="Rol" leeg="Kies een rol" required
                :opties="$rollen->pluck('naam', 'id')" />

            {{-- Administrator is de enige rol die met geen andere samengaat, en
                 dat is niet te zien aan een keuzelijst met vijf gelijke namen.
                 Hier zeggen en niet pas bij een foutmelding: het is een keuze
                 over hoe je je organisatie inricht, geen invulfout (01e §2.4). --}}
            @if ($this->kiestExclusieveRol())
                <flux:callout icon="exclamation-triangle" variant="warning">
                    <flux:callout.heading>Dit account krijgt geen toegang tot het ISMS</flux:callout.heading>
                    <flux:callout.text>
                        Een Administrator plaatst toetsbestanden en doet verder niets: geen
                        risico's, geen incidenten, geen gebruikersbeheer. De rol gaat ook niet
                        samen met een ISMS-rol op hetzelfde account. Beheert u zelf de
                        installatie, maak daar dan een tweede account voor aan.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <x-keuzelijst wire:model="afdelingId" label="Afdeling"
                leeg="— geen afdeling —" :opties="$afdelingen"
                description="Bepaalt welke leesbevestigingen deze gebruiker krijgt (A.5.1: relevant personeel). Optioneel." />

            <flux:input wire:model="vervaltOp" type="date" label="Vervalt op"
                description="Optioneel — bedoeld voor tijdelijke accounts, zoals een extern ingehuurde auditor." />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="sluitUitnodigingsformulier">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Uitnodiging versturen</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Blokkeren (01f). Een modal en geen `wire:confirm`: die levert alleen
         ja/nee, en de reden is verplicht. --}}
    <flux:modal wire:model.self="toontBlokkadeformulier" class="md:w-[32rem]">
        <form wire:submit="blokkeren" class="space-y-6">
            <div>
                <flux:heading size="lg">Account blokkeren</flux:heading>
                <flux:subheading>
                    @if ($blokkadeGebruikerId)
                        {{ $gebruikers->firstWhere('id', $blokkadeGebruikerId)?->naam }} kan hierna niet meer inloggen
                        en wordt uit lopende sessies gezet.
                    @endif
                </flux:subheading>
            </div>

            <flux:textarea wire:model="blokkadeReden" label="Reden" rows="3" required
                description="Bijvoorbeeld: vermoeden van gedeelde inloggegevens, of een lopend onderzoek. De reden komt in de audit trail en is niet zichtbaar voor de betrokkene." />

            <flux:callout icon="information-circle">
                <flux:callout.text>
                    Een blokkade heeft geen einddatum en is omkeerbaar: u heft hem zelf op zodra de
                    aanleiding weg is. Gaat iemand uit dienst, kies dan <strong>Deactiveren</strong> —
                    dat is de status die niet terugkomt.
                </flux:callout.text>
            </flux:callout>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontBlokkadeformulier', false)">Annuleren</flux:button>
                <flux:button variant="danger" type="submit">Blokkeren</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Uitnodiging corrigeren (01g). Een modal en geen `wire:confirm`: er zijn
         twee invoervelden nodig, en het neveneffect — de oude link sterft — is
         precies wat de CISO wil weten en niet vanzelf begrijpt. --}}
    <flux:modal wire:model.self="toontCorrectieformulier" class="md:w-[32rem]">
        <form wire:submit="corrigeren" class="space-y-6">
            <div>
                <flux:heading size="lg">Uitnodiging corrigeren</flux:heading>
                <flux:subheading>
                    Herstel een typefout in de naam of het e-mailadres. Kan zolang de uitnodiging
                    nog niet is geaccepteerd.
                </flux:subheading>
            </div>

            <flux:input wire:model="correctieNaam" label="Naam" required />
            <flux:input wire:model.live.debounce.500ms="correctieEmail" type="email" label="E-mailadres" required />

            @if ($bijnaTrefferCorrectie)
                <flux:callout icon="question-mark-circle" variant="warning">
                    <flux:callout.text>
                        Bedoelde u <strong>&#64;{{ $bijnaTrefferCorrectie }}</strong>? Dit adres wijkt
                        een enkel teken af van een domein dat al in gebruik is.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:callout icon="information-circle">
                <flux:callout.text>
                    De uitnodiging die naar het oude adres is gestuurd, werkt hierna niet meer.
                    Er gaat direct een nieuwe uitnodiging naar het gecorrigeerde adres.
                </flux:callout.text>
            </flux:callout>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontCorrectieformulier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Corrigeren en opnieuw versturen</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- E-mailadres wijzigen bij een actief account (01h). Lijkt op de
         correctiemodal hierboven maar doet het tegenovergestelde: daar verandert
         het adres direct, hier pas na bevestiging op het nieuwe adres. De teksten
         zeggen dat expliciet, want het verschil is niet af te leiden uit de
         knop. --}}
    <flux:modal wire:model.self="toontAdreswijziging" class="md:w-[32rem]">
        <form wire:submit="wijzigAdres" class="space-y-6">
            <div>
                <flux:heading size="lg">E-mailadres wijzigen</flux:heading>
                <flux:subheading>
                    Voor een account dat in gebruik is. Het adres wijzigt pas als op het
                    nieuwe adres wordt bevestigd.
                </flux:subheading>
            </div>

            <flux:input wire:model.live.debounce.500ms="adreswijzigingEmail" type="email"
                label="Nieuw e-mailadres" required />

            @if ($bijnaTrefferAdreswijziging)
                <flux:callout icon="question-mark-circle" variant="warning">
                    <flux:callout.text>
                        Bedoelde u <strong>&#64;{{ $bijnaTrefferAdreswijziging }}</strong>? Dit adres
                        wijkt een enkel teken af van een domein dat al in gebruik is.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:callout icon="information-circle">
                <flux:callout.text>
                    Er gaat een bevestigingslink naar het nieuwe adres en een melding naar het
                    huidige adres. Tot er is bevestigd blijft het huidige adres werken.
                    Wachtwoord, tweede factor en lopende sessies veranderen niet.
                </flux:callout.text>
            </flux:callout>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontAdreswijziging', false)">Annuleren</flux:button>
                {{-- Uitgeschakeld zolang het verzoek loopt: twee keer drukken gaf
                     twee aanvragen, en de component vangt dat nu af — maar het
                     versturen van de mail duurt lang genoeg om er twee keer op te
                     drukken, en dan komen er twee berichten. --}}
                <flux:button variant="primary" type="submit"
                    wire:loading.attr="disabled" wire:target="wijzigAdres">
                    Wijziging aanvragen
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Personeelsdossier (A.6): pre-employment + offboarding. --}}
    <flux:modal wire:model.self="toontDossier" class="md:w-[34rem]">
        <form wire:submit="slaDossierOp" class="space-y-6">
            <div>
                <flux:heading size="lg">Personeelsdossier</flux:heading>
                <flux:subheading>Pre-employment en offboarding ({{ $norm->naam_kort }} A.6). Leg de bewijzen vast onder Bewijs &amp; audit trail.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:heading size="sm">Pre-employment</flux:heading>
                <flux:input wire:model="ndaGetekendOp" type="date" label="Geheimhoudingsverklaring (NDA) getekend op"
                    description="Getekende NDA of arbeidsovereenkomst met geheimhoudingsbeding (A.6.2/A.6.6)." />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-keuzelijst wire:model="screeningType" label="Screeningstype"
                        leeg="— nog niet —" :opties="$screeningTypes" />
                    <flux:input wire:model="screeningOp" type="date" label="Screening uitgevoerd op"
                        description="VOG of referentiecheck (A.6.1)." />
                </div>
            </div>

            <flux:separator />

            <div class="space-y-4">
                <flux:heading size="sm">Offboarding</flux:heading>
                <flux:input wire:model="accountsIngetrokkenOp" type="date" label="Accounts ingetrokken op"
                    description="Checklist-bevestiging dat op de laatste werkdag de accounts zijn ingetrokken (A.6.5 / A.5.11)." />
            </div>

            <flux:separator />

            <flux:text class="text-sm text-zinc-500">
                Gekoppelde bewijsstukken: {{ $dossierBewijsAantal }}. Koppel de getekende NDA, de VOG/referentiecheck
                en de offboarding-checklist als bewijsstuk aan deze gebruiker via Bewijs &amp; audit trail.
            </flux:text>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('toontDossier', false)">Annuleren</flux:button>
                <flux:button variant="primary" type="submit">Opslaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
