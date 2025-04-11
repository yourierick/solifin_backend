<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\OpportuniteAffaire;

class OpportuniteAffaireLike extends Model
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
    ];
    
    /**
     * Obtenir l'utilisateur qui a aimé l'opportunité d'affaire.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Obtenir l'opportunité d'affaire qui a été aimée.
     */
    public function opportuniteAffaire()
    {
        return $this->belongsTo(OpportuniteAffaire::class);
    }
}
