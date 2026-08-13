/**
 * onQuizVoltooid — koppeling tussen een toets en het ISMS.
 *
 * TWEE STAPPEN. De tweede wordt het vaakst vergeten, en dan lijkt alles te
 * werken: de deelnemer maakt de toets, ziet zijn uitslag, krijgt geen enkele
 * foutmelding — en er komt niets binnen. Zijn taak blijft openstaan.
 *
 *   1. Plak dit hele blok onderin je toets-HTML, binnen een <script>-tag.
 *   2. ROEP ONQUIZVOLTOOID AAN op de plek waar je zelf de uitslag berekent:
 *
 *          const geslaagd = score / total >= 0.8;
 *
 *          if (geslaagd) {
 *              toonGeslaagdScherm();
 *              onQuizVoltooid(score, total, true);
 *          } else {
 *              toonGezaktScherm();
 *              onQuizVoltooid(score, total, false);   // ← ook deze!
 *          }
 *
 * Ook bij zakken aanroepen, want het ISMS registreert dát er geoefend is en
 * niet alleen wie geslaagd is. Een gezakte poging telt als poging; de deelnemer
 * mag het opnieuw proberen.
 *
 *     score  = aantal goede antwoorden (getal)
 *     total  = totaal aantal vragen (getal)
 *     passed = geslaagd? (true / false)
 *
 * De functie die je aanroept heet `onQuizVoltooid`. `triggerQuizVoltooid`
 * hieronder is optioneel en doet hetzelfde; kies er één en verzin er geen derde.
 * Een aanroep van een naam die niet bestaat, valt nergens op.
 *
 * Het ISMS reikt de deelnemer een URL uit met ?callback=<token>. Zonder die
 * token is de toets vrijblijvend en wordt er niets geregistreerd — zo kun je de
 * toets ook los, zonder ISMS, gebruiken of testen.
 *
 * WAAR JE TOETS IN DRAAIT. Het ISMS serveert je toets uit in een afgeschermde
 * omgeving: de pagina kan niet bij de gegevens, de sessie of de opslag van het
 * ISMS. Scripts, formulieren, meldingen (`alert`), het doorlinken na afloop en
 * externe afbeeldingen of lettertypen werken gewoon. Eén ding werkt niet:
 * `localStorage`, `sessionStorage` en cookies. Wil je tussentijds iets
 * bewaren, houd dat dan in het geheugen van de pagina zelf.
 */

// Optionele helper: roept onQuizVoltooid aan als die bestaat. Handig als je de
// koppeling los wilt kunnen laten (bijv. lokaal testen zonder ISMS).
function triggerQuizVoltooid(score, total, passed) {
    if (typeof window.onQuizVoltooid === 'function') {
        window.onQuizVoltooid(score, total, passed);
    } else if (typeof onQuizVoltooid === 'function') {
        onQuizVoltooid(score, total, passed);
    }
}

function onQuizVoltooid(score, total, passed) {
    const token = new URLSearchParams(window.location.search).get('callback');

    // Zonder ?callback=<token> is de toets vrijblijvend: niets registreren.
    if (!token) {
        return;
    }

    // De terugmeld-URL wordt hier zelf samengesteld: de querystring bevat alleen
    // de token, geen volledige URL. Zo kan deze pagina nooit als doorgeefluik
    // naar een willekeurige host dienen; de callback blijft op dezelfde host.
    const callbackUrl = '/toetsen/callback/' + encodeURIComponent(token);

    fetch(callbackUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            score: score,
            total: total,
            passed: passed
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (passed) {
            alert('Je uitslag is opgeslagen in het ISMS.');
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            }
        }
    })
    .catch(error => {
        console.error('Fout bij verzenden uitslag:', error);
        // Zichtbaar voor de deelnemer: anders denkt iemand dat de uitslag is
        // opgeslagen terwijl dat niet zo is.
        alert('Let op: je uitslag kon niet worden opgeslagen in het ISMS. '
            + 'Probeer het later opnieuw of neem contact op met de beheerder.');
    });
}
