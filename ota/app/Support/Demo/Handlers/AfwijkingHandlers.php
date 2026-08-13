<?php

namespace App\Support\Demo\Handlers;

use App\Models\Afwijking;
use App\Models\CorrigerendeMaatregel;
use App\Models\Effectiviteitstoets;
use App\Models\Grondoorzaak;
use App\Models\Incident;
use App\Support\Afwijkingafsluiting;
use App\Support\Demo\DemoFixtureFout;
use App\Support\Demo\Handelt;
use App\Support\Demo\Klok;
use App\Support\Demo\Simulatie;
use App\Support\Incidentmelding;
use App\Support\Meldplicht;
use App\Support\TaakPlanner;

/**
 * De CAPA-cyclus: incident → afwijking → grondoorzaak → corrigerende maatregel →
 * effectiviteitstoets → sluiten.
 *
 * Melden is een handeling van de medewerker die het ziet (`uitvoeren`); de rest
 * van de cyclus is werk van de CISO (`muteren`). Sluiten loopt via
 * `Afwijkingafsluiting` — de enige plek die dat mag, en die de voorwaarden uit
 * §10.2 afdwingt in plaats van ze te documenteren.
 */
final class AfwijkingHandlers
{
    /** @return array<string, callable> */
    public function register(): array
    {
        return [
            'incident' => $this->incident(...),
            'afwijking' => $this->afwijking(...),
            'corrigerende_maatregel_voltooien' => $this->maatregelVoltooien(...),
            'effectiviteitstoets' => $this->effectiviteitstoets(...),
            'afwijking_sluiten' => $this->afwijkingSluiten(...),
        ];
    }

    private function incident(array $g, int $maand, Simulatie $sim): void
    {
        $melder = $sim->gebruiker($g['gemeld_door']);

        $incident = Handelt::als($melder)
            ->mits('heeft-niveau', ['incident-afwijkingenbeheer', 'uitvoeren'])
            ->bij("M{$maand}/incident/{$g['sleutel']}")
            ->doe(function () use ($g, $melder, $sim) {
                $incident = Incident::create([
                    'titel' => $g['titel'],
                    'omschrijving' => $g['omschrijving'],
                    'gemeld_door_id' => $melder->id,
                    'gemeld_op' => now(),
                    // Kennisname ligt vóór de melding in het ISMS — dat is het
                    // wettelijke ankerpunt en het hele punt van dat veld.
                    'kennisname_op' => now()->subHours((int) ($g['kennisname_uren_eerder'] ?? 0)),
                    'ernst' => $g['ernst'],
                    'status' => 'gemeld',
                    'gekoppeld_risico_id' => isset($g['gekoppeld_risico'])
                        ? $sim->fixtures()->model($g['gekoppeld_risico'])->id : null,
                    'gekoppeld_asset_id' => isset($g['gekoppeld_asset'])
                        ? $sim->fixtures()->model($g['gekoppeld_asset'])->id : null,
                ]);

                Incidentmelding::meldAanCiso($incident);

                return $incident;
            });

        $sim->fixtures()->onthoud($g['sleutel'], $incident);

        // De klok mee vooruit: een incident dat na zes dagen is opgelost, hoort
        // ook zes dagen later in de audit trail te staan.
        $sim->klok()->naDagen((int) ($g['opgelost_na_dagen'] ?? 0), $maand);

        Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['incident-afwijkingenbeheer', 'muteren'])
            ->bij("M{$maand}/incident_oplossen/{$g['sleutel']}")
            ->doe(function () use ($g, $incident, $sim) {
                $incident->update(['status' => 'opgelost']);

                // De raakvlakvraag hoort bij elk incident gesteld te zijn, ook
                // als het antwoord twee keer "nee" is. Zonder dit zou de demo
                // een gesloten incident tonen in een toestand die het scherm
                // sinds 08b niet meer toelaat. Een motivatie hoort er alleen bij
                // als er raakvlak is — anders staat er een alinea om te zeggen
                // dat er niets gebeurd is.
                $incident->update([
                    'raakt_persoonsgegevens' => $g['raakt_persoonsgegevens'] ?? false,
                    'is_netwerk_informatie_incident' => config('meldplicht.cbw_plichtig')
                        ? ($g['is_netwerk_informatie_incident'] ?? false)
                        : null,
                    'extern_meldingsplichtig' => $g['extern_meldingsplichtig'] ?? false,
                    'meldplicht_beoordeeld_op' => now(),
                    'meldplicht_motivatie' => $g['meldplicht_motivatie'] ?? null,
                ]);

                if ($g['extern_meldingsplichtig'] ?? false) {
                    Meldplicht::stelVast($incident->fresh(), $incident->fresh()->meldgrondslagen());
                }

                // Geen afwijking? Dan hoort er een besluit te liggen dát er geen
                // nodig is — zonder die motivatie weigert het model te sluiten,
                // en dat is precies de vraag die §10.1 wil laten stellen.
                if (! ($g['leidt_tot_afwijking'] ?? false)) {
                    $incident->update([
                        'geen_afwijking_reden' => $g['geen_afwijking_reden'] ?? null,
                        'status' => 'gesloten',
                        'gesloten_op' => now(),
                        'gesloten_door_id' => $sim->gebruiker('ciske')->id,
                    ]);
                }
            });
    }

    private function afwijking(array $g, int $maand, Simulatie $sim): void
    {
        $afwijking = Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['incident-afwijkingenbeheer', 'muteren'])
            ->bij("M{$maand}/afwijking/{$g['sleutel']}")
            ->doe(function () use ($g, $maand, $sim) {
                $bevinding = $sim->fixtures()->kent("bevinding:{$g['sleutel']}")
                    ? $sim->fixtures()->model("bevinding:{$g['sleutel']}")
                    : null;

                if ($g['bron'] === 'audit_bevinding' && $bevinding === null) {
                    throw DemoFixtureFout::bij(
                        "M{$maand}/afwijking/{$g['sleutel']}",
                        "geen bevinding vastgelegd bij ronde '{$g['bevinding_van']}'"
                    );
                }

                $afwijking = Afwijking::create([
                    'bron' => $g['bron'],
                    'omschrijving' => $g['omschrijving'],
                    'eigenaar_id' => $sim->gebruiker($g['eigenaar'])->id,
                    'bevinding_id' => $bevinding?->id,
                    'incident_id' => isset($g['incident'])
                        ? $sim->fixtures()->model($g['incident'])->id : null,
                ]);

                // De bevinding wordt als non-conformiteit opgepakt; dat is een
                // eigen status op de bevinding, los van die van de afwijking.
                $bevinding?->update(['status' => 'non_conformiteit_gestart']);

                if (isset($g['grondoorzaak'])) {
                    Grondoorzaak::create([
                        'afwijking_id' => $afwijking->id,
                        'omschrijving' => $g['grondoorzaak'],
                        'methodiek' => '5x waarom',
                    ]);
                }

                CorrigerendeMaatregel::create([
                    'afwijking_id' => $afwijking->id,
                    'omschrijving' => $g['corrigerende_maatregel']['omschrijving'],
                    'eigenaar_id' => $sim->gebruiker($g['eigenaar'])->id,
                    'deadline' => $this->deadline($g['corrigerende_maatregel'], $sim),
                    'status' => $g['corrigerende_maatregel']['status_in_eindstand'] ?? 'open',
                ]);

                return $afwijking;
            });

        $sim->fixtures()->onthoud($g['sleutel'], $afwijking->refresh());
    }

    /**
     * Een deelresultaat brengt de maatregel op 'in uitvoering', niet op
     * 'voltooid': het patchbeheer-dossier uit M14 wordt in twee stappen
     * afgewerkt en mag na de eerste stap niet als afgerond gelden.
     */
    private function maatregelVoltooien(array $g, int $maand, Simulatie $sim): void
    {
        $afwijking = $sim->fixtures()->model($g['afwijking']);

        Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['incident-afwijkingenbeheer', 'muteren'])
            ->bij("M{$maand}/corrigerende_maatregel_voltooien/{$g['afwijking']}")
            ->doe(function () use ($g, $afwijking) {
                foreach ($afwijking->maatregelen()->where('status', '!=', 'voltooid')->get() as $maatregel) {
                    if (isset($g['deel'])) {
                        $maatregel->update(['status' => 'in_uitvoering']);

                        continue;
                    }

                    TaakPlanner::voltooiVoorEntiteit($maatregel, 'corrigerende-maatregel');
                    $maatregel->update(['status' => 'voltooid', 'voltooid_op' => now()]);
                }
            });
    }

    private function effectiviteitstoets(array $g, int $maand, Simulatie $sim): void
    {
        $afwijking = $sim->fixtures()->model($g['afwijking']);
        $ciso = $sim->gebruiker('ciske');

        Handelt::als($ciso)
            ->mits('heeft-niveau', ['incident-afwijkingenbeheer', 'muteren'])
            ->bij("M{$maand}/effectiviteitstoets/{$g['afwijking']}")
            ->doe(function () use ($g, $afwijking, $ciso) {
                foreach ($afwijking->maatregelen()->where('status', 'voltooid')->get() as $maatregel) {
                    if ($maatregel->toetsen()->exists()) {
                        continue;
                    }

                    TaakPlanner::voltooiVoorEntiteit($maatregel, 'effectiviteitstoets');

                    Effectiviteitstoets::create([
                        'corrigerende_maatregel_id' => $maatregel->id,
                        'uitgevoerd_op' => now(),
                        'resultaat' => $g['resultaat'],
                        'uitgevoerd_door_id' => $ciso->id,
                        'toelichting' => $g['resultaat'] === 'effectief'
                            ? 'De maatregel is uitgevoerd en de situatie die tot de afwijking leidde, is niet opnieuw aangetroffen.'
                            : 'De maatregel is uitgevoerd, maar de afwijking is daarmee niet weggenomen.',
                    ]);
                }
            });
    }

    private function afwijkingSluiten(array $g, int $maand, Simulatie $sim): void
    {
        $afwijking = $sim->fixtures()->model($g['sleutel']);
        $sluiter = $sim->gebruiker($g['door']);

        Handelt::als($sluiter)
            ->mits('heeft-niveau', ['incident-afwijkingenbeheer', 'muteren'])
            ->bij("M{$maand}/afwijking_sluiten/{$g['sleutel']}")
            ->doe(function () use ($afwijking, $sluiter) {
                Afwijkingafsluiting::sluit($afwijking, $sluiter);

                // De bevinding is daarmee ook afgehandeld; anders blijft de
                // auditronde eeuwig een openstaande non-conformiteit tonen.
                $afwijking->bevinding()->first()?->update([
                    'status' => 'gesloten',
                    'gesloten_op' => now(),
                    'gesloten_door_id' => $sluiter->id,
                ]);

                $this->sluitIncidentIndienKlaar($afwijking, $sluiter->id);
            });
    }

    /**
     * Het incidentdossier sluit pas als er niets meer aan hangt. Dat is dezelfde
     * voorwaarde die `Incident::belemmeringVoorSluiten()` stelt; hier wordt hij
     * gevolgd in plaats van omzeild.
     */
    private function sluitIncidentIndienKlaar(Afwijking $afwijking, int $sluiterId): void
    {
        $incident = $afwijking->incident()->first();

        if ($incident === null || $incident->belemmeringVoorSluiten() !== null) {
            return;
        }

        $incident->update([
            'status' => 'gesloten',
            'gesloten_op' => now(),
            'gesloten_door_id' => $sluiterId,
        ]);
    }

    /** De deadline uit de fixture: een maandoffset, of een termijn na 'nu'. */
    private function deadline(array $def, Simulatie $sim)
    {
        if (isset($def['deadline_maand'])) {
            return $sim->klok()->datum((int) $def['deadline_maand'])->endOfMonth()->startOfDay();
        }

        // Vanaf het éinde van M22 en niet vanaf de eerste dag ervan: de bedoeling
        // van deze sleutel is een maatregel die in de eindstand nog loopt, dus
        // een deadline die op de draaidag écht in de toekomst ligt. Zolang M22 de
        // lopende maand was, klopte dat toevallig ook vanaf de eerste dag; nu M22
        // de vórige maand is, is het einde van de historie het enige ankerpunt
        // dat die belofte waarmaakt.
        if (isset($def['deadline_weken_na_m22'])) {
            return $sim->klok()->datum(Klok::AANTAL_MAANDEN)
                ->endOfMonth()->startOfDay()
                ->addWeeks((int) $def['deadline_weken_na_m22']);
        }

        return null;
    }
}
