<?php

namespace App\Support;

use App\Models\Auditobject;
use App\Models\Auditprogramma;
use App\Models\Auditronde;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * De dekkingsmatrix van één auditprogramma (plan 11b §5): per auditobject over
 * de programmajaren of het gepland, uitgevoerd of een gat is, plus de
 * coverage-KPI.
 *
 * Stond inline in `App\Livewire\Dekkingsmatrix`; losgetrokken voor de
 * assistenttool `audit-dekking` (implementatie/14b §4.3), zodat scherm en tool
 * dezelfde afleiding gebruiken.
 *
 * "Uitgevoerd" telt uitsluitend een **afgeronde** ronde met uitvoerdatum die aan
 * een jaarplan van dít programma hangt — geen ijdelheidscijfer van afgevinkte
 * vakjes.
 */
final class Dekkingsmatrixstand
{
    /**
     * @param  list<array{nummer: int, start: Carbon, eind: Carbon}>  $programmajaren
     * @param  Collection<int, Auditobject>  $objecten
     * @param  array<int, array<int, string>>  $cellen  object-id => programmajaar => uitgevoerd|gat|gepland|leeg
     * @param  array{gedekt: int, totaal: int, percentage: int, nooit: int}  $kpi
     */
    private function __construct(
        public readonly Auditprogramma $programma,
        public readonly array $programmajaren,
        public readonly Collection $objecten,
        public readonly array $cellen,
        public readonly array $kpi,
    ) {}

    /** Standaard het actieve programma, anders het meest recente. */
    public static function standaardProgrammaId(): ?int
    {
        return Auditprogramma::orderByRaw("status = 'actief' desc")
            ->orderByDesc('start_datum')
            ->value('id');
    }

    public static function voor(Auditprogramma $programma): self
    {
        $programmajaren = $programma->programmajaren();

        $objecten = Auditobject::actief()
            ->with('maatregel')
            ->orderBy('volgorde')
            ->orderBy('id')
            ->get();

        // Geplande programmajaren per object (uit de dekkingsplanning).
        $dekkingen = $programma->dekkingen()->get()->keyBy('auditobject_id');
        $gepland = [];
        foreach ($dekkingen as $objectId => $dekking) {
            $gepland[$objectId] = $dekking->geplandeProgrammajaren($programma->aantal_jaren);
        }

        // Feitelijk uitgevoerd: afgeronde rondes van een jaarplan van dit programma.
        $uitgevoerd = self::uitgevoerdePerObject($programma);

        // Een programmajaar is "voorbij" als zijn venster is afgelopen — niet als
        // het kalenderjaar voorbij is. Bij een voorbereidingsprogramma laten we
        // gaten helemaal weg: de opstartfase hóórt gaten te hebben, dat is juist
        // de uitkomst van de nulmeting (plan 11c fase 3).
        $toonGaten = ! $programma->isVoorbereiding();

        $cellen = [];
        $gedekt = 0;
        foreach ($objecten as $object) {
            if (! empty($uitgevoerd[$object->id])) {
                $gedekt++;
            }

            foreach ($programmajaren as $jaar) {
                $nummer = $jaar['nummer'];
                $isUitgevoerd = in_array($nummer, $uitgevoerd[$object->id] ?? [], true);
                $isGepland = in_array($nummer, $gepland[$object->id] ?? [], true);

                $cellen[$object->id][$nummer] = match (true) {
                    $isUitgevoerd => 'uitgevoerd',
                    $isGepland && $toonGaten && $jaar['eind']->isPast() => 'gat',
                    $isGepland => 'gepland',
                    default => 'leeg',
                };
            }
        }

        $totaal = $objecten->count();

        return new self($programma, $programmajaren, $objecten, $cellen, [
            'gedekt' => $gedekt,
            'totaal' => $totaal,
            'percentage' => $totaal > 0 ? (int) round($gedekt / $totaal * 100) : 0,
            'nooit' => $totaal - $gedekt,
        ]);
    }

    /**
     * Per auditobject de **programmajaren** waarin een afgeronde ronde van dit
     * programma het object **behandelde**. Distinct (twee rondes in hetzelfde
     * programmajaar tellen één keer).
     *
     * De bucketing gaat op de **uitvoerdatum** binnen het venster van een
     * programmajaar, niet op `uitgevoerd_op->year` (plan 11c): bij een cyclus die
     * in mei begint, valt een ronde van 2 januari in het programmajaar dat het
     * jaar ervóór begon. Voor de dekking telt wanneer de audit feitelijk
     * plaatsvond — een ronde die een jaar te laat werd uitgevoerd, dekte dat
     * object in het jaar waarin hij plaatsvond.
     *
     * Valt de datum buiten elk venster (een uitloper ná het einde van de cyclus),
     * dan telt het programmajaar van het jaarplan waar de ronde aan hangt; anders
     * zou de ronde geruisloos uit de matrix vallen.
     *
     * @return array<int, list<int>>
     */
    private static function uitgevoerdePerObject(Auditprogramma $programma): array
    {
        $rondes = Auditronde::query()
            ->where('status', 'afgerond')
            ->whereNotNull('uitgevoerd_op')
            // Plan 11c: een uitgevoerde ronde is niet automatisch een dekkende
            // ronde. Een nulmeting of her-audit blijft volwaardig dossier — hij
            // telt alleen niet mee in deze telling.
            ->dekkend()
            ->whereHas('auditplan', fn ($q) => $q->where('auditprogramma_id', $programma->id))
            ->with(['auditobjecten', 'bevindingen:id,auditronde_id,auditobject_id', 'auditplan'])
            ->get();

        $vensters = $programma->programmajaren();

        $map = [];
        foreach ($rondes as $ronde) {
            $nummer = self::programmajaarVan($ronde, $vensters);

            if ($nummer === null) {
                continue;
            }

            // Plan 11d: in de scope staan is geen dekking. Alleen een object dat
            // de auditor daadwerkelijk behandelde — geen opmerkingen, of een
            // bevinding — telt mee. Het verschil tussen "we waren het van plan"
            // en "we hebben ernaar gekeken" is precies wat een externe auditor
            // natelt.
            foreach ($ronde->behandeldeObjectIds() as $objectId) {
                $map[$objectId][$nummer] = true;
            }
        }

        return array_map(fn (array $jaren) => array_keys($jaren), $map);
    }

    /**
     * Het programmajaar waarin een uitgevoerde ronde valt.
     *
     * @param  list<array{nummer: int, start: Carbon, eind: Carbon}>  $vensters
     */
    private static function programmajaarVan(Auditronde $ronde, array $vensters): ?int
    {
        foreach ($vensters as $venster) {
            if ($ronde->uitgevoerd_op->betweenIncluded($venster['start'], $venster['eind'])) {
                return $venster['nummer'];
            }
        }

        return $ronde->auditplan?->programmajaar;
    }
}
