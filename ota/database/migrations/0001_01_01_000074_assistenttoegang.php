<?php

use App\Models\AssistentAanroep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assistenttoegang via MCP (implementatie/14b §2).
 *
 * De vijf OAuth-tabellen zijn die van Passport 13, overgenomen in onze
 * nummering in plaats van gepubliceerd met hun tijdstempelnamen. Twee
 * verschillen met het origineel: `user_id` verwijst met een foreign key naar
 * `gebruikers` (Passport legt geen sleutel), en een token verdwijnt mee met het
 * account. De kolomnamen blijven die van Passport; het pakket leest ze.
 *
 * De tabellen komen er ook als de functie uit staat. Een migratie die afhangt
 * van een env-variabele, laat een installatie die het later aanzet met een
 * schema dat niet past bij de migratiestand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $tabel) {
            $tabel->uuid('id')->primary();
            $tabel->nullableMorphs('owner');
            $tabel->string('name');
            $tabel->string('secret')->nullable();
            $tabel->string('provider')->nullable();
            $tabel->text('redirect_uris');
            $tabel->text('grant_types');
            $tabel->boolean('revoked');
            $tabel->timestamps();
        });

        Schema::create('oauth_auth_codes', function (Blueprint $tabel) {
            $tabel->char('id', 80)->primary();
            $tabel->foreignId('user_id')->index()->constrained('gebruikers')->cascadeOnDelete();
            $tabel->foreignUuid('client_id');
            $tabel->text('scopes')->nullable();
            $tabel->boolean('revoked');
            $tabel->dateTime('expires_at')->nullable();
        });

        Schema::create('oauth_access_tokens', function (Blueprint $tabel) {
            $tabel->char('id', 80)->primary();
            $tabel->foreignId('user_id')->nullable()->index()->constrained('gebruikers')->cascadeOnDelete();
            $tabel->foreignUuid('client_id');
            $tabel->string('name')->nullable();
            $tabel->text('scopes')->nullable();
            $tabel->boolean('revoked');
            $tabel->timestamps();
            $tabel->dateTime('expires_at')->nullable();
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $tabel) {
            $tabel->char('id', 80)->primary();
            $tabel->char('access_token_id', 80)->index();
            $tabel->boolean('revoked');
            $tabel->dateTime('expires_at')->nullable();
        });

        // De device-flow staat uit (14b §15), maar Passport 13 verwacht de
        // tabel bij het opruimen (`passport:purge`).
        Schema::create('oauth_device_codes', function (Blueprint $tabel) {
            $tabel->char('id', 80)->primary();
            $tabel->foreignId('user_id')->nullable()->index()->constrained('gebruikers')->cascadeOnDelete();
            $tabel->foreignUuid('client_id')->index();
            $tabel->char('user_code', 8)->unique();
            $tabel->text('scopes');
            $tabel->boolean('revoked');
            $tabel->dateTime('user_approved_at')->nullable();
            $tabel->dateTime('last_polled_at')->nullable();
            $tabel->dateTime('expires_at')->nullable();
        });

        // Wie wat via een assistent heeft opgevraagd (14b §2.3, §7). Leesacties
        // horen niet in de audit trail; die is voor mutaties.
        Schema::create('assistent_aanroepen', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('gebruiker_id')->constrained('gebruikers')->cascadeOnDelete();

            // Geen foreign key: een client die wordt opgeruimd, laat de log
            // staan. Daarom gaat de naam mee als kopie.
            $tabel->uuid('client_id')->nullable();
            $tabel->string('client_naam')->nullable();

            $tabel->enum('soort', AssistentAanroep::SOORTEN);
            $tabel->string('naam', 64);
            $tabel->json('argumenten')->nullable();
            $tabel->unsignedInteger('aantal_resultaten')->nullable();
            $tabel->unsignedBigInteger('bewijsstuk_id')->nullable();
            $tabel->enum('uitkomst', AssistentAanroep::UITKOMSTEN);
            $tabel->unsignedInteger('duur_ms');
            $tabel->timestamp('aangeroepen_op');

            $tabel->index(['gebruiker_id', 'aangeroepen_op']);
            $tabel->index('aangeroepen_op');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistent_aanroepen');
        Schema::dropIfExists('oauth_device_codes');
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_auth_codes');
        Schema::dropIfExists('oauth_clients');
    }
};
