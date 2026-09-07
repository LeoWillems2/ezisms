<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;

/**
 * Een Blueprint die onthoudt welke enum-kolommen er gedeclareerd worden
 * (implementatie/00s §10.2).
 *
 * Waarom niet de migratiebestanden lezen: 15 van de 86 `->enum(`-regels zijn met
 * een regex niet te herleiden — meerregelige arrays, `self::CONST` als
 * waardenlijst, en `->change()` dat een eerdere declaratie vervangt. Wie de
 * migraties gewoon dráait, ziet de waarheid, inclusief de volgorde waarin latere
 * migraties eerdere overschrijven.
 */
class VangendeBlueprint extends Blueprint
{
    /** @var array<string, list<string>> 'tabel.kolom' => toegestane waarden */
    public static array $enums = [];

    /** @param  list<string>  $allowed */
    public function enum($column, array $allowed)
    {
        self::$enums[$this->getTable().'.'.$column] = $allowed;

        return parent::enum($column, $allowed);
    }

    /**
     * Een kolom die later weer verdwijnt, hoort niet in het register te staan.
     *
     * @param  array<int, string>|mixed  $columns
     */
    public function dropColumn($columns)
    {
        foreach ((array) $columns as $kolom) {
            unset(self::$enums[$this->getTable().'.'.$kolom]);
        }

        return parent::dropColumn($columns);
    }
}
