<?php

use App\Http\Middleware\Assistent\LegAssistentAanroepVast;
use App\Http\Middleware\Assistent\MagAssistentKoppelen;
use App\Http\Middleware\Assistent\VereistActiefAccountViaToken;
use App\Mcp\IsmsServer;
use App\Support\Assistent\Configuratie;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;
use Laravel\Passport\Http\Middleware\CheckToken;

/*
 * Assistenttoegang via MCP (implementatie/14b §3, §4).
 *
 * Uit = niets geregistreerd. Geen /mcp, geen /oauth, geen discovery: voor een
 * installatie die het niet wil, bestaat het endpoint niet. Dit bestand wordt
 * gelezen bij `route:cache`, dus na het omzetten van ISMS_ASSISTENT is een
 * herstart nodig (14b §10.3).
 */
if (! Configuratie::isAan()) {
    return;
}

/*
 * De routes van Passport, met de hand en niet via Passport zelf
 * (AppServiceProvider zet `Passport::ignoreRoutes()`). Twee redenen: ze
 * moeten mee uit kunnen met de rest, en de toestemmingsstap moet achter
 * `MagAssistentKoppelen`. Alleen wat de authorization-code-flow nodig heeft:
 * geen device-flow, geen transient tokens, geen clientbeheer.
 *
 * De toestemmingsroutes staan in de web-groep, dus `VereistActiefAccount` en
 * `VereistTweefactor` gelden (14b §3.1).
 */
Route::prefix('oauth')->name('passport.')->group(function () {
    Route::get('authorize', [AuthorizationController::class, 'authorize'])
        ->middleware(['web', MagAssistentKoppelen::class])
        ->name('authorizations.authorize');

    Route::middleware(['web', 'auth', MagAssistentKoppelen::class])->group(function () {
        Route::post('authorize', [ApproveAuthorizationController::class, 'approve'])
            ->name('authorizations.approve');
        Route::delete('authorize', [DenyAuthorizationController::class, 'deny'])
            ->name('authorizations.deny');
    });

    // Code en verversingstoken inwisselen. De client heeft hier geen sessie.
    Route::post('token', [AccessTokenController::class, 'issueToken'])
        ->middleware('throttle:assistent-token')
        ->name('token');
});

// Discovery (RFC 9728/8414) en de dynamische clientregistratie. Het
// registratie-endpoint is anoniem en krijgt daarom een eigen, krappe limiet
// (14b §3.2). Met de hand, zodat die limiet niet ook op de discovery-adressen
// komt; die vraagt een client bij elke verbinding op.
Mcp::oauthRoutes(registration: false);

Route::post('oauth/register', OAuthRegisterController::class)
    ->middleware('throttle:assistent-registratie')
    ->name('mcp.oauth.register');

Mcp::web('/mcp', IsmsServer::class)->middleware([
    'auth:api',
    CheckToken::using(Configuratie::SCOPE),
    VereistActiefAccountViaToken::class,
    // Vaste waarden tussen aanhalingstekens, zoals in routes/web.php: zonder
    // zoekt `can:` een routeparameter met die naam.
    "can:heeft-niveau,'".Configuratie::BLOK."','lezen'",
    'throttle:assistent',
    LegAssistentAanroepVast::class,
])->name('mcp');
