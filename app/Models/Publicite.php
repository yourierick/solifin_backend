<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Publicite extends Model
{
    use HasFactory;

    protected $table = 'publicites';

    protected $fillable = [
        'page_id',
        'categorie',
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
     * Récupérer l'utilisateur associé à cette publicité via la page
     */
    public function user()
    {
        return $this->hasOneThrough(User::class, Page::class, 'id', 'id', 'page_id', 'user_id');
    }
}
