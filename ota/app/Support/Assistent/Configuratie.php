<?php

namespace App\Support\Assistent;

/**
 * De enige plek die leest of de assistenttoegang aan staat
 * (implementatie/14b §1.2). Routes, menu-items en schermen vragen het hier.
 */
final class Configuratie
{
    /** Het blok waarop een rij "deze rol mag een assistent koppelen" betekent (14b §2.2). */
    public const BLOK = 'assistenttoegang';

    /** De scope die laravel/mcp aan elke zelfregistrerende client geeft. */
    public const SCOPE = 'mcp:use';

    public static function isAan(): bool
    {
        return (bool) config('assistent.aan', false);
    }
}
