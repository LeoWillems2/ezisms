<?php

namespace App\Models;

use App\Models\Concerns\Auditeerbaar;
use Database\Factories\DoelgroepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Een awareness-doelgroep met expliciet lidmaatschap, los van de
 * afdeling-doelgroep van blok 5: awareness-groepen ("IT-beheerders", "nieuwe
 * medewerkers") lopen dwars door afdelingen heen (implementatie/10 §2b).
 */
class Doelgroep extends Model
{
    /** @use HasFactory<DoelgroepFactory> */
    use Auditeerbaar, HasFactory;

    protected $table = 'doelgroepen';

    /** @var list<string> */
    protected $fillable = ['naam', 'omschrijving'];

    public function gebruikers(): BelongsToMany
    {
        return $this->belongsToMany(Gebruiker::class, 'doelgroep_gebruiker', 'doelgroep_id', 'gebruiker_id');
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Trainingsmodule::class, 'doelgroep_trainingsmodule');
    }

    public function auditBlok(): string
    {
        return 'bewustzijn-training';
    }

    public function auditOmschrijving(): string
    {
        return $this->naam;
    }
}
