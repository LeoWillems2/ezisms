<?php

namespace App\Support\Demo;

use App\Models\BewijsKoppeling;
use App\Models\Bewijsstuk;
use App\Support\Bewijsopslag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Genereert de bewijsstukken van de demo: `.md`-bestanden met titel en datum,
 * verder leeg (uitgangspunt uit `saasdemo/p1`).
 *
 * **Waarom dit `Bewijsopslag::bewaar()` omzeilt** (besluit 29-07-2026): die
 * verwacht een `UploadedFile` en de motor heeft er geen — hij genereert de
 * inhoud in het geheugen. De andere weg was `UploadedFile::fake()`, en dat haalt
 * een testhelper de productiecode in voor een pad dat daar ook geladen wordt.
 *
 * De prijs is dat dit een tweede plek is die weet hoe bewijsopslag werkt. Houd
 * hem daarom zo dun mogelijk en houd hem gelijk aan `App\Support\Bewijsopslag`:
 * zelfde disk, zelfde padopbouw (`Y/m`), zelfde hash en zelfde bewaartermijn.
 * Wijzigt die klasse van opslagvorm, dan moet deze mee.
 */
final class Bewijsgenerator
{
    private int $aantal = 0;

    public function maak(string $titel, ?string $omschrijving = null, ?Model $koppelAan = null): Bewijsstuk
    {
        $inhoud = "# {$titel}\n\n"
            .'Datum: '.now()->format('d-m-Y')."\n\n"
            .($omschrijving ? "{$omschrijving}\n\n" : '')
            ."_Demobewijsstuk: dit bestand is gegenereerd door `isms:demo-vul` en bevat geen echte inhoud._\n";

        $bestandsnaam = Str::slug($titel).'-'.now()->format('Y-m-d').'.md';
        $pad = now()->format('Y/m').'/'.Str::random(40).'.md';

        Storage::disk(Bewijsstuk::DISK)->put($pad, $inhoud);

        $bewijsstuk = Bewijsstuk::create([
            'naam' => $titel,
            'omschrijving' => $omschrijving,
            'bestandsnaam' => $bestandsnaam,
            'bestandstype' => 'text/markdown',
            'bestandsgrootte' => strlen($inhoud),
            'opslaglocatie_referentie' => $pad,
            'bestandshash' => hash('sha256', $inhoud),
            'geupload_door' => auth()->id(),
            'geupload_op' => now(),
            'bewaren_tot' => now()->addYears(Bewijsopslag::BEWAARJAREN),
        ]);

        $this->aantal++;

        if ($koppelAan !== null) {
            BewijsKoppeling::create([
                'bewijsstuk_id' => $bewijsstuk->id,
                'blok_naam' => method_exists($koppelAan, 'auditBlok')
                    ? $koppelAan->auditBlok()
                    : 'bewijsrepository-audit-trail',
                'entiteit_type' => $koppelAan->getMorphClass(),
                'entiteit_id' => $koppelAan->getKey(),
            ]);
        }

        return $bewijsstuk;
    }

    /** Ruimt de bestanden van een vorige demovulling op. */
    public function ruimOp(): void
    {
        $disk = Storage::disk(Bewijsstuk::DISK);

        foreach ($disk->allDirectories() as $map) {
            $disk->deleteDirectory($map);
        }

        foreach ($disk->files() as $bestand) {
            $disk->delete($bestand);
        }
    }

    public function aantal(): int
    {
        return $this->aantal;
    }
}
