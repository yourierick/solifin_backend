<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\OffreEmploiLike;
use App\Models\OffreEmploiComment;
use App\Models\OffreEmploiShare;
use App\Models\Page;

class OffreEmploi extends Model
{
    use HasFactory;

    protected $table = 'offres_emploi';

    protected $fillable = [
        'page_id',
        'titre',
        'entreprise',
        'lieu',
        'type_contrat',
        'description',
        'competences_requises',
        'experience_requise',
        'niveau_etudes',
        'salaire',
        'devise',
        'avantages',
        'date_limite',
        'email_contact',
        'contacts',
        'lien',
        'offer_file',
        'statut',
        'etat',
    ];

    protected $dates = [
        'date_limite',
    ];
    
    /**
     * Obtenir les likes de cette offre d'emploi.
     */
    public function likes()
    {
        return $this->hasMany(OffreEmploiLike::class);
    }
    
    /**
     * Obtenir les commentaires de cette offre d'emploi.
     */
    public function comments()
    {
        return $this->hasMany(OffreEmploiComment::class);
    }
    
    /**
     * Obtenir les partages de cette offre d'emploi.
     */
    public function shares()
    {
        return $this->hasMany(OffreEmploiShare::class);
    }

    /**
     * Récupérer la page associée à cette offre d'emploi
     */
    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Récupérer l'utilisateur associé à cette offre d'emploi via la page
     */
    public function user()
    {
        return $this->hasOneThrough(User::class, Page::class, 'id', 'id', 'page_id', 'user_id');
    }
}
