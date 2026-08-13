<?php

namespace App\Console\Commands;

use App\Mail\TrainingVerloopt;
use App\Models\Asset;
use App\Models\Beleidsversie;
use App\Models\Gebruiker;
use App\Models\SoaRegel;
use App\Models\Taak;
use App\Models\Taaksjabloon;
use App\Models\Trainingsmodule;
use App\Support\NotificatieDispatcher;
use App\Support\TaakPlanner;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Genereert terugkerende taken uit sjablonen, plus de twee **tijdgestuurde**
 * signalen uit blok 3 en 4 (implementatie/07 §4 en §7).
 *
 * De veldgestuurde signalen (scope-herziening, risico-herbeoordeling) lopen
 * juist via observers: daar wijzigt een datum, hier verstrijkt tijd.
 */
class GenereerTaken extends Command
{
    protected $signature = 'isms:genereer-taken';

    protected $description = 'Maakt terugkerende taken aan en signaleert achterstallige reviews';

    /**
     * Termijn waarna een uitgereikt bedrijfsmiddel of een SoA-beoordeling
     * opnieuw bekeken hoort te worden. Staat NIET in de norm — gekozen omdat
     * een certificeringscyclus jaarlijks is.
     */
    private const HERBEOORDELINGSTERMIJN_MAANDEN = 12;

    /** Reactietijd op een achterstallig signaal; ook een eigen keuze. */
    private const REACTIETERMIJN_DAGEN = 14;

    /**
     * Termijn waarbinnen een gepubliceerd beleid gelezen en bevestigd hoort te
     * zijn. Staat NIET in de norm — gekozen omdat een leestermijn zonder einde
     * geen termijn is (implementatie/05 §8).
     */
    private const LEESTERMIJN_DAGEN = 30;

    public function handle(): int
    {
        $aantal = $this->uitSjablonen()
            + $this->achterstalligeRetouren()
            + $this->achterstalligeSoaBeoordelingen()
            + $this->openstaandeLeesbevestigingen()
            + $this->trainingHerinneringen();

        $this->info("{$aantal} taak/taken aangemaakt.");

        return self::SUCCESS;
    }

    private function uitSjablonen(): int
    {
        $aantal = 0;

        foreach (Taaksjabloon::where('actief', true)->get() as $sjabloon) {
            $laatste = $sjabloon->taken()->orderByDesc('deadline')->first();

            // De volgende deadline volgt uit de laatste taak, niet uit vandaag:
            // anders schuift de cyclus op bij elke gemiste uitvoering.
            $deadline = $laatste
                ? $sjabloon->volgendeDeadlineNa($laatste->deadline)
                : Carbon::today()->addDays($sjabloon->aanmaken_dagen_vooraf);

            if ($deadline === null || $deadline->isAfter(Carbon::today()->addDays($sjabloon->aanmaken_dagen_vooraf))) {
                continue;
            }

            try {
                Taak::create([
                    'taaksjabloon_id' => $sjabloon->id,
                    'titel' => $sjabloon->naam,
                    'omschrijving' => $sjabloon->omschrijving,
                    'eigenaar_id' => $sjabloon->standaard_eigenaar_id,
                    'deadline' => $deadline,
                    'gekoppeld_blok_naam' => $sjabloon->bron_blok,
                ]);
                $aantal++;
            } catch (QueryException $e) {
                // De unique op (taaksjabloon_id, deadline) maakt dit commando
                // idempotent: een dubbele run botst hier en slaat over.
                if (! str_contains($e->getMessage(), 'taken_sjabloon_deadline_unique')) {
                    throw $e;
                }
            }
        }

        return $aantal;
    }

    /** Annex A 5.11: bedrijfsmiddelen die te lang uitstaan. */
    private function achterstalligeRetouren(): int
    {
        $grens = Carbon::today()->subMonths(self::HERBEOORDELINGSTERMIJN_MAANDEN);
        $aantal = 0;

        $assets = Asset::whereHas('toewijzingen', fn ($q) => $q
            ->whereNull('geretourneerd_op')
            ->whereDate('toegewezen_op', '<=', $grens))->get();

        foreach ($assets as $asset) {
            TaakPlanner::planVoorEntiteit(
                $asset,
                'asset-retourcontrole',
                'Retourcontrole: '.$asset->naam,
                Carbon::today()->addDays(self::REACTIETERMIJN_DAGEN),
                'asset-classificatie',
                $asset->responsible_id,
            );
            $aantal++;
        }

        return $aantal;
    }

    /** Maatregelen die van toepassing zijn maar te lang niet zijn herzien. */
    private function achterstalligeSoaBeoordelingen(): int
    {
        $grens = Carbon::today()->subMonths(self::HERBEOORDELINGSTERMIJN_MAANDEN);
        $aantal = 0;

        $regels = SoaRegel::with('maatregel')
            ->where('van_toepassing', true)
            ->whereDate('laatst_beoordeeld_op', '<=', $grens)
            ->get();

        foreach ($regels as $regel) {
            TaakPlanner::planVoorEntiteit(
                $regel,
                'soa-herbeoordeling',
                'SoA herbeoordelen: A.'.$regel->maatregel->annex_a_referentie,
                Carbon::today()->addDays(self::REACTIETERMIJN_DAGEN),
                'risico-soa',
            );
            $aantal++;
        }

        return $aantal;
    }

    /**
     * A.5.1: erkenning van beleid door relevant personeel. Eén taak per
     * gebruiker per actieve versie (implementatie/05 §8).
     *
     * De begrenzing zit in de query, niet in een `if` in de loop: alleen
     * documenten met leesbevestigingsplicht leveren überhaupt taken op. Zonder
     * dat filter zou dit het kruisproduct van álle actieve versies en álle
     * gebruikers zijn, en krijgt iedereen tientallen taken over procedures die
     * zijn werk niet raken.
     */
    private function openstaandeLeesbevestigingen(): int
    {
        $versies = Beleidsversie::with('document')
            ->where('status', 'actief')
            ->whereHas('document', fn ($q) => $q->where('leesbevestiging_vereist', true))
            ->get();

        if ($versies->isEmpty()) {
            return 0;
        }

        $aantal = 0;

        foreach ($versies as $versie) {
            // De doelgroep is afdelingsgericht (implementatie/05 §6): alleen
            // actieve gebruikers wier afdeling het document heeft aangevinkt.
            $doelgroepIds = $versie->document->doelgroepGebruikerIds();

            // Eerst opruimen: krimpt de doelgroep (afdeling van het document af,
            // of een gebruiker wisselt van afdeling), dan horen openstaande
            // taken van wie er niet meer bij hoort te verdwijnen. Zonder dit
            // blijft er een taak staan die niemand nog kan of hoeft af te ronden.
            $this->trekVerweesdeLeesbevestigingenIn($versie, $doelgroepIds);

            if ($doelgroepIds === []) {
                continue;
            }

            // De leestermijn loopt vanaf publicatie, niet vanaf vandaag: een
            // taak die elke nacht opnieuw 30 dagen krijgt verloopt nooit.
            $deadline = ($versie->gepubliceerd_op ?? Carbon::today())
                ->copy()
                ->addDays(self::LEESTERMIJN_DAGEN);

            $bevestigd = $versie->bevestigingen()->pluck('gebruiker_id')->all();

            foreach ($doelgroepIds as $gebruikerId) {
                if (in_array($gebruikerId, $bevestigd, true)) {
                    continue;
                }

                TaakPlanner::planVoorEntiteit(
                    $versie,
                    'beleid-leesbevestiging',
                    'Lezen en bevestigen: '.$versie->auditOmschrijving(),
                    $deadline,
                    'beleid-maatregelbeheer',
                    $gebruikerId,
                    perEigenaar: true,
                );
                $aantal++;
            }
        }

        return $aantal;
    }

    /**
     * Annex A 6.3: (bijna) verlopen of nog openstaande trainingen. Eén
     * openstaande taak per actieve module per doelgroeplid (implementatie/10 §9).
     *
     * De verplichting is afgeleid (er is geen statusveld), dus dit loopt via een
     * idempotente sweep en niet via een observer. `TaakPlanner` met `perEigenaar`
     * verzet dezelfde taak bij een nieuwe cyclus in plaats van er een tweede
     * naast te zetten, en ruimt hem op bij een `null`-deadline.
     */
    private function trainingHerinneringen(): int
    {
        $aantal = 0;

        foreach (Trainingsmodule::where('actief', true)->get() as $module) {
            foreach ($module->doelgroepGebruikerIds() as $gebruikerId) {
                $nieuwste = $module->voltooiingen()
                    ->where('gebruiker_id', $gebruikerId)
                    ->orderByDesc('voltooid_op')
                    ->orderByDesc('id')
                    ->first();

                // Nooit voltooid ⇒ direct openstaand (deadline vandaag). Een
                // eenmalige module die is voltooid (verloopt_op null) ⇒ geen taak
                // (null-deadline ruimt een bestaande op). Anders is de deadline de
                // verloopdatum: een bijna-verloop-signaal.
                $deadline = match (true) {
                    $nieuwste === null => Carbon::today(),
                    $nieuwste->verloopt_op === null => null,
                    default => $nieuwste->verloopt_op,
                };

                $taak = TaakPlanner::planVoorEntiteit(
                    $module,
                    'training-herinnering',
                    'Training afronden: '.$module->titel,
                    $deadline,
                    'bewustzijn-training',
                    $gebruikerId,
                    perEigenaar: true,
                );

                if ($deadline !== null) {
                    $aantal++;
                }

                // Alleen mailen wanneer daadwerkelijk een níeuwe herinnering
                // ontstaat — niet elke dag dat de sweep dezelfde openstaande taak
                // verzet (implementatie/14 §5). Zo krijgt de betrokkene per
                // verloopcyclus één mail, samenvallend met de idempotente
                // taakplanning.
                if ($taak?->wasRecentlyCreated) {
                    $betrokkene = Gebruiker::find($gebruikerId);
                    if ($betrokkene !== null) {
                        NotificatieDispatcher::verzend(
                            'training_verloopt',
                            new TrainingVerloopt($module),
                            collect([$betrokkene]),
                        );
                    }
                }
            }
        }

        return $aantal;
    }

    /**
     * Verwijdert openstaande leesbevestigingstaken voor een versie waarvan de
     * eigenaar niet (meer) in de doelgroep zit. Een lege doelgroep verwijdert ze
     * allemaal. Voltooide taken blijven staan: dat is historie.
     *
     * Bewust een directe delete en geen `deleteGeaudit()`: het gaat om
     * automatisch gegenereerde herinneringen, geen registraties. De oorzaak —
     * de gewijzigde afdeling van de gebruiker of van het document — staat al in
     * de audit trail.
     *
     * @param  list<int>  $doelgroepIds
     */
    private function trekVerweesdeLeesbevestigingenIn(Beleidsversie $versie, array $doelgroepIds): void
    {
        Taak::query()
            ->where('gekoppeld_entiteit_type', $versie->getMorphClass())
            ->where('gekoppeld_entiteit_id', $versie->getKey())
            ->where('soort', 'beleid-leesbevestiging')
            ->whereIn('status', Taak::OPENSTAAND)
            ->when($doelgroepIds !== [], fn ($q) => $q->whereNotIn('eigenaar_id', $doelgroepIds))
            ->delete();
    }
}
