<?php

namespace Tests\Feature;

use App\Models\Gebruiker;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WachtwoordHerstelTest extends TestCase
{
    use RefreshDatabase;

    public function test_actieve_gebruiker_ontvangt_een_herstellink(): void
    {
        Notification::fake();

        $gebruiker = Gebruiker::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $gebruiker->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($gebruiker, ResetPassword::class);
    }

    public function test_gedeactiveerd_account_ontvangt_geen_herstellink(): void
    {
        Notification::fake();

        $gebruiker = Gebruiker::factory()->gedeactiveerd()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $gebruiker->email)
            ->call('sendPasswordResetLink');

        Notification::assertNothingSent();
    }

    public function test_onbekend_emailadres_levert_dezelfde_melding_op(): void
    {
        Notification::fake();

        Volt::test('auth.forgot-password')
            ->set('email', 'bestaat-niet@example.com')
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_wachtwoord_wordt_daadwerkelijk_gewijzigd(): void
    {
        Notification::fake();

        $gebruiker = Gebruiker::factory()->create(['wachtwoord' => 'oud-wachtwoord']);

        Volt::test('auth.forgot-password')
            ->set('email', $gebruiker->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($gebruiker, ResetPassword::class, function ($notification) use ($gebruiker) {
            Volt::test('auth.reset-password', ['token' => $notification->token])
                ->set('email', $gebruiker->email)
                ->set('wachtwoord', 'nieuw-wachtwoord')
                ->set('wachtwoord_bevestiging', 'nieuw-wachtwoord')
                ->call('resetPassword')
                ->assertHasNoErrors();

            return true;
        });

        $this->assertTrue(Hash::check('nieuw-wachtwoord', $gebruiker->fresh()->wachtwoord));
    }
}
