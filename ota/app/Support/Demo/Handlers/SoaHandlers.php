<?php

namespace App\Support\Demo\Handlers;

use App\Models\Beleidsdocument;
use App\Models\SoaRegel;
use App\Support\Demo\DemoFixtureFout;
use App\Support\Demo\Handelt;
use App\Support\Demo\Simulatie;
use App\Support\TaakPlanner;
use Illuminate\Support\Collection;

/**
 * De Statement of Applicability: de beoordelingsgolven, de implementatiegolven,
 * de jaarlijkse herbeoordeling en de eigen maatregelclassificatie (plan 04d).
 *
 * De SoA wordt bewust in twee slagen gevuld. Een SoA die in één keer op 100%
 * staat laat niet zien dat er over nagedacht is, en de Plan-KPI heeft dan geen
 * curve om te tonen.
 */
final class SoaHandlers
{
    /**
     * Aantal toepasselijke regels dat na een implementatiegolf op 'in
     * uitvoering' staat. De rest die nog niet af is, staat op 'niet gestart' —
     * zonder dat onderscheid lijkt alles wat niet af is even ver.
     */
    private const IN_UITVOERING = 8;

    /** @return array<string, callable> */
    public function register(): array
    {
        return [
            'soa_golf' => $this->soaGolf(...),
            'soa_implementatiegolf' => $this->soaImplementatiegolf(...),
            'soa_herbeoordelen' => $this->soaHerbeoordelen(...),
            'soa_eigen_classificatie' => $this->soaEigenClassificatie(...),
        ];
    }

    /**
     * Een beoordelingsgolf: het opgegeven aandeel van de 93 regels krijgt een
     * oordeel. Alleen nog onbesliste regels — een tweede golf herbevestigt de
     * eerste niet.
     */
    private function soaGolf(array $g, int $maand, Simulatie $sim): void
    {
        $golf = $this->golf($sim, 'beoordelingsgolven', (int) $g['golf']);
        $regels = $this->opVolgorde();
        $tot = (int) ceil($regels->count() * $golf['aandeel']);
        $nietVanToepassing = collect($sim->fixtures()->lijst('soa', 'niet_van_toepassing'))
            ->keyBy('referentie');

        // De CISO beoordeelt de SoA; er staat geen `door` in de tijdlijn omdat
        // dat bij elke golf dezelfde persoon is.
        Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/soa_golf {$g['golf']}")
            ->doe(function () use ($regels, $tot, $nietVanToepassing) {
                foreach ($regels->take($tot) as $regel) {
                    if ($regel->van_toepassing !== null) {
                        continue;
                    }

                    $uitsluiting = $nietVanToepassing->get($regel->maatregel->annex_a_referentie);

                    $regel->update($uitsluiting
                        ? [
                            'van_toepassing' => false,
                            'motivatie' => $uitsluiting['motivatie'],
                            'implementatiestatus' => 'nvt',
                            'laatst_beoordeeld_op' => now(),
                        ]
                        : [
                            'van_toepassing' => true,
                            'motivatie' => $this->motivatie($regel),
                            'beleidreferentie' => $this->beleidreferentie($regel),
                            'laatst_beoordeeld_op' => now(),
                        ]);
                }
            });
    }

    /** Cumulatief: het getal in de fixture is de stand ná deze golf. */
    private function soaImplementatiegolf(array $g, int $maand, Simulatie $sim): void
    {
        $golf = $this->golf($sim, 'implementatiegolven', (int) $g['golf']);
        $regels = $this->opVolgorde()->filter(fn (SoaRegel $r) => $r->van_toepassing === true)->values();

        Handelt::als($sim->gebruiker('ciske'))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/soa_implementatiegolf {$g['golf']}")
            ->doe(function () use ($regels, $golf) {
                foreach ($regels as $index => $regel) {
                    $status = match (true) {
                        $index < $golf['geimplementeerd'] => 'geimplementeerd',
                        $index < $golf['geimplementeerd'] + self::IN_UITVOERING => 'in_uitvoering',
                        default => 'niet_gestart',
                    };

                    if ($regel->implementatiestatus !== $status) {
                        $regel->update(['implementatiestatus' => $status]);
                    }
                }
            });
    }

    private function soaHerbeoordelen(array $g, int $maand, Simulatie $sim): void
    {
        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/soa_herbeoordelen")
            ->doe(function () {
                foreach (SoaRegel::where('van_toepassing', true)->get() as $regel) {
                    TaakPlanner::voltooiVoorEntiteit($regel, 'soa-herbeoordeling');
                    $regel->update(['laatst_beoordeeld_op' => now()]);
                }
            });
    }

    /**
     * Plan 04d fase 2: de organisatie stelt haar eigen classificatie vast op de
     * SoA-regel. Twee van de drie wijken af van het meegeleverde uitgangspunt,
     * de derde bevestigt het — die laatste hoort er nadrukkelijk bij, want
     * "ernaar gekeken en niets veranderd" is ook een vaststelling.
     */
    private function soaEigenClassificatie(array $g, int $maand, Simulatie $sim): void
    {
        $eigen = $sim->fixtures()->bestand('soa')['eigen_classificatie'];

        Handelt::als($sim->gebruiker($g['door']))
            ->mits('heeft-niveau', ['risico-soa', 'muteren'])
            ->bij("M{$maand}/soa_eigen_classificatie")
            ->doe(function () use ($eigen) {
                foreach ($eigen['regels'] as $def) {
                    $regel = SoaRegel::whereHas(
                        'maatregel',
                        fn ($q) => $q->where('annex_a_referentie', $def['referentie'])
                    )->firstOr(fn () => throw DemoFixtureFout::bij(
                        'soa/eigen_classificatie',
                        "maatregel {$def['referentie']} bestaat niet"
                    ));

                    $regel->update(['kenmerken_eigen' => $def['kenmerken']]);
                }
            });
    }

    // --- Hulpjes -----------------------------------------------------------

    /**
     * De vaste werkvolgorde: eerst de organisatorische en mensgerichte
     * maatregelen, want daar zit het beleid dat op dat moment geschreven wordt;
     * binnen een thema op nummer.
     *
     * @return Collection<int, SoaRegel>
     */
    private function opVolgorde(): Collection
    {
        $themas = ['organisatorisch' => 0, 'mensgericht' => 1, 'fysiek' => 2, 'technologisch' => 3];

        return SoaRegel::with('maatregel')->get()->sortBy(function (SoaRegel $regel) use ($themas) {
            [$hoofdstuk, $sub] = array_pad(explode('.', $regel->maatregel->annex_a_referentie, 2), 2, '0');

            return sprintf('%d-%03d-%03d', $themas[$regel->maatregel->thema] ?? 9, (int) $hoofdstuk, (int) $sub);
        })->values();
    }

    /** @return array<string, mixed> */
    private function golf(Simulatie $sim, string $lijst, int $index): array
    {
        return $sim->fixtures()->lijst('soa', $lijst)[$index]
            ?? throw DemoFixtureFout::bij("soa/{$lijst}", "golf {$index} bestaat niet");
    }

    /**
     * De onderbouwing. Waar al beleid aan de regel hangt, verwijst de motivatie
     * daarnaar; anders naar de reden waarom het thema FruitBV raakt. Dat is wat
     * een SoA-motivatie hoort te doen — verwijzen naar waar het geregeld is,
     * niet de maatregel herhalen.
     */
    private function motivatie(SoaRegel $regel): string
    {
        $document = $this->document($regel);

        if ($document !== null) {
            return "Van toepassing. Uitgewerkt in {$document->titel}.";
        }

        return match ($regel->maatregel->thema) {
            'organisatorisch' => 'Van toepassing: FruitBV legt deze beheersing organisatorisch vast; de uitwerking volgt in beleid en procedures.',
            'mensgericht' => 'Van toepassing: de maatregel raakt de medewerkers en de ZZP-beheerders met toegang tot FruitCloud.',
            'fysiek' => 'Van toepassing via het datacenter van WortelNet en het kantoor in Barendrecht; de uitvoering ligt deels bij de hostingpartij.',
            default => 'Van toepassing op de productieomgeving van FruitCloud en de onderliggende infrastructuur.',
        };
    }

    private function beleidreferentie(SoaRegel $regel): ?string
    {
        return $this->document($regel)?->titel;
    }

    private function document(SoaRegel $regel): ?Beleidsdocument
    {
        return $regel->beleidsdocumenten()->first();
    }
}
