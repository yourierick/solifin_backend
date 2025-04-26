<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Wallet;
use App\Models\UserBonusPointHistory;
use App\Models\Pack;
use App\Models\User;
use App\Models\BonusRates;

/**
 * Modèle pour stocker les points bonus des utilisateurs
 * Chaque utilisateur peut accumuler des points en parrainant un certain nombre
 * de filleuls dans une période donnée (semaine, mois, etc.)
 */
class UserBonusPoint extends Model
{
    protected $table = 'user_bonus_points';
    
    protected $fillable = [
        'user_id',
        'pack_id',
        'points_disponibles',
        'points_utilises',
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
     * Relation avec l'historique des points
     */
    public function history()
    {
        return $this->hasMany(UserBonusPointHistory::class, 'user_id', 'user_id');
    }
    
    /**
     * Ajoute des points au compte de l'utilisateur
     * 
     * @param int $points Nombre de points à ajouter
     * @param int $packId ID du pack associé
     * @param string $description Description de l'ajout de points
     * @param array $metadata Métadonnées supplémentaires
     * @return bool
     */
    public function addPoints($points, $packId, $description = null, $metadata = [])
    {
        if ($points <= 0) {
            return false;
        }
        
        $this->points_disponibles += $points;
        $saved = $this->save();
        
        if ($saved) {
            // Enregistrer dans l'historique
            UserBonusPointHistory::create([
                'user_id' => $this->user_id,
                'pack_id' => $packId,
                'points' => $points,
                'type' => 'gain',
                'description' => $description ?: 'Gain de points bonus',
                'metadata' => json_encode($metadata),
            ]);
        }
        
        return $saved;
    }
    
    /**
     * Convertit des points en devise pour le wallet de l'utilisateur
     * 
     * @param int $points Nombre de points à convertir
     * @return bool|float Montant ajouté au wallet ou false en cas d'échec
     */
    public function convertPointsToWallet($points)
    {
        if ($points <= 0 || $points > $this->points_disponibles) {
            return false;
        }
        
        // Récupérer la valeur du point pour ce pack spécifique
        $bonusRate = BonusRates::where('pack_id', $this->pack_id)
            ->where('frequence', 'weekly') // Par défaut, on utilise la fréquence hebdomadaire
            ->first();
            
        if (!$bonusRate) {
            return false; // Pas de taux défini pour ce pack
        }
        
        $valuePerPoint = $bonusRate->valeur_point;
        $amount = $points * $valuePerPoint;
        
        // Mettre à jour les points
        $this->points_disponibles -= $points;
        $this->points_utilises += $points;
        $saved = $this->save();
        
        if ($saved) {
            // Enregistrer dans l'historique
            UserBonusPointHistory::create([
                'user_id' => $this->user_id,
                'pack_id' => $this->pack_id,
                'points' => -$points, // Négatif car c'est une utilisation
                'type' => 'conversion',
                'description' => "Conversion de $points points en $amount devise (Pack: {$this->pack->name})",
                'metadata' => json_encode([
                    'value_per_point' => $valuePerPoint,
                    'amount' => $amount,
                    'pack_id' => $this->pack_id
                ]),
            ]);
            
            // Ajouter le montant au wallet de l'utilisateur
            $wallet = Wallet::where('user_id', $this->user_id)->first();
            if ($wallet) {
                // Utiliser la méthode addFunds du modèle Wallet
                $wallet->addFunds($amount, 'bonus_points', 'approved', [
                    'points_converted' => $points,
                    'value_per_point' => $valuePerPoint,
                    'pack_id' => $this->pack_id,
                    'pack_name' => $this->pack->name
                ]);
                
                return $amount;
            }
        }
        
        return false;
    }
    
    /**
     * Récupère ou crée un enregistrement de points pour un utilisateur et un pack
     * 
     * @param int $userId ID de l'utilisateur
     * @param int $packId ID du pack
     * @return UserBonusPoint
     */
    public static function getOrCreate($userId, $packId)
    {
        $userPoints = self::where('user_id', $userId)
                          ->where('pack_id', $packId)
                          ->first();
        
        if (!$userPoints) {
            $userPoints = self::create([
                'user_id' => $userId,
                'pack_id' => $packId,
                'points_disponibles' => 0,
                'points_utilises' => 0
            ]);
        }
        
        return $userPoints;
    }
    
    /**
     * Récupère tous les points bonus d'un utilisateur regroupés par pack
     * 
     * @param int $userId ID de l'utilisateur
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllUserPoints($userId)
    {
        return self::where('user_id', $userId)
                   ->with('pack') // Charger la relation pack
                   ->get();
    }
}
