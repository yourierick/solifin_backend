<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Publicite;

class PubliciteComment extends Model
{
    use HasFactory;
    
    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'publicite_id',
        'content',
        'parent_id',
    ];
    
    /**
     * Obtenir l'utilisateur qui a commenté la publicité.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Obtenir la publicité qui a été commentée.
     */
    public function publicite()
    {
        return $this->belongsTo(Publicite::class);
    }
    
    /**
     * Obtenir le commentaire parent (si c'est une réponse).
     */
    public function parent()
    {
        return $this->belongsTo(PubliciteComment::class, 'parent_id');
    }
    
    /**
     * Obtenir les réponses à ce commentaire.
     */
    public function replies()
    {
        return $this->hasMany(PubliciteComment::class, 'parent_id');
    }
}
