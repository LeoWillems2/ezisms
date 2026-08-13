<?php

namespace App\Rules;

use App\Models\Gebruiker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Server-kant van `Gebruiker::kiesbaar()`: een gekozen gebruiker-id moet een
 * actief account zijn — of gelijk aan de al opgeslagen waarde ($behoud), zodat
 * een bestaande, inmiddels gedeactiveerde toewijzing ongewijzigd bewaard mag
 * blijven. Zonder deze rule zou de UI-filtering te omzeilen zijn met een
 * geknutseld verzoek.
 *
 * Geef als $behoud de waarde uit de database mee (niet uit het formulier — dat
 * is precies wat een aanvaller manipuleert). Leegte laat deze rule met rust; dat
 * regelt `required`/`nullable`.
 */
class KiesbareGebruiker implements ValidationRule
{
    /** @var array<int> */
    private array $behoudIds;

    public function __construct(int|string|array|null $behoud = null)
    {
        $this->behoudIds = collect(is_array($behoud) ? $behoud : [$behoud])
            ->reject(fn ($id) => $id === null || $id === '')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $id = (int) $value;

        if (in_array($id, $this->behoudIds, true)) {
            return;
        }

        if (! Gebruiker::whereKey($id)->selecteerbaar()->exists()) {
            $fail('De gekozen gebruiker is niet (meer) beschikbaar; kies een actief account.');
        }
    }
}
