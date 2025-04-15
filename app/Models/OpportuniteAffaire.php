<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\OpportuniteAffaireLike;
use App\Models\OpportuniteAffaireComment;
use App\Models\OpportuniteAffaireShare;
use App\Models\Page;
use App\Models\User;

class OpportuniteAffaire extends Model
{
    use HasFactory;

    protected $table = 'opportunites_affaires';

    protected $fillable = [
        'page_id',
        'titre',
        'secteur',
        'description',
        'benefices_attendus',
        'investissement_requis',
        'devise',
        'duree_retour_investissement',
        'image',
        'localisation',
        'contacts',
        'email',
        'opportunity_file',
        'lien',
        'conditions_participation',
        'date_limite',
        'statut',
        'raison_rejet',
        'etat',
    ];

    protected $casts = [
        'investissement_requis' => 'float',
        'date_limite' => 'date',
    ];

    /**
     * Récupérer la page associée à cette opportunité d'affaire
     */
    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Récupérer l'utilisateur associé à cette opportunité d'affaire via la page
     */
    public function user()
    {
        return $this->hasOneThrough(User::class, Page::class, 'id', 'id', 'page_id', 'user_id');
    }
    
    /**
     * Obtenir les likes de cette opportunité d'affaire.
     */
    public function likes()
    {
        return $this->hasMany(OpportuniteAffaireLike::class);
    }
    
    /**
     * Obtenir les commentaires de cette opportunité d'affaire.
     */
    public function comments()
    {
        return $this->hasMany(OpportuniteAffaireComment::class);
    }
    
    /**
     * Obtenir les partages de cette opportunité d'affaire.
     */
    public function shares()
    {
        return $this->hasMany(OpportuniteAffaireShare::class);
    }
}
