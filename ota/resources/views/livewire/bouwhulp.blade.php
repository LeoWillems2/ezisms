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
                afloop werken normaal, en externe afbeeldingen of lettertypen mogen ook. Eén ding
                werkt er niet: <code>localStorage</code>, <code>sessionStorage</code> en cookies.
                Wil je tussentijds iets bewaren, doe dat dan in het geheugen van de pagina zelf.
            </flux:text>

            <div>
                <flux:button icon="arrow-down-tray" variant="primary"
                    :href="route('toetsen.bouwhulp.download')">
                    Download onQuizVoltooid.js
                </flux:button>
            </div>
        </div>
    </div>
</div>
