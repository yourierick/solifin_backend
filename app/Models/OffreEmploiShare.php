<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\OffreEmploi;

class OffreEmploiShare extends Model
{
    use HasFactory;
    
    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'offre_emploi_id',
        'comment',
    ];
    
    /**
     * Obtenir l'utilisateur qui a partagé l'offre d'emploi.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Obtenir l'offre d'emploi qui a été partagée.
     */
    public function offreEmploi()
    {
        return $this->belongsTo(OffreEmploi::class);
    }
}
