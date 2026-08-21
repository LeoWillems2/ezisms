<div class="flex h-full w-full flex-1 flex-col gap-6">
    @include('partials.bewustzijn-subnav')

    <div>
        <flux:heading size="xl">Bouwhulp: een toets maken</flux:heading>
        <flux:subheading>Hoe je een eigen toets bouwt en aan het ISMS koppelt.</flux:subheading>
    </div>

    <div class="max-w-3xl space-y-6">
        <div class="space-y-3">
            <flux:heading size="lg">Wat een toets is</flux:heading>
            <flux:text>
                Een toets is een op zichzelf staande HTML-pagina. Plaatsen doet de
                <strong>Administrator</strong> via Toetsbestanden; jij levert het bestand aan.
                De inhoud — lesstof, vragen, opmaak —
                bepaal je helemaal zelf; het ISMS bemoeit zich alleen met de <em>uitslag</em>.
                Je koppelt de toets vervolgens aan een trainingsmodule bij
                <flux:link :href="route('toetsen.uitzetten')" wire:navigate>Toetsen uitzetten</flux:link>.
            </flux:text>
        </div>

        <div class="space-y-3">
            <flux:heading size="lg">Hoe de koppeling werkt</flux:heading>
            <flux:text>
                Bij het uitzetten reikt het ISMS elke deelnemer een persoonlijke URL uit met een
                token in de querystring: <code>?callback=&lt;token&gt;</code>. Die token hoort bij
                precies één deelnemer en één module.
            </flux:text>
            <ul class="list-disc space-y-2 pl-5">
                <li>
                    <flux:text>
                        Zodra de deelnemer de toets afrondt, roept je pagina
                        <code>onQuizVoltooid(score, total, passed)</code> aan — <strong>bij zowel
                        slagen als zakken</strong>, zodat ook een mislukte poging wordt geregistreerd.
                    </flux:text>
                </li>
                <li>
                    <flux:text>
                        Die functie leest de token uit de URL en doet een <code>POST</code> naar
                        <code>/toetsen/callback/&lt;token&gt;</code> op dezelfde host. Er wordt nooit
                        een volledige URL uit de querystring gebruikt, dus de pagina kan niet als
                        doorgeefluik naar een andere host dienen.
                    </flux:text>
                </li>
                <li>
                    <flux:text>
                        Bij een geslaagde, geverifieerde poging legt het ISMS een onherroepelijke
                        voltooiing vast en stuurt eventueel een <code>redirect_url</code> terug.
                        Zonder token (<code>?callback=</code> ontbreekt) is de toets vrijblijvend en
                        registreert hij niets — handig om de toets los te testen.
                    </flux:text>
                </li>
            </ul>
        </div>

        <div class="space-y-3">
            <flux:heading size="lg">De functie toevoegen</flux:heading>
            <flux:text>
                Download de kant-en-klare functie hieronder en plak de inhoud onderin je toets binnen
                een <code>&lt;script&gt;</code>-tag. Roep hem aan vanuit je eigen afrondingslogica —
                de meegeleverde helper <code>triggerQuizVoltooid(score, total, passed)</code> mag je
                daarvoor gebruiken. Je hoeft verder niets te configureren; de token komt vanzelf uit
                de URL die het ISMS uitreikt.
            </flux:text>
        </div>

        <div class="space-y-3">
            <flux:heading size="lg">Waar je toets in draait</flux:heading>
            <flux:text>
                Het ISMS serveert je toets uit in een <em>afgeschermde omgeving</em>: de pagina heeft
                geen toegang tot de gegevens, de sessie of de opslag van het ISMS. Voor een gewone
                toets merk je daar niets van — scripts, formulieren, meldingen en het doorlinken na
                afloop werken normaal. Twee dingen werken niet:
                <code>localStorage</code>, <code>sessionStorage</code> en cookies (bewaar wat je
                nodig hebt in het geheugen van de pagina zelf), en alles wat de pagina bij een
                andere website zou ophalen — zie hieronder.
            </flux:text>

            <div>
                <flux:button icon="arrow-down-tray" variant="primary"
                    :href="route('toetsen.bouwhulp.download')">
                    Download onQuizVoltooid.js
                </flux:button>
            </div>
        </div>

        <div class="space-y-3">
            <flux:heading size="lg">De toets moet één bestand zijn</flux:heading>
            <flux:text>
                Geen externe scripts, stylesheets, lettertypen of afbeeldingen. Alle CSS en
                JavaScript staan <em>in</em> het bestand; afbeeldingen neem je op als
                <code>data:</code>-URI. Dus geen Tailwind-CDN en geen Google Fonts, maar platte
                CSS en systeemlettertypen.
            </flux:text>
            <flux:text>
                De reden is niet netheid. De deelnemers zijn medewerkers van deze organisatie;
                haalt de pagina iets bij een andere partij op, dan gaat hun IP-adres daarheen.
                Het ISMS blokkeert die verzoeken, dus een toets met externe bronnen ziet er bij
                de deelnemer kapot uit. Bij het plaatsen waarschuwt het ISMS de Administrator
                als het er toch in staat.
            </flux:text>
            <flux:text>
                Begin met het skelet hieronder: een werkende toets met drie vragen, zonder één
                externe bron. Laat je je toets door een AI schrijven, geef deze opdracht dan mee:
            </flux:text>

            <flux:callout>
                <flux:text>
                    Maak één zelfstandig HTML-bestand. Geen externe scripts, stylesheets,
                    lettertypen of afbeeldingen: alle CSS en JavaScript inline in het bestand,
                    afbeeldingen als data:-URI. Geen Tailwind-CDN en geen Google Fonts; gebruik
                    platte CSS en systeemlettertypen.
                </flux:text>
            </flux:callout>

            <div>
                <flux:button icon="arrow-down-tray"
                    :href="route('toetsen.bouwhulp.skelet')">
                    Download het skelet
                </flux:button>
            </div>
        </div>
    </div>
</div>
