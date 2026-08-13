<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 02b: de ontbrekende schakel tussen §4.1 (issues) en §6.1 (risico's).
 *
 * Beide deelproducten spreken deze koppeling af — 02 §5 ("issues en
 * belanghebbende-eisen uit dit blok zijn direct input voor risico-identificatie")
 * en 04 §5 zegt hetzelfde vanaf de andere kant — maar de ERD modelleerde alleen
 * `SCOPE_VERKLARING }o--o{ ISSUE`. Zonder deze tabel is §4.1 een register dat
 * nergens op uitkomt, en is de auditorvraag "waar landt deze kwestie in uw
 * risicobeoordeling?" alleen met de hand te beantwoorden.
 *
 * Kale pivot, zoals elke andere koppeltabel hier (`scope_verklaring_issue`,
 * `risicobehandeling_soa_regel`). Een `toelichting`-kolom is overwogen en
 * bewust weggelaten: de dreiging/kwetsbaarheid op het risico zegt dat al.
 *
 * `cascadeOnDelete` aan beide kanten — de koppeling heeft geen betekenis zonder
 * een van beide, en er hangt geen eigen gegeven aan dat verloren kan gaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_risico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->foreignId('risico_id')->constrained('risicos')->cascadeOnDelete();
            $table->unique(['issue_id', 'risico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_risico');
    }
};
