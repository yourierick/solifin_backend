<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Publicite;

class PubliciteShare extends Model
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
        'comment',
    ];
    
    /**
     * Obtenir l'utilisateur qui a partagé la publicité.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Obtenir la publicité qui a été partagée.
     */
    public function publicite()
    {
        return $this->belongsTo(Publicite::class);
    }
}
