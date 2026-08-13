<?php

namespace App\Support\Demo;

use RuntimeException;

/**
 * Er klopt iets niet in `saasdemo/data/*.json`, of het scenario beschrijft een
 * handeling die het product niet toestaat.
 *
 * Bewust een harde fout en geen waarschuwing: een demo die half vult is erger
 * dan een demo die niet vult. De eerste ziet er goed uit en klopt niet.
 */
class DemoFixtureFout extends RuntimeException
{
    public static function bij(string $waar, string $probleem): self
    {
        return new self("{$waar}: {$probleem}");
    }
}
