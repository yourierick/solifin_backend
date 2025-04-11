<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\OpportuniteAffaire;

class OpportuniteAffaireShare extends Model
{
    use HasFactory;
    
    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'opportunite_affaire_id',
        'comment',
    ];
    
    /**
     * Obtenir l'utilisateur qui a partagé l'opportunité d'affaire.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Obtenir l'opportunité d'affaire qui a été partagée.
     */
    public function opportuniteAffaire()
    {
        return $this->belongsTo(OpportuniteAffaire::class);
    }
}
