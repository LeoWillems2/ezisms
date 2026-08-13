<?php

namespace App\Support;

use App\Models\Bewijsstuk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Het opslaan van een bewijsstuk zit hier en niet in een Livewire-component,
 * omdat straks meerdere schermen ernaar uploaden: het herbruikbare
 * BewijsPaneel, het bewijsstukkenoverzicht en in blok 4.4 de beleidsversie.
 */
final class Bewijsopslag
{
    /** Bewaartermijn uit deelproducten/06 §7: één certificeringscyclus. */
    public const BEWAARJAREN = 3;

    public static function bewaar(UploadedFile $bestand, string $naam, ?string $omschrijving = null): Bewijsstuk
    {
        $inhoud = $bestand->get();

        // Hash vóór opslag: maakt achteraf verifieerbaar dat het bestand op
        // schijf nog is wat er is geüpload (implementatie/06 §3a).
        $hash = hash('sha256', $inhoud);

        $pad = $bestand->store(now()->format('Y/m'), Bewijsstuk::DISK);

        return Bewijsstuk::create([
            'naam' => $naam,
            'omschrijving' => $omschrijving,
            'bestandsnaam' => $bestand->getClientOriginalName(),
            'bestandstype' => $bestand->getMimeType() ?? 'application/octet-stream',
            'bestandsgrootte' => strlen($inhoud),
            'opslaglocatie_referentie' => $pad,
            'bestandshash' => $hash,
            'geupload_door' => auth()->id(),
            'geupload_op' => now(),
            'bewaren_tot' => now()->addYears(self::BEWAARJAREN),
        ]);
    }

    public static function verwijderBestand(Bewijsstuk $bewijsstuk): void
    {
        Storage::disk(Bewijsstuk::DISK)->delete($bewijsstuk->opslaglocatie_referentie);
    }
}
