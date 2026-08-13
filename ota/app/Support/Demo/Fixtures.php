<?php

namespace App\Support\Demo;

use Illuminate\Database\Eloquent\Model;

/**
 * Leest `saasdemo/data/*.json` en houdt bij welk model bij welke fixture-sleutel
 * hoort.
 *
 * De sleutels gelden alleen binnen de fixtures; database-id's zijn pas bekend
 * tijdens het vullen. Alles wat de motor aanmaakt wordt hier geregistreerd,
 * zodat een latere gebeurtenis ("accepteer risico-15") het model terugvindt.
 *
 * Opzoeken van een onbekende sleutel is een harde fout: dat betekent dat de
 * tijdlijn iets aanspreekt wat nooit is aangemaakt, en dan klopt de rest van de
 * vulling ook niet meer.
 */
final class Fixtures
{
    /** @var array<string, array<string, mixed>> bestandsnaam zonder extensie => inhoud */
    private array $data = [];

    /** @var array<string, Model> fixture-sleutel => aangemaakt model */
    private array $modellen = [];

    public function __construct(private readonly string $map) {}

    /**
     * Het pad van een bijlage naast de JSON-bestanden, bijvoorbeeld het
     * toetsbestand dat bij een trainingsmodule hoort. De fixtures zijn niet
     * alleen data: een enkele demo brengt ook een bestand mee.
     */
    public function bijlage(string $relatiefPad): string
    {
        return rtrim($this->map, '/').'/'.ltrim($relatiefPad, '/');
    }

    public static function uit(string $map): self
    {
        $fixtures = new self($map);
        $fixtures->laad();

        return $fixtures;
    }

    private function laad(): void
    {
        $bestanden = glob(rtrim($this->map, '/').'/*.json') ?: [];

        if ($bestanden === []) {
            throw DemoFixtureFout::bij($this->map, 'geen fixtures gevonden');
        }

        foreach ($bestanden as $pad) {
            try {
                $this->data[basename($pad, '.json')] = json_decode(
                    file_get_contents($pad), true, flags: JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $e) {
                throw DemoFixtureFout::bij(basename($pad), "ongeldige JSON — {$e->getMessage()}");
            }
        }

        $vereisten = ['organisatie', 'personen', 'assets', 'leveranciers', 'risicos',
            'soa', 'beleid', 'training', 'audits', 'reviews', 'tijdlijn'];

        foreach ($vereisten as $vereist) {
            if (! isset($this->data[$vereist])) {
                throw DemoFixtureFout::bij($this->map, "{$vereist}.json ontbreekt");
            }
        }
    }

    /** @return array<string, mixed> */
    public function bestand(string $naam): array
    {
        return $this->data[$naam] ?? throw DemoFixtureFout::bij($naam, 'onbekend fixture-bestand');
    }

    /**
     * Een lijst uit een bestand, bijvoorbeeld `lijst('risicos', 'risicos')`.
     *
     * @return list<array<string, mixed>>
     */
    public function lijst(string $bestand, string $sleutel): array
    {
        return $this->bestand($bestand)[$sleutel] ?? [];
    }

    /**
     * De definitie achter een fixture-sleutel, ongeacht in welk bestand hij
     * staat. Handig voor handlers die alleen een sleutel meekrijgen.
     *
     * @return array<string, mixed>
     */
    public function definitie(string $bestand, string $lijst, string $sleutel): array
    {
        foreach ($this->lijst($bestand, $lijst) as $regel) {
            if (($regel['sleutel'] ?? null) === $sleutel) {
                return $regel;
            }
        }

        throw DemoFixtureFout::bij("{$bestand}/{$lijst}", "sleutel '{$sleutel}' bestaat niet");
    }

    /**
     * De gebeurtenis van een bepaald type met een bepaalde sleutel, waar hij ook
     * in de tijdlijn staat.
     *
     * Nodig waar één gebeurtenis de inhoud van een andere draagt: de omschrijving
     * van een non-conformiteit staat bij de `afwijking`, en de auditronde die de
     * bevinding vastlegt moet hem daar ophalen in plaats van hem te herhalen.
     *
     * @return array<string, mixed>
     */
    public function gebeurtenis(string $type, string $sleutel): array
    {
        foreach ($this->bestand('tijdlijn')['maanden'] as $maand) {
            foreach ($maand['gebeurtenissen'] as $gebeurtenis) {
                if (($gebeurtenis['type'] ?? null) === $type
                    && ($gebeurtenis['sleutel'] ?? null) === $sleutel) {
                    return $gebeurtenis;
                }
            }
        }

        throw DemoFixtureFout::bij('tijdlijn', "geen gebeurtenis '{$type}' met sleutel '{$sleutel}'");
    }

    public function onthoud(string $sleutel, Model $model): Model
    {
        $this->modellen[$sleutel] = $model;

        return $model;
    }

    public function kent(string $sleutel): bool
    {
        return isset($this->modellen[$sleutel]);
    }

    /** @template T of Model */
    public function model(string $sleutel): Model
    {
        return $this->modellen[$sleutel]
            ?? throw DemoFixtureFout::bij('tijdlijn', "verwijst naar '{$sleutel}', maar dat is nooit aangemaakt");
    }

    /**
     * Alle onthouden modellen van een klasse, in de volgorde waarin ze zijn
     * aangemaakt. Dit is wat `"sleutels": "alle_bestaande"` in de tijdlijn
     * oplevert.
     *
     * @return array<string, Model> sleutel => model
     */
    public function alleVan(string $klasse): array
    {
        return array_filter($this->modellen, fn (Model $m) => $m instanceof $klasse);
    }

    /**
     * Zet een verzamelwaarde uit de tijdlijn om in concrete sleutels.
     * Een lijst blijft zichzelf; `"alle"`-achtige woorden vragen om de
     * volledige set van dat soort.
     *
     * @param  list<string>|string  $waarde
     * @return list<string>
     */
    public function sleutels(array|string $waarde, string $klasse, string $waar): array
    {
        if (is_array($waarde)) {
            return $waarde;
        }

        if (! in_array($waarde, ['alle', 'alle_bekende', 'alle_bestaande'], true)) {
            throw DemoFixtureFout::bij($waar, "onbekende verzamelwaarde '{$waarde}'");
        }

        return array_keys($this->alleVan($klasse));
    }
}
