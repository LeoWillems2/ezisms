<?php

namespace App\Models;

use App\Support\Normprofiel;
use Database\Factories\MaatregelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Eén Annex A-beheersmaatregel. Referentiedata: aangemaakt door
 * MaatregelSeeder uit de normtekst, niet via de applicatie.
 */
class Maatregel extends Model
{
    /** @use HasFactory<MaatregelFactory> */
    use HasFactory;

    /** Het kennisbankartikel dat uitlegt waarom er geen normtekst wordt meegeleverd. */
    public const DISCLAIMER_SLUG = 'verantwoording-en-disclaimer';

    /** De linktekst naar dat artikel; de titel leest beter dan het pad. */
    public const DISCLAIMER_LABEL = 'Verantwoording en disclaimer';

    /**
     * Wat er in het seedbestand staat waar nog geen normtekst is ingevoerd.
     *
     * Een markering in de data, geen schermtekst — zelfde patroon als
     * {@see self::ZORGAANVULLING_GEEN}. Het scherm behandelt deze waarde precies
     * zoals een lege omschrijving: het toont {@see self::GEEN_OMSCHRIJVING_AANHEF}
     * plus de link naar de verantwoording, en de markering zelf komt er nooit op.
     *
     * Waarom een markering per regel en niet één vlag voor het bestand
     * (04f §1.2): tot 06-08-2026 keek de seeder naar de eerste rij om te bepalen
     * of het bestand nog de niet-ingevulde huls was. Half invullen leverde dan
     * 60 maatregelen met "Koop de norm" op het scherm, en dus de regel "vul het
     * bestand helemaal, of vul het niet". Per regel mág gedeeltelijk: wie tien
     * maatregelen overtypt, ziet tien teksten en 83 mededelingen.
     */
    public const OMSCHRIJVING_NIET_MEEGELEVERD = 'Dit ISMS levert bij deze maatregel geen omschrijving mee.';

    /**
     * Wat er staat als de zorgaanvullingen helemaal niet zijn ingelezen — dan is
     * per maatregel niet eens bekend óf de norm er een heeft.
     *
     * Deze stand is een **installatiefout** en geen licentiekwestie. Tot
     * 05-08-2026 stond hier "Pas aan als je de norm hebt gekocht": toen moest
     * elke installatie `maatregelen-nen7510.json` zelf genereren. Sinds dat
     * bestand wordt meegeleverd, betekent `null` alleen nog dat het ontbreekt, en
     * dan is dat advies onjuist — de norm kopen lost het niet op.
     */
    public const ZORGAANVULLING_NIET_INGELEZEN = 'Niet ingelezen: deze installatie mist het '
        .'seedbestand met de zorgaanvullingen. Meld dit bij uw beheerder.';

    /**
     * Wat er staat bij een maatregel die in NEN 7510 wél een zorgspecifieke
     * beheersmaatregel heeft, maar waarvan dit ISMS de tekst niet meelevert.
     *
     * Besloten op 05-08-2026: het NEN-bestand draagt geen normtekst meer, alleen
     * nog de driedeling. De generator schrijft deze zin op de plaatsen waar de
     * norm een aanvulling heeft en {@see self::ZORGAANVULLING_GEEN} waar niet.
     * Wat overblijft is de mededeling plus de *selectie*, en dát een maatregel
     * een zorgspecifieke beheersmaatregel heeft is openbaar bekend — daarom
     * staat het bestand wél in versiebeheer.
     *
     * Dezelfde redenering als bij {@see self::GEEN_OMSCHRIJVING_AANHEF}: zeggen
     * dát er iets is en dat wij het niet leveren, is iets anders dan zwijgen.
     * De literal staat ook in `scripts/genereer_maatregelen_seed.py`;
     * ZorgcontrolsetTest bewaakt dat de twee niet uiteenlopen.
     */
    public const ZORGAANVULLING_NIET_MEEGELEVERD = 'Dit ISMS levert bij deze maatregel geen '
        .'zorgspecifieke maatregel mee.';

    /**
     * Wat er staat bij een maatregel waar NEN 7510 géén zorgspecifieke
     * beheersmaatregel bij geeft — 79 van de 101.
     *
     * Dit was een lege string, en dat werkte prima zolang alleen de generator
     * het bestand vulde. Wie het bestand met de hand bewerkt (de situatie van
     * een uitgeleverde installatie zonder de generator, en sinds 04f de
     * gedocumenteerde werkwijze) ziet bij een lege string niet het verschil tussen "hier
     * hoort niets" en "hier moet ik nog iets neerzetten" — en een ingevulde
     * regel laat het ISMS iets beweren wat de norm niet zegt.
     *
     * Daarom een expliciete tekst. Hij is Engels en schreeuwerig omdat hij niet
     * voor de lezer van het scherm bedoeld is maar voor degene met het bestand
     * open; op het scherm komt hij nooit, want {@see self::toontZorgaanvulling()}
     * behandelt hem als "geen aanvulling".
     */
    public const ZORGAANVULLING_GEEN = 'DO NOT TOUCH';

    /**
     * Wat het scherm zegt bij een maatregel zonder normtekst.
     *
     * Zonder deze zin ziet het scherm er kapot uit in plaats van principieel:
     * sinds 04f levert het ISMS in géén enkel profiel een omschrijving mee, en
     * bij de maatregelen zonder zorgaanvulling houdt de modal dan alleen een
     * titel over. Deze zin plus de link is dus de enige plek waar de SoA de
     * herkomst van de tekst verantwoordt.
     *
     * Norm-neutraal geformuleerd: het is waar in beide profielen, en het
     * profielverhaal staat op de kennisbankpagina waar de link heen gaat — die
     * heeft sinds 00i een eigen zorgvariant.
     */
    public const GEEN_OMSCHRIJVING_AANHEF = 'Dit ISMS levert bij deze maatregel geen omschrijving mee; '
        .'u ziet de officiële titel. Waarom:';

    protected $table = 'maatregelen';

    /** @var list<string> */
    protected $fillable = ['annex_a_referentie', 'thema', 'naam', 'omschrijving', 'kenmerken', 'zorgaanvulling'];

    /** @var array<string, string> */
    protected $casts = ['kenmerken' => 'array'];

    public function soaRegel(): HasOne
    {
        return $this->hasOne(SoaRegel::class);
    }

    /**
     * Heeft deze maatregel een ingevoerde normtekst?
     *
     * Drie schrijfwijzen voor "nee", en dat is geen slordigheid: `null` is een
     * rij die de seeder nooit heeft aangeraakt, `''` een leeggemaakt veld, en
     * {@see self::OMSCHRIJVING_NIET_MEEGELEVERD} de markering uit het
     * seedbestand. Voor de lezer maken die geen verschil — het scherm toont in
     * alle drie de gevallen {@see self::GEEN_OMSCHRIJVING_AANHEF} met de link.
     */
    public function toontOmschrijving(): bool
    {
        return ! in_array(
            $this->omschrijving,
            [null, '', self::OMSCHRIJVING_NIET_MEEGELEVERD],
            true,
        );
    }

    /**
     * Moet het zorgaanvullingsblok getoond worden? (implementatie/04e §1)
     *
     * Drie toestanden, en het verschil tussen de eerste twee is het hele punt:
     * `null` betekent dat de rij niet door de seeder is aangeraakt — dan is per
     * maatregel niet eens bekend óf de norm er een heeft, en dat moet je zien.
     * {@see self::ZORGAANVULLING_GEEN} betekent "ingelezen, deze heeft er geen"
     * en toont niets.
     */
    public function toontZorgaanvulling(): bool
    {
        return Normprofiel::heeft('zorgaanvulling')
            && $this->zorgaanvulling !== self::ZORGAANVULLING_GEEN;
    }

    /** De mededeling bij deze maatregel, of de melding dat er niets is ingelezen. */
    public function zorgaanvullingTekst(): string
    {
        return $this->zorgaanvulling ?? self::ZORGAANVULLING_NIET_INGELEZEN;
    }
}
