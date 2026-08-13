<?php

namespace App\Support\Demo\Handlers;

use App\Models\Doelgroep;
use App\Models\Trainingsmodule;
use App\Models\Trainingsvoltooiing;
use App\Support\Demo\DemoFixtureFout;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use App\Support\Koppeling;
use App\Support\ToetsBestanden;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Bewustzijnstraining: de modules, hun doelgroepen en de voltooiingsrondes.
 *
 * Een voltooiing registreert de deelnemer zelf (`bewustzijn-training/uitvoeren`).
 * De Auditor-rol heeft dat recht niet — die is bewust lees-en-exporteer — en
 * voor die enkele deelnemer legt de CISO de voltooiing vast. Dat mag: een
 * voltooiing is een feit dat de beheerder van het programma kan vaststellen,
 * anders dan een leesbevestiging, die alleen de lezer zelf kan afleggen (zie
 * BeleidHandlers, waar de Auditor daarom wordt overgeslagen).
 */
final class TrainingHandlers
{
    /** @return array<string, callable> */
    public function register(): array
    {
        return ['training_ronde' => $this->trainingRonde(...)];
    }

    private function trainingRonde(array $g, int $maand, Simulatie $sim): void
    {
        $def = $sim->fixtures()->definitie('training', 'modules', $g['module']);
        $module = $this->borgModule($def, $maand, $sim);
        $ronde = $def['rondes'][(int) $g['ronde']]
            ?? throw DemoFixtureFout::bij("training/{$g['module']}", "ronde {$g['ronde']} bestaat niet");

        foreach ($ronde['voltooid_door'] as $sleutel) {
            $deelnemer = $sim->gebruiker($sleutel);
            $magZelf = Gate::forUser($deelnemer)->allows('heeft-niveau', ['bewustzijn-training', 'uitvoeren']);

            Handelt::als($magZelf ? $deelnemer : $sim->gebruiker('ciske'))
                ->mits('heeft-niveau', ['bewustzijn-training', $magZelf ? 'uitvoeren' : 'muteren'])
                ->bij("M{$maand}/training_ronde/{$g['module']}/{$sleutel}")
                ->doe(fn () => Trainingsvoltooiing::create([
                    'trainingsmodule_id' => $module->id,
                    'gebruiker_id' => $deelnemer->id,
                    'voltooid_op' => now(),
                    'verloopt_op' => $module->geldigheidsduur_maanden
                        ? now()->addMonths($module->geldigheidsduur_maanden)
                        : null,
                    'bron' => 'zelfregistratie',
                ]));
        }
    }

    /** De module bestaat vanaf de eerste ronde; latere rondes vinden hem terug. */
    private function borgModule(array $def, int $maand, Simulatie $sim): Trainingsmodule
    {
        if ($sim->fixtures()->kent($def['sleutel'])) {
            return $sim->fixtures()->model($def['sleutel']);
        }

        $module = Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['bewustzijn-training', 'muteren'])
            ->bij("M{$maand}/trainingsmodule/{$def['sleutel']}")
            ->doe(function () use ($def, $sim) {
                $module = Trainingsmodule::create([
                    'titel' => $def['titel'],
                    'geldigheidsduur_maanden' => $def['geldigheidsduur_maanden'],
                    'actief' => $def['actief'],
                    'toets_bestand' => $this->borgToetsbestand($def, $sim),
                ]);

                Koppeling::sync(
                    $module->doelgroepen(),
                    'doelgroepen',
                    collect($def['doelgroepen'])->map(fn (string $s) => $this->borgDoelgroep($s, $sim)->id)->all()
                );

                return $module;
            });

        return $sim->fixtures()->onthoud($def['sleutel'], $module);
    }

    /**
     * Het toetsbestand van een module op de toetsen-disk zetten, en de naam
     * teruggeven waarmee de module ernaar wijst.
     *
     * Een module zonder `toets_bestand` in de fixtures levert null op — dat is
     * het normale geval; twee van de drie demomodules hebben geen toets.
     *
     * Het bestand reist mee in `saasdemo/data/toetsen/` en wordt hier
     * neergezet, want in een echte installatie plaatst de Administrator het via
     * het beheerscherm en dat kan een demovulling niet nabootsen. Bestaat het al,
     * dan blijft het staan: wie op zijn demo-installatie een eigen versie heeft
     * geüpload, raakt die niet kwijt aan een tweede vulling.
     */
    private function borgToetsbestand(array $def, Simulatie $sim): ?string
    {
        $naam = $def['toets_bestand'] ?? null;

        if ($naam === null) {
            return null;
        }

        if (! ToetsBestanden::bestaat($naam)) {
            $bron = $sim->fixtures()->bijlage('toetsen/'.$naam);

            if (! is_file($bron)) {
                throw DemoFixtureFout::bij('training', "toetsbestand {$naam} ontbreekt in de fixtures");
            }

            Storage::disk(ToetsBestanden::DISK)->put($naam, (string) file_get_contents($bron));
        }

        return $naam;
    }

    /**
     * Doelgroepen zijn bij training ledenlijsten (anders dan bij beleid, waar de
     * bevestigingsplicht aan afdelingen hangt). Leden die op dit moment nog niet
     * bestaan blijven weg; wie later binnenkomt, hoort tot dan ook niet bij de
     * doelgroep.
     */
    private function borgDoelgroep(string $sleutel, Simulatie $sim): Doelgroep
    {
        $def = $sim->fixtures()->definitie('personen', 'doelgroepen', $sleutel);

        $doelgroep = $sim->fixtures()->kent($sleutel)
            ? $sim->fixtures()->model($sleutel)
            : $sim->fixtures()->onthoud($sleutel, Doelgroep::create([
                'naam' => $def['naam'],
                'omschrijving' => $def['omschrijving'],
            ]));

        Koppeling::sync(
            $doelgroep->gebruikers(),
            'leden',
            collect($def['leden'])
                ->filter(fn (string $lid) => $sim->fixtures()->kent($lid))
                ->map(fn (string $lid) => $sim->gebruiker($lid)->id)
                ->all()
        );

        return $doelgroep;
    }
}
