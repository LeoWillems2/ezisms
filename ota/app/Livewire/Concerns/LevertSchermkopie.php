<?php

namespace App\Livewire\Concerns;

use App\Support\Schermkopie;
use App\Support\SchermkopieNietBeschikbaar;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Geeft een scherm de knop **Kopie voor de auditor** (implementatie/12h).
 *
 * Een scherm implementeert twee methodes en heeft er verder geen omkijken naar:
 * `schermkopie()` bouwt de kopie uit de **al opgehaalde** rijen (12h §6), en
 * `kopieBlok()` zegt op welk blok het leesrecht wordt gecontroleerd.
 *
 * **Waarom `lezen` en niet `exporteren`.** Dat losse niveau bestaat wél
 * (implementatie/06 §8), maar het staat buiten de ladder: de CISO heeft
 * `muteren` en dús géén `exporteren`, en die zit nu juist naast de auditor als
 * de vraag valt. De inhoudelijke grens is een andere: de kopie bevat exact wat
 * het scherm toont, record-scoping incluis, dus wie het scherm mag zien mag een
 * kopie van wat hij ziet. De vastlegging (§9) houdt bij wie dat deed.
 */
trait LevertSchermkopie
{
    /**
     * De melding wanneer de kopie niet gemaakt kon worden (§7). Een publieke
     * eigenschap en geen flash-melding: de knop staat midden op het scherm en
     * de melding hoort er meteen naast, niet na een volgende paginalading.
     */
    public ?string $kopiefout = null;

    /** Wat er in het document komt — gedeclareerd door het scherm zelf (§5). */
    abstract protected function schermkopie(): Schermkopie;

    /** Het blok waarvan het leesrecht de kopie afdekt. */
    abstract protected function kopieBlok(): string;

    public function magKopieren(): bool
    {
        return Gate::allows('heeft-niveau', [$this->kopieBlok(), 'lezen']);
    }

    public function kopieerVoorAuditor(): ?StreamedResponse
    {
        abort_unless($this->magKopieren(), 403);

        $this->kopiefout = null;
        $kopie = $this->schermkopie();

        try {
            $inhoud = $kopie->docx();
        } catch (SchermkopieNietBeschikbaar $fout) {
            // Een knop die stil niets doet is erger dan geen knop (§7).
            $this->kopiefout = $fout->getMessage();

            return null;
        }

        // Pas ná een geslaagde conversie: een mislukte kopie is niet meegegeven.
        // Dezelfde volgorde als bij `Raadpleging` in DownloadBewijsstuk.
        $kopie->legVast();

        return response()->streamDownload(
            fn () => print ($inhoud),
            $kopie->bestandsnaam(),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }
}
