<?php

namespace App\Support\Demo\Handlers;

use App\Models\Auditobject;
use App\Models\Auditplan;
use App\Models\Auditprogramma;
use App\Models\Auditronde;
use App\Models\Bevinding;
use App\Models\Gebruiker;
use App\Models\Maatregel;
use App\Models\OrganisatieEenheid;
use App\Support\Demo\DemoFixtureFout;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use App\Support\Koppeling;
use Illuminate\Support\Facades\Artisan;

/**
 * Auditprogramma's, jaarplannen, rondes en bevindingen.
 *
 * Twee dingen zijn hier bewust anders dan bij de andere domeinen:
 *
 *  1. **De programma's komen uit het productiecommando.**
 *     `isms:bereid-auditcyclus-voor` is het gereedschap dat een CISO hiervoor
 *     gebruikt; het in de motor namaken zou een tweede waarheid opleveren en de
 *     demo zou dan niet meer bewijzen dát dat commando werkt.
 *  2. **De autorisatie loopt via de record-guards.** De onafhankelijkheid van de
 *     interne auditor zit in `Auditronde::magUitvoerenDoor()` en
 *     `magBevindingBewerkenDoor()`, niet in de blok-autorisatiecheck
 *     (implementatie/11 §4). De Auditor-rol heeft geen `muteren`, en dat hoort
 *     zo te blijven.
 */
final class AuditHandlers
{
    /** @return array<string, callable> */
    public function register(): array
    {
        return [
            'auditprogramma_aanmaken' => $this->auditprogrammaAanmaken(...),
            'auditcyclus_voorbereiden' => $this->auditcyclusVoorbereiden(...),
            'auditprogramma_afsluiten' => $this->auditprogrammaAfsluiten(...),
            'auditronde' => $this->auditronde(...),
        ];
    }

    private function auditprogrammaAanmaken(array $g, int $maand, Simulatie $sim): void
    {
        $this->bereidVoor($g, $maand, $sim, voorbereiding: ($g['aard'] ?? null) === 'voorbereiding');
    }

    private function auditcyclusVoorbereiden(array $g, int $maand, Simulatie $sim): void
    {
        $this->bereidVoor($g, $maand, $sim, voorbereiding: false);
    }

    private function bereidVoor(array $g, int $maand, Simulatie $sim, bool $voorbereiding): void
    {
        Handelt::als($sim->gebruiker($g['door'] ?? 'ciske'))
            ->mits('heeft-niveau', ['auditmanagement', 'muteren'])
            ->bij("M{$maand}/{$g['sleutel']}")
            ->doe(function () use ($g, $maand, $sim, $voorbereiding) {
                $opties = [
                    '--start' => $sim->klok()->datum((int) $g['start_maand'])->toDateString(),
                    '--jaren' => (int) $g['aantal_jaren'],
                ];

                if ($voorbereiding) {
                    $opties['--voorbereiding'] = true;
                }

                if ($g['activeer'] ?? ($g['status'] ?? null) === 'actief') {
                    $opties['--activeer'] = true;
                }

                $code = Artisan::call('isms:bereid-auditcyclus-voor', $opties);

                if ($code !== 0) {
                    throw DemoFixtureFout::bij(
                        "M{$maand}/{$g['sleutel']}",
                        'isms:bereid-auditcyclus-voor is afgebroken: '.trim(Artisan::output())
                    );
                }

                $sim->fixtures()->onthoud($g['sleutel'], Auditprogramma::latest('id')->firstOrFail());
            });
    }

    private function auditprogrammaAfsluiten(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['auditmanagement', 'muteren'])
            ->bij("M{$maand}/auditprogramma_afsluiten/{$g['sleutel']}")
            ->doe(fn () => $sim->fixtures()->model($g['sleutel'])->update(['status' => 'afgesloten']));
    }

    /**
     * Een ronde van plannen tot afronden. Drie handelingen door twee personen:
     * de CISO plant en wijst toe, de uitvoerder start, legt de bevindingen vast
     * en rondt af.
     */
    private function auditronde(array $g, int $maand, Simulatie $sim): void
    {
        $ciso = $sim->gebruiker('ciske');
        $auditor = isset($g['auditor']) ? $sim->gebruiker($g['auditor']) : null;

        $ronde = Handelt::als($ciso)
            ->mits('heeft-niveau', ['auditmanagement', 'muteren'])
            ->bij("M{$maand}/auditronde/{$g['sleutel']} (plannen)")
            ->doe(fn () => $this->plan($g, $maand, $sim, $auditor));

        $sim->fixtures()->onthoud($g['sleutel'], $ronde);

        if (! ($g['afgerond'] ?? false)) {
            return;
        }

        // Wie de ronde uitvoert: bij een interne ronde de toegewezen auditor,
        // bij een externe de CISO die het rapport transcribeert.
        $uitvoerder = $ronde->isIntern() ? $auditor : $ciso;

        if ($uitvoerder === null) {
            throw DemoFixtureFout::bij("M{$maand}/auditronde/{$g['sleutel']}", 'interne ronde zonder auditor');
        }

        $this->voerUit($g, $maand, $sim, $ronde, $uitvoerder);
    }

    /** Plannen: het jaarplan zoeken of maken, de ronde vinden of aanmaken, auditor toewijzen. */
    private function plan(array $g, int $maand, Simulatie $sim, ?Gebruiker $auditor): Auditronde
    {
        $plan = $this->plan_($g, $sim);

        // De cyclus-commando's plannen hun rondes al. Die hergebruiken in plaats
        // van er een tweede naast te zetten: anders staat er in de demo een
        // geplande ronde die nooit is uitgevoerd naast een uitgevoerde ronde die
        // nooit gepland was.
        $ronde = $plan->rondes()
            ->where('type', $g['rondetype'])
            ->where('status', 'gepland')
            ->first()
            ?? Auditronde::create([
                'auditplan_id' => $plan->id,
                'type' => $g['rondetype'],
                'status' => 'gepland',
            ]);

        $ronde->update([
            'gepland_op' => now(),
            'auditor_gebruiker_id' => $auditor?->id,
            'extern_auditor_naam' => $g['extern_auditor_naam'] ?? null,
            'telt_mee_voor_dekking' => $g['telt_mee_voor_dekking'] ?? ! $ronde->isNulmeting(),
        ]);

        if (($g['scope'] ?? null) === 'alle_actieve_auditobjecten') {
            Koppeling::sync($ronde->auditobjecten(), 'auditobjecten', Auditobject::actief()->pluck('id')->all());
        }

        if ($ronde->isIntern()) {
            Koppeling::sync(
                $ronde->organisatieEenheden(),
                'organisatie-eenheden',
                OrganisatieEenheid::where('type', 'afdeling')->pluck('id')->all()
            );
        }

        return $ronde;
    }

    /**
     * Het jaarplan waar de ronde onder valt. Externe rondes horen bij geen
     * cyclus: die krijgen een los plan, want `auditrondes.auditplan_id` is niet
     * nullable en een certificeringsaudit is geen interne-auditcyclus.
     */
    private function plan_(array $g, Simulatie $sim): Auditplan
    {
        if (($g['programma'] ?? null) === null) {
            return Auditplan::create(['jaar' => now()->year, 'status' => 'vastgesteld']);
        }

        $programma = $sim->fixtures()->model($g['programma']);
        $programmajaar = (int) ($g['programmajaar'] ?? 1);

        return $programma->auditplannen()->where('programmajaar', $programmajaar)->first()
            ?? throw DemoFixtureFout::bij(
                "auditronde/{$g['sleutel']}",
                "programma '{$g['programma']}' heeft geen programmajaar {$programmajaar}"
            );
    }

    /** Uitvoeren: starten, bevindingen vastleggen, afronden — alle drie door de uitvoerder. */
    private function voerUit(array $g, int $maand, Simulatie $sim, Auditronde $ronde, Gebruiker $uitvoerder): void
    {
        Handelt::als($uitvoerder)
            ->mitsRecord(
                'alleen de toegewezen auditor start een interne ronde (implementatie/11 §4)',
                fn (Gebruiker $u) => $ronde->magUitvoerenDoor($u),
            )
            ->bij("M{$maand}/auditronde/{$g['sleutel']} (starten)")
            ->doe(fn () => $ronde->update(['status' => 'in_uitvoering']));

        $ronde->refresh();

        Handelt::als($uitvoerder)
            ->mitsRecord(
                'bevindingen mogen alleen door de uitvoerder van een lopende ronde worden vastgelegd',
                fn (Gebruiker $u) => $ronde->magBevindingBewerkenDoor($u),
            )
            ->bij("M{$maand}/auditronde/{$g['sleutel']} (bevindingen)")
            ->doe(fn () => $this->legBevindingenVast($g, $sim, $ronde));

        Handelt::als($uitvoerder)
            ->mitsRecord(
                'alleen de uitvoerder rondt de ronde af',
                fn (Gebruiker $u) => $ronde->magUitvoerenDoor($u),
            )
            ->bij("M{$maand}/auditronde/{$g['sleutel']} (afronden)")
            ->doe(fn () => $ronde->update(['status' => 'afgerond', 'uitgevoerd_op' => now()]));
    }

    /**
     * De bevindingen uit `audits.json`, met de aantallen uit `tijdlijn.json` als
     * controle. Lopen die twee uiteen, dan is dat een fixture-fout: stilzwijgend
     * de ene of de andere volgen levert een demo op die iets anders toont dan
     * het scenario beschrijft.
     */
    private function legBevindingenVast(array $g, Simulatie $sim, Auditronde $ronde): void
    {
        $definities = $sim->fixtures()->bestand('audits')['bevindingen'][$g['sleutel']]
            ?? throw DemoFixtureFout::bij('audits/bevindingen', "geen bevindingen voor '{$g['sleutel']}'");

        $verwacht = $g['bevindingen'] ?? [];
        $geteld = array_count_values(array_column($definities, 'type'));

        foreach ($verwacht as $type => $aantal) {
            if (($geteld[$type] ?? 0) !== $aantal) {
                throw DemoFixtureFout::bij(
                    "audits/bevindingen/{$g['sleutel']}",
                    sprintf('tijdlijn verwacht %d× %s, audits.json bevat er %d', $aantal, $type, $geteld[$type] ?? 0)
                );
            }
        }

        foreach ($definities as $def) {
            // Een non-conformiteit die tot een afwijking leidt, ontleent haar
            // tekst aan die afwijking: één omschrijving, op één plek.
            $afwijking = isset($def['afwijking'])
                ? $sim->fixtures()->gebeurtenis('afwijking', $def['afwijking'])
                : null;

            $bevinding = Bevinding::create([
                'auditronde_id' => $ronde->id,
                'type' => $def['type'],
                'omschrijving' => $def['omschrijving'] ?? $afwijking['omschrijving'],
                'maatregel_id' => $this->maatregelId($def['maatregel'] ?? $afwijking['maatregel'] ?? null),
            ]);

            if ($afwijking !== null) {
                // Onder een eigen sleutel, zodat de afwijking straks weet uit
                // welke bevinding zij voortkomt.
                $sim->fixtures()->onthoud("bevinding:{$def['afwijking']}", $bevinding);
            }
        }
    }

    private function maatregelId(?string $referentie): ?int
    {
        return $referentie === null
            ? null
            : Maatregel::where('annex_a_referentie', $referentie)->value('id');
    }
}
