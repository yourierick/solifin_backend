<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PubliciteLike;
use App\Models\PubliciteComment;
use App\Models\PubliciteShare;
use App\Models\Page;
use App\Models\User;

class Publicite extends Model
{
    use HasFactory;

    protected $table = 'publicites';

    protected $fillable = [
        'page_id',
        'pays',
        'ville',
        'type',
        'categorie',
        'sous_categorie',
        'autre_sous_categorie',
        'titre',
        'description',
        'image',
        'video',
        'contacts',
        'email',
        'adresse',
        'besoin_livreurs',
        'conditions_livraison',
        'point_vente',
        'quantite_disponible',
        'prix_unitaire_vente',
        'devise',
        'commission_livraison',
        'prix_unitaire_livraison',
        'lien',
        'statut',
        'raison_rejet',
        'etat',
        'duree_affichage',
    ];

    protected $casts = [
        'conditions_livraison' => 'array',
        'prix_unitaire_vente' => 'float',
        'prix_unitaire_livraison' => 'float',
    ];

    /**
     * Récupérer la page associée à cette publicité
     */
    public function page()
    {
        return $this->belongsTo(Page::class);
    }
    
    /**
     * Obtenir l'utilisateur associé à cette publicité via la page
     */
    public function user()
    {
        return $this->hasOneThrough(User::class, Page::class, 'id', 'id', 'page_id', 'user_id');
    }
    
    /**
     * Obtenir les likes de cette publicité.
     */
    public function likes()
    {
        return $this->hasMany(PubliciteLike::class);
    }
    
    /**
     * Obtenir les commentaires de cette publicité.
     */
    public function comments()
    {
        return $this->hasMany(PubliciteComment::class);
    }
    
    /**
     * Obtenir les partages de cette publicité.
     */
    public function shares()
    {
        return $this->hasMany(PubliciteShare::class);
    }
}
