<?php

namespace App\Http\Controllers;

use App\Models\Toetsopdracht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Het terugmeldkanaal van een toets (implementatie/10 §7): de enige plek waar het
 * ISMS gegevens aanneemt van een niet-ingelogde bron. De token is het bewijs;
 * de route staat buiten de `auth`-groep, is CSRF-uitgezonderd en getthrottled.
 */
class ToetsCallbackController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        // Onbekende token => 404, zonder onderscheid tussen "bestaat niet" en
        // "ingetrokken".
        $opdracht = Toetsopdracht::where('token', $token)->firstOrFail();

        $antwoord = [
            'ok' => true,
            'redirect_url' => route('taken.index'),
        ];

        $taak = $opdracht->taak;

        // Een voltooide taak wordt niet heropend (blok 7 §8): 200 zonder wijziging.
        if ($taak === null || $taak->status === 'voltooid') {
            return response()->json($antwoord);
        }

        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1'],
            'passed' => ['required', 'boolean'],
        ]);

        abort_if($data['score'] > $data['total'], 422, 'score mag total niet overschrijden.');

        // De deelnemer (taak-eigenaar) is de actor in de audit trail, niet de
        // "Systeem (geplande taak)"-terugval — dat zou verhullen wie de toets
        // werkelijk maakte (§7).
        if ($taak->eigenaar) {
            Auth::setUser($taak->eigenaar);
        }

        DB::transaction(function () use ($opdracht, $taak, $data) {
            $opdracht->update([
                'pogingen' => $opdracht->pogingen + 1,
                'laatste_score' => $data['score'],
                'laatste_totaal' => $data['total'],
                'laatste_poging_op' => now(),
                'status' => $data['passed'] ? 'geslaagd' : 'gezakt',
                'geslaagd_op' => $data['passed'] ? now() : $opdracht->geslaagd_op,
            ]);

            // Gezakt raakt de taak niet aan — die blijft open, het werk is niet af.
            if (! $data['passed']) {
                return;
            }

            // Geslaagd: taak voltooien en, bij een gekoppelde module, de
            // machinale voltooiing schrijven. De taak heeft bewust geen `soort`
            // (§8), dus hij wordt hier rechtstreeks afgerond, niet via TaakPlanner.
            $taak->update([
                'status' => 'voltooid',
                'voltooid_op' => now(),
                'omschrijving' => trim(($taak->omschrijving ? $taak->omschrijving."\n" : '')
                    .'Toets afgerond: '.$opdracht->toets_titel
                    .' ('.$data['score'].'/'.$data['total'].').'),
            ]);

            if ($opdracht->module && $taak->eigenaar) {
                $opdracht->module->registreerVoltooiing($taak->eigenaar, 'toets');
            }
        });

        return response()->json($antwoord);
    }
}
