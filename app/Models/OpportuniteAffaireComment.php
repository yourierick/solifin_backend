<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\OpportuniteAffaire;

class OpportuniteAffaireComment extends Model
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
        'content',
        'parent_id',
    ];
    
    /**
     * Obtenir l'utilisateur qui a commenté l'opportunité d'affaire.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Obtenir l'opportunité d'affaire qui a été commentée.
     */
    public function opportuniteAffaire()
    {
        return $this->belongsTo(OpportuniteAffaire::class);
    }
    
    /**
     * Obtenir le commentaire parent (si c'est une réponse).
     */
    public function parent()
    {
        return $this->belongsTo(OpportuniteAffaireComment::class, 'parent_id');
    }
    
    /**
     * Obtenir les réponses à ce commentaire.
     */
    public function replies()
    {
        return $this->hasMany(OpportuniteAffaireComment::class, 'parent_id');
    }
}
