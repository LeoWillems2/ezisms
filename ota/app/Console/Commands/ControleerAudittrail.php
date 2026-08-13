<?php

namespace App\Console\Commands;

use App\Models\Ketencontrole;
use App\Support\Audittrailketen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Loopt de keten over de audit trail na (implementatie/06c §5).
 *
 * Draait elke nacht om 01:45 — vóór de opruimtaken van 02:00 en 02:30, zodat de
 * controle over de trail van gisteren gaat en niet half over die van vannacht.
 *
 * De uitslag wordt vastgelegd in `audit_ketencontroles`. Dat is het punt: een
 * auditor vraagt niet of de keten vandaag klopt, maar of hij al twee jaar elke
 * nacht is gecontroleerd.
 */
class ControleerAudittrail extends Command
{
    protected $signature = 'isms:controleer-audittrail
        {--stil : Alleen de slotregel, voor de planner}
        {--vanaf= : Begin bij dit regelnummer (na een bewuste verzegeling)}
        {--kop : Druk alleen de huidige kophash af en stop}';

    protected $description = 'Controleert de keten-hashes van de audit trail';

    public function handle(): int
    {
        if ($this->option('kop')) {
            $this->line(Audittrailketen::kop() ?? '(geen regels)');

            return self::SUCCESS;
        }

        $vanaf = $this->option('vanaf') === null ? null : (int) $this->option('vanaf');
        $stil = (bool) $this->option('stil');

        if (! $stil) {
            $this->toonVerzegeling();
        }

        $uitkomst = Audittrailketen::controleer($vanaf);
        $uitkomst->save();

        if ($uitkomst->intact) {
            $this->info($uitkomst->samenvatting().' ('.$uitkomst->regels.' regels gecontroleerd).');

            if (! $stil && $uitkomst->kophash !== null) {
                $this->line('Kophash: '.$uitkomst->kophash);
            }

            return self::SUCCESS;
        }

        // Een gebroken keten is geen foutmelding die in een logbestand mag
        // verdwijnen: hij hoort ook 's nachts ergens terecht te komen waar
        // iemand hem ziet. De vastlegging hierboven is het bewijs, dit is het
        // signaal.
        Log::error('Ketencontrole audit trail: breuk bij logregel '.$uitkomst->kapotte_id);

        $this->error($uitkomst->samenvatting().'.');
        $this->toonKapotteRegel((int) $uitkomst->kapotte_id);

        return self::FAILURE;
    }

    private function toonVerzegeling(): void
    {
        $verzegeling = Ketencontrole::query()->where('soort', 'verzegeld')->orderByDesc('id')->first();

        if ($verzegeling === null) {
            $this->warn('De keten is nog nooit verzegeld; de trail is mogelijk ouder dan de keten.');

            return;
        }

        $this->line('Laatst verzegeld op '.$verzegeling->tijdstip->lokaal()->format('d-m-Y H:i')
            .($verzegeling->reden === null ? '' : ' — '.$verzegeling->reden));
    }

    /** Wat er op de kapotte regel stond, zodat het onderzoek ergens begint. */
    private function toonKapotteRegel(int $id): void
    {
        $regel = DB::table('audit_logregels')->where('id', $id)->first();

        if ($regel === null) {
            $this->line('Regel '.$id.' bestaat niet meer.');

            return;
        }

        $this->table(['Veld', 'Waarde'], [
            ['Regel', $regel->id],
            ['Tijdstip', $regel->tijdstip],
            ['Gebruiker', $regel->gebruiker_naam],
            ['Blok', $regel->blok_naam],
            ['Entiteit', $regel->entiteit_omschrijving.' ('.$regel->entiteit_type.')'],
            ['Actie', $regel->actie],
        ]);

        $this->line('Let op: de regels ná deze zijn niet gecontroleerd — na een breuk wijkt alles af.');
    }
}
