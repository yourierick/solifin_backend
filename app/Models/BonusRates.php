<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusRates extends Model
{
    protected $fillable = [
        'pack_id',
        'frequence',
        'nombre_filleuls',    // Nombre de filleuls pour obtenir 1 point (seuil)
        'points_attribues',   // Nombre de points attribués pour ce seuil
        'valeur_point',       // Valeur d'un point en devise
    ];

    /**
     * Relation avec le pack
     */
    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }
}
