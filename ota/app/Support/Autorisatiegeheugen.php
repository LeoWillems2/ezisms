<?php

namespace App\Support;

/**
 * Per-request-geheugen voor de `heeft-niveau`-autorisatiecheck.
 *
 * Een lijstscherm stelt dezelfde vraag ("mag deze gebruiker dit blok muteren?")
 * één keer per rij, en elke vraag kostte twee queries — de SoA kwam zo op ruim
 * 200 permissiequeries per render. Binnen één request verandert het antwoord
 * niet, dus het eerste antwoord per (gebruiker, blok, niveau) volstaat.
 *
 * Scoped in de container en geen statics: zo begint elk request (en elke test)
 * leeg. Voor een proces dat meerdere requests overleeft legen RolToewijzing en
 * RolPermissie dit geheugen bij elke mutatie, zodat een rolwijziging nooit een
 * verouderd antwoord achterlaat.
 */
class Autorisatiegeheugen
{
    /** @var array<string, bool> */
    private array $uitkomsten = [];

    public function onthoud(string $sleutel, callable $bepaal): bool
    {
        return $this->uitkomsten[$sleutel] ??= $bepaal();
    }

    public function vergeet(): void
    {
        $this->uitkomsten = [];
    }
}
