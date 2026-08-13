<?php

namespace App\Http\Controllers;

use App\Models\Bewijsstuk;
use App\Models\Raadpleging;
use App\Support\Bewijstoegang;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloaden loopt door de applicatie heen, nooit via een direct URL-pad naar
 * de schijf (implementatie/06 §7). De route-middleware checkt leesrecht op het
 * blok; hier komt de record-scoping erbij, want `uitvoeren` (Medewerker)
 * impliceert lezen en zou anders ieders bewijsmateriaal opvraagbaar maken.
 */
class DownloadBewijsstuk extends Controller
{
    public function __invoke(Bewijsstuk $bewijsstuk): StreamedResponse
    {
        // Dezelfde poort als de preview: volledige inzage, eigen upload, of het
        // bestand van een actief beleid (blok 5, implementatie/05 §5).
        abort_unless(Bewijstoegang::magLezen($bewijsstuk), 403);

        abort_unless(
            Storage::disk(Bewijsstuk::DISK)->exists($bewijsstuk->opslaglocatie_referentie),
            404
        );

        // Na de controles en na het bestaanscheck: een geweigerde of mislukte
        // download is geen raadpleging. Hier en niet in het beleidsscherm, want
        // dit is de enige plek waar een bestand het systeem verlaat — een
        // registratie die je op meerdere plekken moet aanroepen, wordt op den
        // duur ergens vergeten (implementatie/05 §14).
        Raadpleging::create([
            'bewijsstuk_id' => $bewijsstuk->id,
            'gebruiker_id' => auth()->id(),
            'geraadpleegd_op' => now(),
        ]);

        return Storage::disk(Bewijsstuk::DISK)->download(
            $bewijsstuk->opslaglocatie_referentie,
            $bewijsstuk->bestandsnaam,
        );
    }
}
