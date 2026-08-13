<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classificatieschema extends Model
{
    protected $table = 'classificatieschemas';

    /** @var list<string> */
    protected $fillable = ['dimensie', 'niveau', 'omschrijving', 'omgangsregels'];
}
