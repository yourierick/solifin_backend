<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'conditions_participation',
        'date_limite',
        'statut',
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
}
