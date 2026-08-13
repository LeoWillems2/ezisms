<?php

namespace App\Support;

use App\Models\Ketencontrole;
use Illuminate\Support\Facades\DB;

/**
 * De keten over `audit_logregels`: aanleggen, nalopen, en de kop opvragen
 * (implementatie/06c).
 *
 * Werkt bewust op de query builder en niet op `AuditLogregel`. Twee redenen: de
 * ruwe kolomwaarden zijn wat gehasht wordt (geen casts ertussen), en het model
 * verbiedt terecht elke update — terwijl verzegelen precies dat doet. Dat is de
 * enige plek in de applicatie waar een logregel wordt aangeraakt nadat hij is
 * geschreven, en het is er één die om bevestiging vraagt of in een migratie zit.
 */
final class Audittrailketen
{
    private const BLOKGROOTTE = 500;

    /** De hash van de laatste regel: het kopstuk van de keten. */
    public static function kop(): ?string
    {
        return DB::table('audit_logregels')->orderByDesc('id')->value('hash');
    }

    /**
     * Legt de keten opnieuw aan over alle bestaande regels.
     *
     * **Dit legt de inhoud vast zoals die nú is en zegt niets over wat er
     * daarvóór is gebeurd** (06c §7). Vandaar de vastlegging: de
     * `verzegeld`-rij markeert waar de bewijskracht van de trail begint, zodat
     * daar later geen misverstand over kan ontstaan.
     */
    public static function verzegel(string $reden): Ketencontrole
    {
        $vorige = null;
        $regels = 0;
        $totId = null;

        // In één transactie, en dat is geen optimalisatie maar een eis: een
        // verzegeling die halverwege afbreekt laat een keten achter die deels
        // oud en deels nieuw is, en die is nergens meer aan te repareren behalve
        // met een volgende verzegeling. Het scheelt en passant twee ordes van
        // grootte — 2190 losse commits op MySQL duurden minuten.
        DB::transaction(function () use (&$vorige, &$regels, &$totId) {
            DB::table('audit_logregels')->orderBy('id')->chunk(
                self::BLOKGROOTTE,
                function ($rijen) use (&$vorige, &$regels, &$totId) {
                    foreach ($rijen as $rij) {
                        $hash = Ketenhash::van($rij, $vorige);

                        DB::table('audit_logregels')
                            ->where('id', $rij->id)
                            ->update(['hash' => $hash, 'vorige_hash' => $vorige]);

                        $vorige = $hash;
                        $regels++;
                        $totId = $rij->id;
                    }
                }
            );
        });

        return Ketencontrole::create([
            'tijdstip' => now(),
            'soort' => 'verzegeld',
            'intact' => true,
            'regels' => $regels,
            'tot_id' => $totId,
            'kapotte_id' => null,
            'kophash' => $vorige,
            'reden' => $reden,
        ]);
    }

    /**
     * Loopt de keten na en levert de uitslag — **niet opgeslagen**; de aanroeper
     * beslist of dit een vastgelegde controle is.
     *
     * Stopt bij de eerste breuk. Doorlopen daarna levert alleen ruis op: alles
     * erna wijkt per definitie af.
     *
     * @param  int|null  $vanaf  begin bij dit regelnummer; de hash van de regel
     *                           ervóór wordt dan als gegeven aangenomen (06c §5)
     */
    public static function controleer(?int $vanaf = null): Ketencontrole
    {
        $verwacht = $vanaf === null
            ? null
            : DB::table('audit_logregels')->where('id', '<', $vanaf)->orderByDesc('id')->value('hash');

        $regels = 0;
        $totId = null;
        $kapotteId = null;

        DB::table('audit_logregels')
            ->when($vanaf !== null, fn ($q) => $q->where('id', '>=', $vanaf))
            ->orderBy('id')
            ->chunk(self::BLOKGROOTTE, function ($rijen) use (&$verwacht, &$regels, &$totId, &$kapotteId) {
                foreach ($rijen as $rij) {
                    // Twee verschillende gebreken, en het onderscheid is nuttig:
                    // een verbroken schakel wijst op een verwijderde of
                    // ingevoegde regel, een afwijkende inhoud op een gewijzigde.
                    $schakelKlopt = $rij->vorige_hash === $verwacht;
                    $inhoudKlopt = $rij->hash !== null
                        && hash_equals((string) $rij->hash, Ketenhash::van($rij, $rij->vorige_hash));

                    if (! $schakelKlopt || ! $inhoudKlopt) {
                        $kapotteId = $rij->id;

                        return false;
                    }

                    $verwacht = $rij->hash;
                    $regels++;
                    $totId = $rij->id;
                }

                return true;
            });

        return new Ketencontrole([
            'tijdstip' => now(),
            'soort' => 'controle',
            'intact' => $kapotteId === null,
            'regels' => $regels,
            'tot_id' => $totId,
            'kapotte_id' => $kapotteId,
            'kophash' => $kapotteId === null ? $verwacht : null,
            'reden' => null,
        ]);
    }
}
