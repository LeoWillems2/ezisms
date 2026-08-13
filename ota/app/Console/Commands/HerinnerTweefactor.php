<?php

namespace App\Console\Commands;

use App\Mail\TweefactorDeadline;
use App\Models\Gebruiker;
use App\Models\Notificatie;
use App\Support\NotificatieDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Herinnert gebruikers aan hun openstaande tweede factor
 * (implementatie/01d §9).
 *
 * Zonder deze taak is de enige waarschuwing de balk in het systeem zelf, en die
 * ziet iemand die twee weken niet inlogt nooit. Verlopen sluit niemand buiten —
 * hij komt niet verder dan het instelscherm en helpt zichzelf daar — maar het
 * gebeurt dan wel op het slechtst denkbare moment: als hij inlogt om iets te
 * doen.
 *
 * Eigen commando en niet aangehangen aan `isms:verval-gebruikersaccounts`: dat
 * commando deactiveert accounts, dit stuurt post. Twee dingen in één sweep is
 * één ding te veel om over na te denken als er iets misgaat.
 */
class HerinnerTweefactor extends Command
{
    protected $signature = 'isms:herinner-tweefactor';

    protected $description = 'Mailt gebruikers van wie de tweefactor-termijn bijna of net verstreken is';

    /** Zoveel dagen vóór de deadline gaat de eerste herinnering uit. */
    public const VOORAANKONDIGING_DAGEN = 3;

    private const GEBEURTENIS = 'tweefactor_deadline';

    public function handle(): int
    {
        if (! config('tweefactor.afdwingen')) {
            $this->info('Tweefactor wordt niet afgedwongen; geen herinneringen verstuurd.');

            return self::SUCCESS;
        }

        $kandidaten = Gebruiker::query()
            ->where('status', 'actief')
            ->whereNotNull('tweefactor_deadline')
            ->whereNull('two_factor_confirmed_at')
            ->whereDate('tweefactor_deadline', '<=', now()->addDays(self::VOORAANKONDIGING_DAGEN))
            ->get();

        $verstuurd = 0;

        foreach ($kandidaten as $gebruiker) {
            $dagen = (int) ceil(now()->startOfDay()->diffInDays($gebruiker->tweefactor_deadline, absolute: false));

            if ($this->alGemaild($gebruiker, $dagen)) {
                continue;
            }

            NotificatieDispatcher::verzend(
                self::GEBEURTENIS,
                new TweefactorDeadline($gebruiker, max($dagen, 0)),
                collect([$gebruiker]),
            );

            $verstuurd++;
        }

        $this->info("{$verstuurd} herinnering(en) verstuurd; {$kandidaten->count()} gebruiker(s) in beeld.");

        return self::SUCCESS;
    }

    /**
     * Hoogstens één mail per fase: één in de aanloop en één zodra de termijn
     * verstreken is. Anders krijgt iemand die met vakantie is veertien dezelfde
     * mails, en dat is de snelste manier om een herinnering te leren negeren.
     *
     * De grens volgt uit de deadline zelf: vóór het verstrijken telt het venster
     * vanaf het moment dat de klok ging lopen, daarna vanaf de deadline. Zet de
     * CISO de tweede factor opnieuw, dan komt er een nieuwe deadline en dus een
     * nieuw venster.
     */
    private function alGemaild(Gebruiker $gebruiker, int $dagenResterend): bool
    {
        $deadline = Carbon::parse($gebruiker->tweefactor_deadline);

        $grens = $dagenResterend > 0
            ? $deadline->copy()->subDays((int) config('tweefactor.respijt_dagen'))
            : $deadline->copy()->startOfDay();

        return Notificatie::query()
            ->where('gebeurtenis_type', self::GEBEURTENIS)
            ->where('gebruiker_id', $gebruiker->id)
            ->where('gegenereerd_op', '>=', $grens)
            ->exists();
    }
}
