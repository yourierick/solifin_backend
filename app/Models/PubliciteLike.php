<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Publicite;

class PubliciteLike extends Model
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
    ];
    
    /**
     * Obtenir l'utilisateur qui a aimé la publicité.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Obtenir la publicité qui a été aimée.
     */
    public function publicite()
    {
        return $this->belongsTo(Publicite::class);
    }
}
