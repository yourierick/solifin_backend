<?php

namespace App\Services;

use App\Models\BonusRates;
use App\Models\User;
use App\Models\UserBonusPoint;
use App\Models\UserBonusPointHistory;
use App\Models\Pack;
use App\Models\UserPack;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service pour gérer l'attribution et le calcul des points bonus
 * Ce service s'occupe de vérifier les parrainages des utilisateurs
 * et d'attribuer des points selon les règles configurées pour chaque pack
 */
class BonusPointsService
{
    /**
     * Traite l'attribution des points bonus pour tous les utilisateurs
     * Cette méthode peut être appelée par une tâche planifiée ou manuellement
     * 
     * @param string|null $frequency Fréquence spécifique à traiter (daily, weekly, monthly, yearly) ou null pour toutes
     * @return array Statistiques sur les points attribués
     */
    public function processAllBonusPoints($frequency = null)
    {
        $stats = [
            'users_processed' => 0,
            'points_attributed' => 0,
            'errors' => 0
        ];
        
        try {
            // Si une fréquence spécifique est demandée, traiter uniquement celle-ci
            if ($frequency) {
                return $this->processBonusPointsByFrequency($frequency);
            }
            
            // Sinon, traiter toutes les fréquences
            $frequencies = ['daily', 'weekly', 'monthly', 'yearly'];
            foreach ($frequencies as $freq) {
                $result = $this->processBonusPointsByFrequency($freq);
                $stats['users_processed'] += $result['users_processed'];
                $stats['points_attributed'] += $result['points_attributed'];
                $stats['errors'] += $result['errors'];
            }
            
            return $stats;
        } catch (\Exception $e) {
            Log::error("Erreur lors du traitement des points bonus: " . $e->getMessage());
            return [
                'users_processed' => 0,
                'points_attributed' => 0,
                'errors' => 1,
                'error_message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Traite l'attribution des points bonus pour une fréquence spécifique
     * Pour chaque utilisateur avec des packs actifs, calcule et attribue les points bonus
     * en fonction du nombre de filleuls parrainés durant la période
     * 
     * @param string $frequency Fréquence à traiter (daily, weekly, monthly, yearly)
     * @return array Statistiques sur les points attribués
     */
    public function processBonusPointsByFrequency($frequency)
    {
        $stats = [
            'users_processed' => 0,
            'points_attributed' => 0,
            'errors' => 0
        ];
        
        try {
            // Définir la période selon la fréquence
            list($startDate, $endDate) = $this->getDateRangeForFrequency($frequency);
            
            // Récupérer tous les utilisateurs avec un pack actif
            $users = User::whereHas('packs', function($query) {
                $query->where('status', 'active')
                      ->where('payment_status', 'completed');
            })->get();
            
            foreach ($users as $user) {
                try {
                    // Récupérer tous les packs actifs de l'utilisateur
                    $userPacks = UserPack::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->where('payment_status', 'completed')
                        ->get();
                    
                    // Compter les filleuls parrainés durant la période (une seule fois par utilisateur)
                    $filleulsCount = $this->countReferralsInPeriod($user->id, $startDate, $endDate);
                    
                    // Si l'utilisateur n'a pas de filleuls pour cette période, passer au suivant
                    if ($filleulsCount <= 0) {
                        continue;
                    }
                    
                    // Pour chaque pack actif de l'utilisateur
                    foreach ($userPacks as $userPack) {
                        $pack = Pack::find($userPack->pack_id);
                        if (!$pack) {
                            continue;
                        }
                        
                        // Trouver le taux de bonus applicable pour ce pack et cette fréquence
                        $bonusRate = $this->findBonusRateForPack($pack->id, $frequency);
                        
                        if ($bonusRate && $bonusRate->nombre_filleuls > 0) {
                            // Calculer les points à attribuer (multiple du seuil)
                            $pointsToAward = 0;
                            if ($filleulsCount >= $bonusRate->nombre_filleuls) {
                                $pointsToAward = floor($filleulsCount / $bonusRate->nombre_filleuls) * $bonusRate->points_attribues;
                            }
                            
                            if ($pointsToAward > 0) {
                                // Attribuer les points
                                $userPoints = UserBonusPoint::getOrCreate($user->id, $userPack->pack_id);
                                $description = $this->getDescriptionForFrequency($frequency, $filleulsCount);
                                
                                $metadata = [
                                    'frequency' => $frequency,
                                    'filleuls_count' => $filleulsCount,
                                    'pack_id' => $pack->id,
                                    'pack_name' => $pack->name,
                                    'points_per_threshold' => $bonusRate->points_attribues,
                                    'threshold' => $bonusRate->nombre_filleuls
                                ];
                                
                                $pointsAdded = $userPoints->addPoints(
                                    $pointsToAward,
                                    $pack->id,
                                    $description,
                                    $metadata
                                );
                                
                                if ($pointsAdded) {
                                    $stats['points_attributed'] += $pointsToAward;
                                }
                            }
                        }
                    }
                    
                    $stats['users_processed']++;
                } catch (\Exception $e) {
                    Log::error("Erreur lors du traitement des points bonus pour l'utilisateur {$user->id}: " . $e->getMessage());
                    $stats['errors']++;
                }
            }
            
            return $stats;
        } catch (\Exception $e) {
            Log::error("Erreur lors du traitement des points bonus pour la fréquence $frequency: " . $e->getMessage());
            return [
                'users_processed' => 0,
                'points_attributed' => 0,
                'errors' => 1,
                'error_message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtient la plage de dates pour une fréquence donnée
     * Calcule les dates de début et de fin pour la période correspondant à la fréquence
     * 
     * @param string $frequency Fréquence (daily, weekly, monthly, yearly)
     * @return array Tableau contenant la date de début et la date de fin
     */
    private function getDateRangeForFrequency($frequency)
    {
        $now = Carbon::now();
        $startDate = null;
        $endDate = null;
        
        switch ($frequency) {
            case 'daily':
                // Aujourd'hui (de minuit à 23:59:59)
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
                break;
                
            case 'weekly':
                // Cette semaine (du lundi au dimanche)
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                break;
                
            case 'monthly':
                // Ce mois (du 1er au dernier jour du mois)
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                break;
                
            case 'yearly':
                // Cette année (du 1er janvier au 31 décembre)
                $startDate = $now->copy()->startOfYear();
                $endDate = $now->copy()->endOfYear();
                break;
                
            default:
                throw new \InvalidArgumentException("Fréquence non reconnue: $frequency");
        }
        
        return [$startDate, $endDate];
    }
    
    /**
     * Génère une description pour l'attribution des points selon la fréquence
     * 
     * @param string $frequency Fréquence (daily, weekly, monthly, yearly)
     * @param int $filleulsCount Nombre de filleuls parrainés
     * @return string Description
     */
    private function getDescriptionForFrequency($frequency, $filleulsCount)
    {
        $periodText = '';
        
        switch ($frequency) {
            case 'daily':
                $periodText = "journalier";
                break;
            case 'weekly':
                $periodText = "hebdomadaire";
                break;
            case 'monthly':
                $periodText = "mensuel";
                break;
            case 'yearly':
                $periodText = "annuel";
                break;
            default:
                $periodText = $frequency;
        }
        
        return "Bonus $periodText pour $filleulsCount filleuls parrainés";
    }
    
    /**
     * Compte le nombre de filleuls parrainés par un utilisateur durant une période donnée
     * Utilise la table user_packs pour compter les utilisateurs uniques parrainés
     * 
     * @param int $userId ID de l'utilisateur
     * @param Carbon $startDate Date de début de la période
     * @param Carbon $endDate Date de fin de la période
     * @return int Nombre de filleuls parrainés durant la période
     */
    private function countReferralsInPeriod($userId, Carbon $startDate, Carbon $endDate)
    {
        // Compter les utilisateurs uniques parrainés via user_packs
        return UserPack::where('sponsor_id', $userId)
            ->where('payment_status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('user_id')
            ->count('user_id');
    }
    
    /**
     * Trouve le taux de bonus pour un pack et une fréquence donnés
     * 
     * @param int $packId ID du pack
     * @param string $frequency Fréquence (daily, weekly, monthly, yearly)
     * @return BonusRates|null Taux de bonus ou null si aucun n'est configuré
     */
    private function findBonusRateForPack($packId, $frequency)
    {
        return BonusRates::where('pack_id', $packId)
            ->where('frequence', $frequency)
            ->first();
    }
    
    /**
     * Convertit des points en devise pour un utilisateur
     * Vérifie les conditions nécessaires et effectue la conversion
     * 
     * @param int $userId ID de l'utilisateur
     * @param int $packId ID du pack
     * @param int $points Nombre de points à convertir
     * @return array Résultat de la conversion
     */
    public function convertPointsToWallet($userId, $packId, $points)
    {
        try {
            // Valider les paramètres
            if (!$userId || !$packId || $points <= 0) {
                return [
                    'success' => false,
                    'message' => 'Paramètres invalides pour la conversion'
                ];
            }
            
            // Récupérer l'utilisateur
            $user = User::find($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ];
            }
            
            // Vérifier que le pack existe et est actif pour l'utilisateur
            $userPack = UserPack::where('user_id', $user->id)
                ->where('pack_id', $packId)
                ->where('status', 'active')
                ->where('payment_status', 'completed')
                ->first();
                
            if (!$userPack) {
                return [
                    'success' => false,
                    'message' => 'Pack non trouvé ou inactif pour cet utilisateur'
                ];
            }
            
            // Récupérer le taux de bonus pour obtenir la valeur du point
            // On utilise la fréquence hebdomadaire par défaut pour la valeur du point
            $bonusRate = $this->findBonusRateForPack($packId, 'weekly');
            if (!$bonusRate || $bonusRate->valeur_point <= 0) {
                return [
                    'success' => false,
                    'message' => 'La valeur d\'un point n\'est pas configurée pour ce pack'
                ];
            }
            
            // Récupérer les points de l'utilisateur pour ce pack spécifique
            $userPoints = UserBonusPoint::getOrCreate($userId, $packId);
            
            if ($points > $userPoints->points_disponibles) {
                return [
                    'success' => false,
                    'message' => 'Nombre de points insuffisant'
                ];
            }
            
            // Convertir les points en devise
            $amount = $userPoints->convertPointsToWallet($points);
            
            if ($amount === false) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la conversion des points'
                ];
            }
            
            return [
                'success' => true,
                'message' => "Conversion réussie de $points points en $amount devise",
                'amount' => $amount,
                'points_converted' => $points,
                'remaining_points' => $userPoints->points_disponibles
            ];
        } catch (\Exception $e) {
            Log::error("Erreur lors de la conversion des points: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la conversion des points'
            ];
        }
    }
    
    /**
     * Méthode de compatibilité pour l'ancienne implémentation
     * Permet de maintenir la compatibilité avec le code existant
     * 
     * @return array Statistiques sur les points attribués
     */
    public function processWeeklyBonusPoints()
    {
        return $this->processBonusPointsByFrequency('weekly');
    }
}
