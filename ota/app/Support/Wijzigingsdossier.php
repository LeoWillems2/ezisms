<?php

namespace App\Support;

use App\Models\Sjabloonstap;
use App\Models\Taak;
use App\Models\Wijziging;
use App\Models\Wijzigingssjabloon;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * De enige plek waar een wijzigingsdossier zijn stappenreeks krijgt en waar de
 * dossierstatus verschuift (implementatie/15 §3, §4, §7).
 *
 * De reeksmechaniek zelf zit in `Stappenreeks`; dit blok levert alleen de lijst
 * stappen en beslist wat er ná een afkeuring gebeurt — dat laatste kent de
 * engine bewust niet.
 */
final class Wijzigingsdossier
{
    /**
     * Kiest het sjabloon, zet de voorgenomen datum en bouwt de reeks.
     *
     * `gepland_op` wordt hier gevuld en niet pas na goedkeuring (§2b): de
     * stapdeadlines volgen uit deze datum plus de offsets uit het sjabloon, en
     * `Stappenreeks::start()` eist een deadline per stap.
     */
    public static function neemInBehandeling(
        Wijziging $wijziging,
        Wijzigingssjabloon $sjabloon,
        CarbonInterface $geplandOp,
    ): void {
        if ($wijziging->status !== 'aangemeld') {
            throw new RuntimeException('Alleen een aangemelde wijziging kan in behandeling worden genomen.');
        }

        if ($sjabloon->stappen->isEmpty()) {
            throw new RuntimeException('Dit sjabloon heeft geen stappen.');
        }

        // Soort en zwaarte worden gekopieerd, niet afgeleid: het sjabloon mag
        // later wijzigen, het dossier moet vasthouden wat er gold.
        $wijziging->update([
            'wijzigingssjabloon_id' => $sjabloon->id,
            'soort' => $sjabloon->soort,
            'zwaarte' => $sjabloon->zwaarte,
            'gepland_op' => $geplandOp,
            'status' => 'in_behandeling',
        ]);

        Stappenreeks::start(
            $wijziging,
            'wijzigingsbeheer',
            $sjabloon->stappen->map(fn (Sjabloonstap $stap) => [
                'titel' => $stap->titel,
                'omschrijving' => $stap->omschrijving,
                'volgorde' => $stap->volgorde,
                'deadline' => self::deadlineVoor($geplandOp, $stap),
                'eigenaar_id' => $stap->standaard_eigenaar_id,
                // Het enige punt waar een staptype de engine raakt.
                'vraagt_uitkomst' => $stap->staptype === 'goedkeuring',
                // Meekopiëren en niet naslaan (§17): wat er bij de start gold,
                // blijft gelden. `sjabloonstap_id` gaat mee als herkomst.
                'extra' => [
                    'sjabloonstap_id' => $stap->id,
                    'staptype' => $stap->staptype,
                    'bewijs_verplicht' => $stap->bewijs_verplicht,
                    'bij_afkeuren_terug_naar' => $stap->bij_afkeuren_terug_naar,
                ],
            ])->all(),
        );
    }

    /**
     * Verschuift de planning en daarmee de deadlines van de stappen die nog
     * moeten gebeuren (§4).
     *
     * Voltooide stappen houden hun deadline: die is historie, en de vertraging
     * die eruit volgt is meetdata. Een verschuiving die een deadline in het
     * verleden legt is toegestaan — `isms:verloop-taken` mag dat gewoon zien.
     *
     * De deadlines schuiven mee met het **verschil**, ze worden niet opnieuw uit
     * het sjabloon berekend. Twee redenen: de offsets in het sjabloon kunnen
     * inmiddels gewijzigd zijn en horen een lopend dossier niet te raken (§17),
     * en zo hoeft `deadline_offset_dagen` niet óók op de taak bevroren te worden.
     */
    public static function verzetPlanning(Wijziging $wijziging, CarbonInterface $geplandOp): void
    {
        if ($wijziging->isAfgerond()) {
            throw new RuntimeException('Een afgerond dossier verzet je niet meer.');
        }

        $vorige = $wijziging->gepland_op;

        $wijziging->update(['gepland_op' => $geplandOp]);

        if ($vorige === null) {
            return;
        }

        $verschil = $vorige->diffInDays($geplandOp, absolute: false);

        if ($verschil === 0) {
            return;
        }

        foreach ($wijziging->stappen() as $stap) {
            if ($stap->status === 'voltooid') {
                continue;
            }

            $stap->update(['deadline' => $stap->deadline->copy()->addDays($verschil)]);
        }
    }

    /**
     * Verwerkt de uitkomst van een goedkeuringsstap. De engine zet de reeks bij
     * een afkeuring stil; wát er dan gebeurt is een besluit van dit blok (§7).
     */
    public static function legUitkomstVast(Wijziging $wijziging, Taak $stap, string $uitkomst): void
    {
        Stappenreeks::legUitkomstVast($stap, $uitkomst);

        if ($uitkomst !== 'afgekeurd') {
            self::werkStatusBij($wijziging);

            return;
        }

        $terugNaar = $stap->bij_afkeuren_terug_naar;

        if ($terugNaar === null) {
            $wijziging->update(['status' => 'afgewezen']);

            return;
        }

        Stappenreeks::heropenVanaf($wijziging, $terugNaar);
    }

    /**
     * Zet het dossier op `uitgevoerd` zodra de uitvoerstap achter de rug is.
     * Aangeroepen na elke stapafronding vanaf het dossierscherm.
     */
    public static function werkStatusBij(Wijziging $wijziging): void
    {
        if ($wijziging->status !== 'in_behandeling') {
            return;
        }

        $uitvoerstappen = $wijziging->stappen()
            ->filter(fn (Taak $stap) => $stap->staptype === 'uitvoeren');

        if ($uitvoerstappen->isEmpty() || $uitvoerstappen->contains(fn (Taak $s) => $s->status !== 'voltooid')) {
            return;
        }

        $wijziging->update([
            'status' => 'uitgevoerd',
            // De feitelijke uitvoerdatum: wanneer de laatste uitvoerstap is
            // afgerond, niet de geplande datum. Het verschil tussen die twee is
            // precies wat een auditor wil kunnen zien.
            'uitgevoerd_op' => $uitvoerstappen->max('voltooid_op') ?? Carbon::now(),
        ]);
    }

    /** Reden waarom het dossier nu niet gesloten kan worden, of null. */
    public static function belemmeringVoorSluiten(Wijziging $wijziging): ?string
    {
        if ($wijziging->isAfgerond()) {
            return 'Dit dossier is al afgerond.';
        }

        $stappen = $wijziging->stappen();

        // Zonder reeks is er niets om te evalueren, en — erger — is de
        // terugvalplancontrole nooit langsgekomen: die hangt aan de uitvoerstap.
        // Een leeg dossier lezen als "alles klaar" liet een wijziging sluiten
        // zonder vangnet, en dat is precies wat A.8.32 f) verbiedt.
        if ($stappen->isEmpty()) {
            return 'Dit dossier heeft nog geen stappen. Neem het eerst in behandeling.';
        }

        $open = $stappen->filter(fn (Taak $stap) => $stap->status !== 'voltooid');

        if ($open->isNotEmpty()) {
            return 'Er staan nog '.$open->count().' stap(pen) open.';
        }

        return null;
    }

    /**
     * Haalt een dossier terug uit een eindstand — een correctie, geen normale
     * stap in de cyclus.
     *
     * Zonder deze route is een fout onherstelbaar: een dossier dat ten onrechte
     * gesloten is blijft read-only, en een gap-signaal dat eruit volgt (zoals
     * "uitgevoerd zonder terugvalplan") is dan nooit meer weg te werken. Zelfde
     * patroon als het heractiveren van een beëindigde leverancier (blok 9 §3)
     * en het heropenen van een taak: de vorige stand blijft dankzij de
     * append-only audit trail herleidbaar.
     */
    public static function heropen(Wijziging $wijziging): void
    {
        if (! $wijziging->isAfgerond()) {
            throw new RuntimeException('Dit dossier loopt nog; heropenen kan alleen vanuit een eindstand.');
        }

        $wijziging->update([
            // Terug naar waar het dossier vandaan kwam. Zonder reeks is dat
            // 'aangemeld', zodat de CISO alsnog een sjabloon en een datum kiest —
            // precies de stap die was overgeslagen.
            'status' => $wijziging->stappen()->isEmpty() ? 'aangemeld' : 'in_behandeling',
            // De evaluatie beschreef een afsluiting die wordt teruggedraaid. Hem
            // laten staan zou een lopend dossier tonen dat al "geslaagd" heet.
            // `uitgevoerd_op` blijft wél staan: dat is een feit, geen oordeel.
            'geslaagd' => null,
            'teruggedraaid' => false,
            'evaluatie' => null,
        ]);

        // Stond de reeks al op uitgevoerd, dan hoort het dossier daar weer heen.
        self::werkStatusBij($wijziging->refresh());
    }

    /** Legt de evaluatie vast en sluit het dossier (A.8.32 g). */
    public static function sluit(
        Wijziging $wijziging,
        bool $geslaagd,
        bool $teruggedraaid,
        string $evaluatie,
    ): void {
        $belemmering = self::belemmeringVoorSluiten($wijziging);

        if ($belemmering !== null) {
            throw new RuntimeException($belemmering);
        }

        $wijziging->update([
            'geslaagd' => $geslaagd,
            'teruggedraaid' => $teruggedraaid,
            'evaluatie' => $evaluatie,
            'status' => 'gesloten',
        ]);
    }

    private static function deadlineVoor(CarbonInterface $geplandOp, Sjabloonstap $stap): Carbon
    {
        return Carbon::parse($geplandOp)->copy()->addDays($stap->deadline_offset_dagen);
    }
}
