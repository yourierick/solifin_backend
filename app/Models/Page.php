<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nombre_abonnes',
        'nombre_likes',
        'photo_de_couverture'
    ];

    /**
     * Récupérer l'utilisateur propriétaire de cette page
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Récupérer les abonnés de cette page
     */
    public function abonnes()
    {
        return $this->hasMany(PageAbonnes::class);
    }

    /**
     * Récupérer les publicités associées à cette page
     */
    public function publicites()
    {
        return $this->hasMany(Publicite::class);
    }

    /**
     * Récupérer les offres d'emploi associées à cette page
     */
    public function offresEmploi()
    {
        return $this->hasMany(OffreEmploi::class);
    }

    /**
     * Récupérer les opportunités d'affaires associées à cette page
     */
    public function opportunitesAffaires()
    {
        return $this->hasMany(OpportuniteAffaire::class);
    }
}
