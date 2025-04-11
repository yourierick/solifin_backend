<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\OffreEmploi;

class OffreEmploiComment extends Model
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
        'content',
        'parent_id',
    ];
    
    /**
     * Obtenir l'utilisateur qui a commenté l'offre d'emploi.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Obtenir l'offre d'emploi qui a été commentée.
     */
    public function offreEmploi()
    {
        return $this->belongsTo(OffreEmploi::class);
    }
    
    /**
     * Obtenir le commentaire parent (si c'est une réponse).
     */
    public function parent()
    {
        return $this->belongsTo(OffreEmploiComment::class, 'parent_id');
    }
    
    /**
     * Obtenir les réponses à ce commentaire.
     */
    public function replies()
    {
        return $this->hasMany(OffreEmploiComment::class, 'parent_id');
    }
}
