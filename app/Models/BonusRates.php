<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusRates extends Model
{
    protected $fillable = [
        'pack_id',
        'frequence',
        'nombre_filleuls',
        'taux_bonus',
    ];

    //Relation avec le pack
    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }
}
