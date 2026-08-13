<?php

namespace App\Models;

use Illuminate\Console\Scheduling\Event as GeplandeTaak;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eén regel per uitgevoerde (of overgeslagen) geplande taak
 * (implementatie/00m §1).
 *
 * Machinale log, dus géén `Auditeerbaar` — hetzelfde onderscheid dat
 * {@see Notificatie} en {@see SynchronisatieLog} al maken. De audit trail raakt
 * dit alsnog, maar indirect en op de juiste plek: de **taak** die uit een gat
 * volgt is wél geauditeerd (00m §0.3).
 */
class Systeemhartslag extends Model
{
    /**
     * Enkelvoud, en dat is geen slordigheid: "hartslag" is een stofnaam, en
     * Eloquent zou er anders `systeemhartslags` van maken.
     */
    protected $table = 'systeemhartslag';

    /**
     * `gedraaid_op` ís het tijdstip van de regel; een `created_at` ernaast zou
     * bij het seeden van een nulpunt iets anders zeggen dan `gedraaid_op` en
     * dan is niet meer duidelijk welke van de twee de detectie gebruikt.
     */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'taak_sleutel', 'weergavenaam', 'gedraaid_op', 'resultaat', 'duur_ms', 'melding',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'gedraaid_op' => 'datetime',
        'duur_ms' => 'integer',
    ];

    /**
     * Het genormaliseerde artisan-commando uit een geplande taak (00m §0.5).
     *
     * `Event::$command` is wat de planner uitvoert, inclusief binary, artisan en
     * opties: `'/usr/bin/php8.4' 'artisan' isms:controleer-audittrail --stil`.
     * De sleutel is daarvan alleen `isms:controleer-audittrail` — de opties
     * horen er níét bij, want dan zou `--stil` toevoegen de historie afsnijden.
     *
     * `null` voor een `Schedule::call()`-taak: die heeft geen commando en dus
     * geen stabiele sleutel. Die slaan we over in plaats van er een hash voor te
     * verzinnen; er staat er vandaag geen enkele in `routes/console.php`.
     */
    public static function sleutelVoor(GeplandeTaak $taak): ?string
    {
        $commando = $taak->command;

        if ($commando === null || trim($commando) === '') {
            return null;
        }

        foreach (preg_split('/\s+/', trim($commando)) ?: [] as $deel) {
            $deel = trim($deel, "'\"");

            // Opties horen niet in de sleutel; lege delen ontstaan bij dubbele
            // spaties.
            if ($deel === '' || str_starts_with($deel, '-')) {
                continue;
            }

            // De php-binary: een pad, of kaal `php` / `php8.4`.
            if (str_contains($deel, '/') || str_contains($deel, '\\') || str_starts_with($deel, 'php')) {
                continue;
            }

            if ($deel === 'artisan') {
                continue;
            }

            return $deel;
        }

        return null;
    }

    /**
     * De laatste hartslag per sleutel. Dit is de enige query die de detectie
     * draait, en de index `(taak_sleutel, gedraaid_op)` is ervoor.
     */
    public function scopeLaatsteVoor(Builder $query, string $sleutel): Builder
    {
        return $query->where('taak_sleutel', $sleutel)->orderByDesc('gedraaid_op')->orderByDesc('id');
    }
}
