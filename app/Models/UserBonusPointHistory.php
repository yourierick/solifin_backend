<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle pour stocker l'historique des points bonus des utilisateurs
 * Enregistre chaque gain ou utilisation de points avec les détails associés
 */
class UserBonusPointHistory extends Model
{
    protected $table = 'user_bonus_points_history';
    
    protected $fillable = [
        'user_id',
        'pack_id',
        'points',
        'type',
        'description',
        'metadata',
    ];
    
    protected $casts = [
        'metadata' => 'array',
    ];
    
    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Relation avec le pack
     */
    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }
    
    /**
     * Récupère l'historique des points d'un utilisateur
     * 
     * @param int $userId ID de l'utilisateur
     * @param int $limit Nombre d'enregistrements à récupérer
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserHistory($userId, $limit = 10)
    {
        return self::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
