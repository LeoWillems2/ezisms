<?php

use Illuminate\Support\Facades\Schedule;

// Dagelijkse transitie Actief -> Gedeactiveerd bij bereikte vervaldatum
// (implementatie/01-identity-access.md §7).
Schedule::command('isms:verval-gebruikersaccounts')->dailyAt('01:00');

// Bewaartermijn van 3 jaar verstreken -> Gearchiveerd. Verwijdert niets
// (implementatie/06-bewijsrepository-audit-trail.md §7).
Schedule::command('isms:archiveer-bewijsstukken')->dailyAt('01:30');

// Blok 1: herinnering aan een openstaande tweede factor (implementatie/01d §9).
// Vlak na het vervallen van accounts: wie vannacht is gedeactiveerd hoeft geen
// herinnering meer te krijgen.
Schedule::command('isms:herinner-tweefactor')->dailyAt('01:15');

// De keten-hashes van de audit trail nalopen (implementatie/06c §5). Vóór de
// opruimtaken hieronder: zo gaat de controle over de trail van gisteren en niet
// half over die van vannacht.
Schedule::command('isms:controleer-audittrail --stil')->dailyAt('01:45');

// Blok 7: terugkerende taken aanmaken en verstreken taken laten verlopen
// (implementatie/07-taken-workflow-engine.md §4 en §5).
Schedule::command('isms:genereer-taken')->dailyAt('02:00');
Schedule::command('isms:verloop-taken')->dailyAt('02:15');

// Blok 5: bewaartermijn op de registratie van downloads. Verwijdert wél, anders
// dan het archiveren hierboven (implementatie/05 §14).
Schedule::command('isms:schoon-raadplegingen')->dailyAt('02:30');

// De hartslag nalopen (implementatie/00m §8). Als laatste van de nacht, en dat
// is het punt: alle commando's hierboven hebben dan hun hartslag geschreven, dus
// de controle gaat over de nacht die net voorbij is en niet half over de vorige.
//
// Dit vangt het geval "de scheduler draaide, maar één commando faalde of werd
// overgeslagen". Het andere geval — de machine was helemaal weg — wordt bij het
// opstarten gevangen, in `deploy.sh` en `deploy-docker.sh`. Allebei nodig (§8).
Schedule::command('isms:controleer-hartslag --stil')->dailyAt('02:45');

// Blok 12: maandelijkse KPI-snapshot. Maandelijks en niet dagelijks — dagelijks
// is meer ruis dan signaal voor cijfers die in maanden bewegen (implementatie/12 §2).
Schedule::command('isms:meet-kpis')->monthlyOn(1, '03:00');

// Plan 04c: jaarlijkse restrisico-snapshot per control. Aan het eind van het jaar
// zodat het peiljaar het afgesloten jaar is; de SoA wordt jaarlijks herzien, dus
// een trendpunt per jaar volstaat (implementatie/04c §2).
Schedule::command('isms:leg-restrisico-vast')->yearlyOn(12, 31, '23:00');
