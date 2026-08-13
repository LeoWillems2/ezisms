<?php

namespace App\Models\Concerns;

use App\Models\AuditLogregel;
use Illuminate\Database\Eloquent\Model;

/**
 * Schrijft wijzigingen van een model naar de append-only audit trail
 * (implementatie/06-bewijsrepository-audit-trail.md §5).
 *
 * Bewust één trait in plaats van een observer per model: de logica is voor
 * alle negen auditeerbare modellen identiek. De observers in blok 3 en 4
 * (AssetObserver, RisicoObserver) blijven wat ze zijn — die leiden een veld af
 * en doen iets model-specifieks.
 *
 * @mixin Model
 */
trait Auditeerbaar
{
    /** Bij welk blok hoort deze entiteit (blok-code uit `blokken`). */
    abstract public function auditBlok(): string;

    /**
     * Attributen die nooit in de audit trail terechtkomen.
     *
     * Dit is een beveiligingscontrole, geen opmaak: zonder uitsluiting belandt
     * een wachtwoordhash leesbaar in een tabel die de Auditor mag inzien én
     * exporteren.
     *
     * @return list<string>
     */
    public function auditUitgesloten(): array
    {
        return [];
    }

    /** Leesbare momentopname voor in de logregel (§3b). */
    public function auditOmschrijving(): string
    {
        foreach (['naam', 'titel'] as $veld) {
            if (filled($this->getAttribute($veld))) {
                return (string) $this->getAttribute($veld);
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }

    public static function bootAuditeerbaar(): void
    {
        static::created(fn (Model $model) => $model->schrijfAuditregel(
            'aangemaakt',
            oud: null,
            nieuw: $model->getAttributes(),
        ));

        static::updated(function (Model $model) {
            $nieuw = $model->getChanges();

            // Een wijziging die alleen de status raakt krijgt een eigen actie —
            // dat is waar een auditor op filtert.
            $velden = array_values(array_diff(array_keys($nieuw), ['updated_at']));
            $actie = $velden === ['status'] ? 'status_gewijzigd' : 'gewijzigd';

            $model->schrijfAuditregel(
                $actie,
                oud: array_intersect_key($model->getOriginal(), $nieuw),
                nieuw: $nieuw,
            );
        });

        static::deleted(fn (Model $model) => $model->schrijfAuditregel(
            'verwijderd',
            oud: $model->getOriginal(),
            nieuw: null,
        ));
    }

    /**
     * @param  array<string, mixed>|null  $oud
     * @param  array<string, mixed>|null  $nieuw
     */
    public function schrijfAuditregel(string $actie, ?array $oud, ?array $nieuw): void
    {
        $oud = $this->filterAuditVelden($oud);
        $nieuw = $this->filterAuditVelden($nieuw);

        // Alleen ruis (bijv. een save die enkel updated_at aanraakte): niet loggen.
        if ($actie === 'gewijzigd' && $oud === null && $nieuw === null) {
            return;
        }

        $gebruiker = auth()->user();

        AuditLogregel::create([
            'tijdstip' => now(),
            'gebruiker_id' => $gebruiker?->getKey(),
            // Console-commando's draaien zonder ingelogde gebruiker; zonder deze
            // fallback faalt de dagelijkse taak op een niet-nullable kolom.
            'gebruiker_naam' => $gebruiker?->naam ?? 'Systeem (geplande taak)',
            'blok_naam' => $this->auditBlok(),
            'entiteit_type' => $this->getMorphClass(),
            'entiteit_id' => $this->getKey(),
            'entiteit_omschrijving' => $this->auditOmschrijving(),
            'actie' => $actie,
            'oude_waarde' => $oud,
            'nieuwe_waarde' => $nieuw,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $waarden
     * @return array<string, mixed>|null
     */
    private function filterAuditVelden(?array $waarden): ?array
    {
        if ($waarden === null) {
            return null;
        }

        $tebewaren = array_diff_key(
            $waarden,
            array_flip([...$this->auditUitgesloten(), 'created_at', 'updated_at'])
        );

        return $tebewaren === [] ? null : $tebewaren;
    }
}
