<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
 * Geen publieke registratieroute: gebruikers ontstaan uitsluitend via een
 * uitnodiging door de CISO (implementatie/01-identity-access.md §3/§6).
 * Ook e-mailverificatie is vervallen — het e-mailadres is al aantoonbaar
 * bereikbaar doordat de uitnodigingslink erheen gestuurd is.
 */

Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')
        ->name('login');

    Volt::route('forgot-password', 'auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'auth.reset-password')
        ->name('password.reset');

    // Tweede stap van het inloggen: het wachtwoord is goed, de gebruiker is nog
    // niet ingelogd (implementatie/01d §7b). Vandaar de `guest`-groep.
    Volt::route('tweefactor-challenge', 'auth.tweefactor-challenge')
        ->name('tweefactor.challenge');
});

Route::middleware('auth')->group(function () {
    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');
});

Route::post('logout', Logout::class)
    ->name('logout');
