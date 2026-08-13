<?php

namespace App\Livewire;

use App\Models\Auditobject;
use App\Models\Auditprogramma;
use App\Models\Auditronde;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * De dekkingsmatrix (plan 11b §5): per auditobject over de jaren van een cyclus
 * of het gepland/uitgevoerd/gat is, plus de coverage-KPI. Read-only — dit is
 * §9.1-meting en input voor de directiebeoordeling (§9.3), geen mutatie.
 *
 * "Uitgevoerd" telt uitsluitend een **afgeronde** ronde met uitvoerdatum die aan
 * een jaarplan van dít programma hangt — geen ijdelheidscijfer van afgevinkte
 * vakjes.
 */
#[Layout('components.layouts.app')]
class Dekkingsmatrix extends Component
{
    public ?int $programmaId = null;

    public function mount(): void
    {
        // Standaard het actieve programma, anders het meest recente.
        $this->programmaId = Auditprogramma::orderByRaw("status = 'actief' desc")
            ->orderByDesc('start_datum')
            ->value('id');
    }

    public function render()
    {
        $programma = $this->programmaId !== null
            ? Auditprogramma::find($this->programmaId)
            : null;

        $programmas = Auditprogramma::orderByDesc('start_datum')->get();

        if ($programma === null) {
            return view('livewire.dekkingsmatrix', [
                'programma' => null,
                'programmas' => $programmas,
                'programmajaren' => [],
                'groepen' => collect(),
                'cellen' => [],
                'kpi' => null,
            ]);
        }

        $programmajaren = $programma->programmajaren();

        $objecten = Auditobject::actief()
            ->with('maatregel')
            ->orderBy('groep')
            ->orderBy('volgorde')
            ->get();

        // Geplande programmajaren per object (uit de dekkingsplanning).
        $dekkingen = $programma->dekkingen()->get()->keyBy('auditobject_id');
        $gepland = [];
        foreach ($dekkingen as $objectId => $dekking) {
            $gepland[$objectId] = $dekking->geplandeProgrammajaren($programma->aantal_jaren);
        }

        // Feitelijk uitgevoerd: afgeronde rondes van een jaarplan van dit programma.
        $uitgevoerd = $this->uitgevoerdePerObject($programma);

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

        return view('livewire.dekkingsmatrix', [
            'programma' => $programma,
            'programmas' => $programmas,
            'programmajaren' => $programmajaren,
            'groepen' => $objecten->groupBy('groep'),
            'cellen' => $cellen,
            'kpi' => [
                'gedekt' => $gedekt,
                'totaal' => $totaal,
                'percentage' => $totaal > 0 ? (int) round($gedekt / $totaal * 100) : 0,
                'nooit' => $totaal - $gedekt,
            ],
        ]);
    }

    /**
     * Per auditobject de **programmajaren** waarin een afgeronde ronde van dit
     * programma het object dekte. Distinct (twee rondes in hetzelfde
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
    private function uitgevoerdePerObject(Auditprogramma $programma): array
    {
        $rondes = Auditronde::query()
            ->where('status', 'afgerond')
            ->whereNotNull('uitgevoerd_op')
            // Plan 11c: een uitgevoerde ronde is niet automatisch een dekkende
            // ronde. Een nulmeting of her-audit blijft volwaardig dossier — hij
            // telt alleen niet mee in deze telling.
            ->dekkend()
            ->whereHas('auditplan', fn ($q) => $q->where('auditprogramma_id', $programma->id))
            ->with(['auditobjecten:id', 'auditplan'])
            ->get();

        $vensters = $programma->programmajaren();

        $map = [];
        foreach ($rondes as $ronde) {
            $nummer = $this->programmajaarVan($ronde, $vensters);

            if ($nummer === null) {
                continue;
            }

            foreach ($ronde->auditobjecten as $object) {
                $map[$object->id][$nummer] = true;
            }
        }

        return array_map(fn (array $jaren) => array_keys($jaren), $map);
    }

    /**
     * Het programmajaar waarin een uitgevoerde ronde valt.
     *
     * @param  list<array{nummer: int, start: Carbon, eind: Carbon}>  $vensters
     */
    private function programmajaarVan(Auditronde $ronde, array $vensters): ?int
    {
        foreach ($vensters as $venster) {
            if ($ronde->uitgevoerd_op->betweenIncluded($venster['start'], $venster['eind'])) {
                return $venster['nummer'];
            }
        }

        return $ronde->auditplan?->programmajaar;
    }
}
