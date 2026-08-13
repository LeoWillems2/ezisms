<?php

namespace App\Support\Demo;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * De gesimuleerde tijd. M22 is de **vorige** volledige maand; M0 ligt
 * `AANTAL_MAANDEN` maanden daarvóór.
 *
 * Relatief en niet absoluut, zodat de demo op elke draaidag klopt: taken zijn
 * dan echt te laat en herbeoordelingen echt verlopen, in plaats van dat ze dat
 * op één peildatum toevallig waren.
 *
 * **Waarom de vorige maand en niet de lopende.** Het anker stond op
 * `subMonths(AANTAL_MAANDEN)->startOfMonth()`, waarmee M22 de lópende maand werd.
 * Alles wat het scenario in M22 op de maandgrens doet — `eindeMaand()`,
 * `naDagen()` — landt dan op de laatste dag van deze maand, en dus tot dertig
 * dagen ná vandaag. Die regels gingen op `/audit-log` bovenaan staan, boven het
 * echte werk van vandaag: een gesimuleerde geschiedenis die in de toekomst
 * eindigt. Een maand terug zetten legt het plafond op de laatste dag van de
 * vorige maand, en die ligt altijd in het verleden.
 *
 * Het verzetten gebeurt met `Carbon::setTestNow()`. Dat werkt omdat alle
 * bestaande code `now()` en `Carbon::today()` gebruikt — geverifieerd voor
 * `isms:meet-kpis`, `isms:genereer-taken` en `isms:verloop-taken`, die de motor
 * per maandgrens aanroept.
 */
final class Klok
{
    public const AANTAL_MAANDEN = 22;

    /** Eerste gebeurtenisdag in een maand; daarna telkens `STAP` dagen verder. */
    private const EERSTE_DAG = 3;

    private const STAP = 2;

    /** Plafond, zodat een drukke maand niet in de volgende overloopt. */
    private const LAATSTE_DAG = 26;

    private readonly CarbonImmutable $m0;

    private int $dagInMaand = self::EERSTE_DAG;

    public function __construct(?CarbonImmutable $vandaag = null)
    {
        // +1: M22 wordt de vorige maand, zodat geen enkel gesimuleerd moment —
        // ook niet het maandeinde van M22 — voorbij vandaag valt.
        $this->m0 = ($vandaag ?? CarbonImmutable::today())
            ->subMonths(self::AANTAL_MAANDEN + 1)
            ->startOfMonth();
    }

    public function datum(int $maand, int $dagInMaand = 1): CarbonImmutable
    {
        return $this->m0->addMonths($maand)->addDays($dagInMaand - 1);
    }

    /** Zet de klok op het begin van een maand en herstart de dagteller. */
    public function beginMaand(int $maand): CarbonImmutable
    {
        $this->dagInMaand = self::EERSTE_DAG;

        return $this->zet($this->datum($maand));
    }

    /**
     * De volgende gebeurtenis in deze maand: één stap verder dan de vorige.
     * Zonder dit staan veertig audit-trailregels op dezelfde seconde en is de
     * volgorde van de dag niet meer te lezen.
     */
    public function volgendeGebeurtenis(int $maand): CarbonImmutable
    {
        $dag = min($this->dagInMaand, self::LAATSTE_DAG);
        $this->dagInMaand += self::STAP;

        return $this->zet($this->datum($maand, $dag));
    }

    /**
     * Een aantal dagen verder binnen dezelfde maand, begrensd op de laatste dag
     * ervan. Voor doorlooptijden die de fixtures in dagen geven — een incident
     * dat na twee dagen is opgelost, hoort ook twee dagen later in de audit
     * trail te staan.
     *
     * De begrenzing voorkomt dat een lange doorlooptijd de maandlus voorbijloopt
     * en handelingen in een maand schrijft die nog niet is begonnen.
     */
    public function naDagen(int $dagen, int $maand): CarbonImmutable
    {
        $doel = CarbonImmutable::now()->addDays($dagen);
        $grens = $this->datum($maand)->endOfMonth()->startOfDay();

        return $this->zet($doel->greaterThan($grens) ? $grens : $doel);
    }

    /** Het einde van de maand, voor de terugkerende commando's. */
    public function eindeMaand(int $maand): CarbonImmutable
    {
        return $this->zet($this->datum($maand)->endOfMonth()->startOfDay());
    }

    /**
     * De jaargrenzen die in deze maand vallen. Bij 22 maanden zijn dat er twee
     * over de hele tijdlijn; welke kalenderjaren dat zijn hangt van de draaidag
     * af, en dat is inherent aan een relatieve tijdlijn.
     *
     * @return list<CarbonImmutable>
     */
    public function jaargrenzenIn(int $maand): array
    {
        $begin = $this->datum($maand);
        $eind = $begin->endOfMonth();
        $oudjaar = $begin->copy()->setDate($begin->year, 12, 31)->startOfDay();

        return $oudjaar->betweenIncluded($begin, $eind) ? [$oudjaar] : [];
    }

    public function zet(CarbonImmutable $moment): CarbonImmutable
    {
        Carbon::setTestNow($moment);

        return $moment;
    }

    /**
     * Terugzetten is verplicht en hoort in een `finally`: een proces dat met een
     * verzette klok eindigt, schrijft de rest van zijn werk in het verleden.
     */
    public static function herstel(): void
    {
        Carbon::setTestNow();
    }

    public function nu(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }
}
