<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserBonusPoint;
use App\Models\UserBonusPointHistory;
use App\Models\BonusRates;
use App\Services\BonusPointsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Contrôleur pour gérer les points bonus des utilisateurs
 * Permet aux utilisateurs de consulter leurs points et de les convertir en devise
 */
class UserBonusPointController extends Controller
{
    protected $bonusPointsService;
    
    public function __construct(BonusPointsService $bonusPointsService)
    {
        $this->bonusPointsService = $bonusPointsService;
    }
    
    /**
     * Récupère les points bonus de l'utilisateur connecté
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPoints()
    {
        $user = Auth::user();
        
        // Récupérer tous les packs actifs de l'utilisateur
        $userPacks = $user->packs()
            ->wherePivot('payment_status', 'completed')
            ->wherePivot('status', 'active')
            ->get();
            
        $pointsByPack = [];
        $totalPoints = 0;
        $totalValue = 0;
        $totalUsedPoints = 0;
        
        foreach ($userPacks as $pack) {
            // Récupérer ou créer les points bonus pour ce pack
            $userPoints = UserBonusPoint::getOrCreate($user->id, $pack->id);
            
            // Récupérer la valeur du point pour ce pack
            $bonusRate = BonusRates::where('pack_id', $pack->id)
                ->where('frequence', 'weekly')
                ->first();
                
            $valeurPoint = $bonusRate ? $bonusRate->valeur_point : 0;
            $valeurTotale = $userPoints->points_disponibles * $valeurPoint;
            
            $pointsByPack[] = [
                'pack_id' => $pack->id,
                'pack_name' => $pack->name,
                'disponibles' => $userPoints->points_disponibles,
                'utilises' => $userPoints->points_utilises,
                'valeur_point' => $valeurPoint,
                'valeur_totale' => $valeurTotale
            ];
            
            $totalPoints += $userPoints->points_disponibles;
            $totalValue += $valeurTotale;
            $totalUsedPoints += $userPoints->points_utilises;
        }
        
        // Calculer la valeur moyenne d'un point (pour l'affichage global)
        $averagePointValue = $totalPoints > 0 ? $totalValue / $totalPoints : 0;
        
        return response()->json([
            'success' => true,
            'points' => [
                'disponibles' => $totalPoints,
                'utilises' => $totalUsedPoints,
                'valeur_point' => round($averagePointValue, 2),
                'valeur_totale' => $totalValue,
                'points_par_pack' => $pointsByPack
            ]
        ]);
    }
    
    /**
     * Récupère l'historique des points bonus de l'utilisateur connecté
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPointsHistory(Request $request)
    {
        $user = Auth::user();
        $limit = $request->input('limit', 10);
        
        $history = UserBonusPointHistory::getUserHistory($user->id, $limit);
        $history = $history->map(function ($entry) {
            return [
                'id' => $entry->id,
                'pack_id' => $entry->pack_id,
                'pack_name' => $entry->pack_name,
                'type' => $entry->type,
                'description' => $entry->description,
                'points' => $entry->points,
                'date' => $entry->created_at->format('d/m/Y')
            ];
        });
        
        return response()->json([
            'success' => true,
            'history' => $history
        ]);
    }
    
    /**
     * Convertit des points bonus en devise pour le wallet de l'utilisateur
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function convertPoints(Request $request)
    {
        try {
            $request->validate([
                'points' => 'required|integer|min:1',
                'pack_id' => 'required|exists:packs,id'
            ]);
            
            DB::beginTransaction();

            $user = Auth::user();
            
            // Utiliser le service pour convertir les points
            $result = $this->bonusPointsService->convertPointsToWallet(
                $user->id, 
                $request->pack_id, 
                $request->points
            );

            
            if ($result['success']) {
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Conversion réussie! ' . $request->points . ' points ont été convertis en ' . $result['amount'] . ' $',
                    'amount' => $result['amount'],
                    'remaining_points' => $result['remaining_points']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        }
        catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur lors de l\'inscription: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription'
            ], 500);
        }
    }
}
