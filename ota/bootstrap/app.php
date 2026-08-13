<?php

use App\Http\Middleware\VereistActiefAccount;
use App\Http\Middleware\VereistTweefactor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Het toets-callbackkanaal wordt aangeroepen vanuit een publieke
        // toetspagina zonder sessie; de token is het bewijs, niet een CSRF-token
        // (implementatie/10 §7). Zelfde uitzonderingspatroon als een
        // webhook-achtige route.
        $middleware->validateCsrfTokens(except: [
            'toetsen/callback/*',
        ]);

        // CORS staat hier bewust NIET aangezet: `HandleCors` zit al in de
        // globale stack van het framework en leest `config/cors.php`. Dat
        // bestand bestond tot 01e niet, waardoor er geen enkel pad matchte en er
        // dus ook geen koppen werden gezet. Sinds 01e draait een toets in een
        // CSP-sandbox en dus in een opake origin; het terugmelden is daarmee een
        // cross-origin verzoek geworden en heeft die koppen nodig. Alles wat
        // daarvoor nodig was, staat in config/cors.php.

        // Twee afdwingingen op de web-groep en niet op een routegroep, zodat een
        // route die later wordt toegevoegd er niet buiten valt:
        //
        // 1. Een geblokkeerd of gedeactiveerd account eruit zetten bij het
        //    eerstvolgende verzoek (implementatie/01f §4). Staat vóór de
        //    tweefactorcontrole: wie er niet meer in hoort, hoeven we niet eerst
        //    naar een instelscherm te sturen.
        // 2. Tweefactor afdwingen na de respijtperiode (implementatie/01d §9).
        $middleware->web(append: [
            VereistActiefAccount::class,
            VereistTweefactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
