<?php

namespace App\Listeners;

use App\Models\Systeemhartslag;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Schrijft één regel per geplande taak die gedraaid heeft
 * (implementatie/00m §2), naar het model van {@see RegistreerTweefactorGebeurtenis}.
 *
 * Eén listener op drie gebeurtenissen, en géén `->onSuccess()`/`->onFailure()`
 * per regel in `routes/console.php`: dat zijn achttien plekken om te vergeten,
 * en de planning hoort over planning te gaan (00m §0.2).
 *
 * `ScheduledTaskSkipped` staat er expliciet bij. Laravel slaat geplande
 * commando's over zolang de applicatie `down` is, en de uitrol zet de site
 * tijdens een release in onderhoud — een overgeslagen run is dus een normaal en
 * verklaarbaar geval dat je wél wilt kunnen terugzien.
 */
class RegistreerSchedulerHartslag
{
    /** @var array<class-string, string> */
    private const RESULTATEN = [
        ScheduledTaskFinished::class => 'gelukt',
        ScheduledTaskFailed::class => 'fout',
        ScheduledTaskSkipped::class => 'overgeslagen',
    ];

    public function handle(object $gebeurtenis): void
    {
        // Deze listener mag nooit gooien. Een mislukte hartslagregistratie hoort
        // een geplande taak niet te laten omvallen — dan zou de bewaking de
        // storing zijn die ze moet melden. Zelfde patroon als
        // NotificatieDispatcher (00m §2).
        try {
            $this->registreer($gebeurtenis);
        } catch (Throwable $fout) {
            Log::warning('Hartslag niet vastgelegd: '.$fout->getMessage());
        }
    }

    private function registreer(object $gebeurtenis): void
    {
        $resultaat = self::RESULTATEN[$gebeurtenis::class] ?? null;

        if ($resultaat === null || ! isset($gebeurtenis->task)) {
            return;
        }

        $taak = $gebeurtenis->task;
        $sleutel = Systeemhartslag::sleutelVoor($taak);

        // Een `Schedule::call()`-taak heeft geen commando en dus geen stabiele
        // sleutel (00m §0.5). Niets vastleggen is hier beter dan een rij onder
        // een verzonnen sleutel, want de detectie zou hem daarna eeuwig missen.
        if ($sleutel === null) {
            return;
        }

        Systeemhartslag::create([
            'taak_sleutel' => $sleutel,
            'weergavenaam' => $taak->getSummaryForDisplay(),
            'gedraaid_op' => now(),
            'resultaat' => $resultaat,
            // `runtime` staat alleen op ScheduledTaskFinished, in seconden.
            'duur_ms' => isset($gebeurtenis->runtime)
                ? (int) round($gebeurtenis->runtime * 1000)
                : null,
            'melding' => $this->melding($gebeurtenis),
        ]);
    }

    /**
     * Waaróm het misging of werd overgeslagen. Bij een overslaan heeft Laravel
     * geen reden bij zich — de filters die hem tegenhielden zijn closures — dus
     * daar staat wat de lezer wél verder helpt.
     */
    private function melding(object $gebeurtenis): ?string
    {
        if ($gebeurtenis instanceof ScheduledTaskFailed) {
            return $gebeurtenis->exception->getMessage();
        }

        if ($gebeurtenis instanceof ScheduledTaskSkipped) {
            return 'Overgeslagen door de planner; meestal onderhoudsmodus of een filter op deze taak.';
        }

        return null;
    }

    /** @return list<class-string> */
    public static function gebeurtenissen(): array
    {
        return array_keys(self::RESULTATEN);
    }
}
