<?php

namespace App\Support\Demo\Handlers;

use App\Actions\ActiveerScopeVerklaring;
use App\Models\Belanghebbende;
use App\Models\Gebruiker;
use App\Models\Issue;
use App\Models\OrganisatieEenheid;
use App\Models\Rol;
use App\Models\ScopeInterface;
use App\Models\ScopeVerklaring;
use App\Models\Uitsluiting;
use App\Support\Demo\DemoFixtureFout;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use App\Support\Koppeling;
use Illuminate\Support\Str;

/**
 * Gebruikers, organisatie-eenheden, context (issues en belanghebbenden), de
 * scopeverklaring en de losse bewijsstukken.
 */
final class OrganisatieHandlers
{
    /** @return array<string, callable> */
    public function register(): array
    {
        return [
            'gebruikers_aanmaken' => $this->gebruikersAanmaken(...),
            'context_vastleggen' => $this->contextVastleggen(...),
            'scope_concept' => $this->scopeConcept(...),
            'scope_activeren' => $this->scopeActiveren(...),
            'gebruiker_uit_dienst' => $this->gebruikerUitDienst(...),
            'bewijsstuk' => $this->bewijsstuk(...),
        ];
    }

    /**
     * De eerste ronde maakt ook de organisatie-eenheden aan: een gebruiker
     * verwijst naar zijn afdeling, dus die moet er eerder zijn.
     */
    private function gebruikersAanmaken(array $g, int $maand, Simulatie $sim): void
    {
        $this->borgEenheden($sim);

        $eersteRonde = ! $sim->fixtures()->kent('ciske');

        foreach ($g['sleutels'] as $sleutel) {
            $def = $sim->fixtures()->definitie('personen', 'gebruikers', $sleutel);
            $wachtwoord = Str::password(16);

            $maak = function () use ($def, $sleutel, $wachtwoord, $sim) {
                $gebruiker = Gebruiker::create([
                    'naam' => $def['naam'],
                    'email' => $def['email'],
                    'wachtwoord' => $wachtwoord,
                    'status' => 'actief',
                    'organisatie_eenheid_id' => $sim->fixtures()->model($def['eenheid'])->id,
                    'nda_getekend_op' => now(),
                    'screening_type' => $def['screening_type'] ?? null,
                    'screening_op' => isset($def['screening_type']) ? now() : null,
                ]);

                $rol = Rol::where('naam', $def['rol'])->firstOr(
                    fn () => throw DemoFixtureFout::bij("personen/{$sleutel}", "rol '{$def['rol']}' bestaat niet")
                );

                // Via het toewijzingsmodel en niet via attach(): zo staat de
                // rolverlening in de audit trail, precies zoals het
                // gebruikersscherm het doet.
                $gebruiker->rolToewijzingen()->create([
                    'rol_id' => $rol->id,
                    'toegekend_door_id' => auth()->id(),
                    'toegekend_op' => now(),
                ]);

                $sim->fixtures()->onthoud($sleutel, $gebruiker);
                $sim->onthoudWachtwoord($def['email'], $wachtwoord);
            };

            // Het allereerste account kan niemand aanmaken — er is nog geen
            // ingelogde gebruiker. Daarna doet de CISO het, met de
            // autorisatiecheck erop, precies zoals in productie.
            if ($eersteRonde) {
                $maak();

                continue;
            }

            Handelt::als($sim->gebruiker('ciske'))
                ->mits('heeft-niveau', ['identity-access', 'muteren'])
                ->bij("M{$maand}/gebruikers_aanmaken/{$sleutel}")
                ->doe($maak);
        }
    }

    private function borgEenheden(Simulatie $sim): void
    {
        foreach ($sim->fixtures()->lijst('organisatie', 'organisatie_eenheden') as $def) {
            if ($sim->fixtures()->kent($def['sleutel'])) {
                continue;
            }

            $sim->fixtures()->onthoud($def['sleutel'], OrganisatieEenheid::create([
                'naam' => $def['naam'],
                'type' => $def['type'],
            ]));
        }
    }

    private function contextVastleggen(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['context-scope', 'muteren'])
            ->bij("M{$maand}/context_vastleggen")
            ->doe(function () use ($sim) {
                foreach ($sim->fixtures()->lijst('organisatie', 'issues') as $def) {
                    $sim->fixtures()->onthoud($def['sleutel'], Issue::create([
                        'aard' => $def['aard'],
                        'categorie' => $def['categorie'],
                        'omschrijving' => $def['omschrijving'],
                        'laatst_beoordeeld_op' => now(),
                    ]));
                }

                foreach ($sim->fixtures()->lijst('organisatie', 'belanghebbenden') as $def) {
                    $belanghebbende = Belanghebbende::create([
                        'naam' => $def['naam'],
                        'aard' => $def['aard'],
                        'relevantie_voor_isms' => $def['relevantie_voor_isms'],
                    ]);

                    foreach ($def['eisen'] as $eis) {
                        $belanghebbende->eisen()->create([
                            'omschrijving' => $eis['omschrijving'],
                            'bron' => $eis['bron'],
                        ]);
                    }

                    $sim->fixtures()->onthoud($def['sleutel'], $belanghebbende);
                }
            });
    }

    private function scopeConcept(array $g, int $maand, Simulatie $sim): void
    {
        $def = $sim->fixtures()->definitie('organisatie', 'scopeverklaringen', $g['sleutel']);
        $basis = isset($def['erft_van'])
            ? $sim->fixtures()->definitie('organisatie', 'scopeverklaringen', $def['erft_van'])
            : $def;

        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['context-scope', 'muteren'])
            ->bij("M{$maand}/scope_concept/{$g['sleutel']}")
            ->doe(function () use ($def, $basis, $sim, $g) {
                $verklaring = ScopeVerklaring::create([
                    'versienummer' => $def['versienummer'],
                    'scopetekst' => $def['scopetekst'] ?? $basis['scopetekst'],
                    'status' => 'concept',
                ]);

                foreach ($basis['uitsluitingen'] ?? [] as $uitsluiting) {
                    Uitsluiting::create([
                        'scope_verklaring_id' => $verklaring->id,
                        'omschrijving' => $uitsluiting['omschrijving'],
                        'motivatie' => $uitsluiting['motivatie'],
                    ]);
                }

                foreach ($basis['raakvlakken'] ?? [] as $raakvlak) {
                    ScopeInterface::create([
                        'scope_verklaring_id' => $verklaring->id,
                        'omschrijving' => $raakvlak['omschrijving'],
                        'risico_implicatie' => $raakvlak['risico_implicatie'],
                    ]);
                }

                Koppeling::sync($verklaring->organisatieEenheden(), 'organisatie-eenheden', collect($basis['eenheden'] ?? [])
                    ->map(fn (string $s) => $sim->fixtures()->model($s)->id)->all());

                Koppeling::sync($verklaring->belanghebbenden(), 'belanghebbenden', collect($basis['belanghebbenden'] ?? [])
                    ->filter(fn (string $s) => $sim->fixtures()->kent($s))
                    ->map(fn (string $s) => $sim->fixtures()->model($s)->id)->all());

                Koppeling::sync($verklaring->issues(), 'issues', collect($sim->fixtures()->lijst('organisatie', 'issues'))
                    ->pluck('sleutel')
                    ->filter(fn (string $s) => $sim->fixtures()->kent($s))
                    ->map(fn (string $s) => $sim->fixtures()->model($s)->id)->all());

                $sim->fixtures()->onthoud($g['sleutel'], $verklaring);
            });
    }

    /** Goedkeuractie (implementatie/01c): de directie stelt de scope vast. */
    private function scopeActiveren(array $g, int $maand, Simulatie $sim): void
    {
        $directeur = $sim->gebruiker($g['door']);
        $verklaring = $sim->fixtures()->model($g['sleutel']);

        Handelt::als($directeur)
            ->mits('heeft-niveau', ['context-scope', 'goedkeuren'])
            ->bij("M{$maand}/scope_activeren/{$g['sleutel']}")
            ->doe(fn () => (new ActiveerScopeVerklaring)($verklaring, $directeur->naam));
    }

    private function gebruikerUitDienst(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['identity-access', 'muteren'])
            ->bij("M{$maand}/gebruiker_uit_dienst/{$g['sleutel']}")
            ->doe(function () use ($g, $sim) {
                $sim->gebruiker($g['sleutel'])->update([
                    'status' => 'gedeactiveerd',
                    'accounts_ingetrokken_op' => now(),
                ]);
            });
    }

    private function bewijsstuk(array $g, int $maand, Simulatie $sim): void
    {
        $koppeling = isset($g['koppeling']) && $sim->fixtures()->kent($g['koppeling'])
            ? $sim->fixtures()->model($g['koppeling'])
            : null;

        Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['bewijsrepository-audit-trail', 'muteren'])
            ->bij("M{$maand}/bewijsstuk")
            ->doe(fn () => $sim->bewijs()->maak($g['titel'], $g['toelichting'] ?? null, $koppeling));
    }
}
