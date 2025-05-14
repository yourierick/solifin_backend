<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\UserBonusPoint;
use App\Models\UserBonusPointHistory;
use App\Models\Pack;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FinanceController extends Controller
{
    /**
     * Récupérer toutes les transactions de l'utilisateur connecté
     */
    public function getTransactions(Request $request)
    {
        try {
            $user = Auth::user();
            $wallet = Wallet::where('user_id', $user->id)->first();
            
            if (!$wallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun portefeuille trouvé pour cet utilisateur'
                ], 404);
            }
            
            $query = WalletTransaction::where('wallet_id', $wallet->id)
                ->orderBy('created_at', 'desc');
            
            // Filtrer par type si spécifié
            if ($request->has('type') && !empty($request->type)) {
                $query->where('type', $request->type);
            }
            
            // Filtrer par statut si spécifié
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }
            
            // Filtrer par date de début si spécifiée
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            
            // Filtrer par date de fin si spécifiée
            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            
            // Filtrer par pack si spécifié (dans les métadonnées)
            if ($request->has('pack_id') && !empty($request->pack_id)) {
                $query->whereJsonContains('metadata->pack_id', (int)$request->pack_id);
            }
            
            $transactions = $query->paginate(10);
            
            return response()->json([
                'success' => true,
                'data' => $transactions,
                'wallet' => [
                    'balance' => $wallet->balance,
                    'total_earned' => $wallet->total_earned,
                    'total_withdrawn' => $wallet->total_withdrawn
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transactions: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer les statistiques des transactions par type
     */
    public function getTransactionStatsByType(Request $request)
    {
        try {
            $user = Auth::user();
            $wallet = Wallet::where('user_id', $user->id)->first();
            
            if (!$wallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun portefeuille trouvé pour cet utilisateur'
                ], 404);
            }
            
            $query = WalletTransaction::where('wallet_id', $wallet->id);
            
            // Filtrer par date de début si spécifiée
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            
            // Filtrer par date de fin si spécifiée
            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            
            // Regrouper par type et calculer les totaux
            $stats = $query->select('type', 
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('MIN(created_at) as first_transaction'),
                DB::raw('MAX(created_at) as last_transaction')
            )
            ->groupBy('type')
            ->get();
            
            // Calculer le total général
            $totalAmount = $query->sum('amount');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'total_amount' => $totalAmount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer les types de transactions disponibles pour l'utilisateur
     */
    public function getTransactionTypes()
    {
        try {
            $user = Auth::user();
            $wallet = Wallet::where('user_id', $user->id)->first();
            
            if (!$wallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun portefeuille trouvé pour cet utilisateur'
                ], 404);
            }
            
            $types = WalletTransaction::where('wallet_id', $wallet->id)
                ->select('type')
                ->distinct()
                ->pluck('type');
                
            return response()->json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des types de transactions: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer le solde du portefeuille de l'utilisateur
     */
    public function getWalletBalance()
    {
        try {
            $user = Auth::user();
            $wallet = Wallet::where('user_id', $user->id)->first();
            
            if (!$wallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun portefeuille trouvé pour cet utilisateur'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => $wallet->balance,
                    'total_earned' => $wallet->total_earned,
                    'total_withdrawn' => $wallet->total_withdrawn
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du solde: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer l'historique des points bonus de l'utilisateur
     */
    public function getBonusPointsHistory(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = UserBonusPointHistory::where('user_id', $user->id)
                ->with(['pack'])
                ->orderBy('created_at', 'desc');
            
            // Filtrer par type si spécifié
            if ($request->has('type') && !empty($request->type)) {
                $query->where('type', $request->type);
            }
            
            // Filtrer par pack si spécifié
            if ($request->has('pack_id') && !empty($request->pack_id)) {
                $query->where('pack_id', $request->pack_id);
            }
            
            // Filtrer par date de début si spécifiée
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            
            // Filtrer par date de fin si spécifiée
            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }
            
            // Recherche par terme si spécifié
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = '%' . $request->search . '%';
                $query->where(function($q) use ($searchTerm) {
                    $q->where('description', 'like', $searchTerm)
                      ->orWhere('id', 'like', $searchTerm);
                });
            }
            
            $history = $query->paginate(10);
            
            // Récupérer le total des points disponibles par pack
            $bonusPoints = UserBonusPoint::where('user_id', $user->id)
                ->with('pack')
                ->get();

            $points_dispo_total = $bonusPoints->sum('points_disponibles');
            $points_utilises_total = $bonusPoints->sum('points_utilises');
            $userPointsBonus = [];
            $userPointsBonus['points_dispos_total'] = $points_dispo_total;
            $userPointsBonus['points_utilises_total'] = $points_utilises_total;

            return response()->json([
                'success' => true,
                'data' => [
                    'history' => $history,
                    'bonus_points' => $bonusPoints,
                    'userPointsBonus' => $userPointsBonus,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique des points bonus: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer les statistiques des points bonus de l'utilisateur
     */
    public function getBonusPointsStats(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Définir la période de filtrage
            $startDate = $request->has('date_from') && !empty($request->date_from) 
                ? Carbon::parse($request->date_from)->startOfDay() 
                : Carbon::now()->subMonths(1)->startOfDay();
                
            $endDate = $request->has('date_to') && !empty($request->date_to) 
                ? Carbon::parse($request->date_to)->endOfDay() 
                : Carbon::now()->endOfDay();
            
            // Construire la requête de base avec les filtres de date
            $baseQuery = UserBonusPointHistory::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate]);
            
            // Filtrer par pack si spécifié
            if ($request->has('pack_id') && !empty($request->pack_id)) {
                $baseQuery->where('pack_id', $request->pack_id);
            }
            
            // Statistiques par type
            $statsByType = (clone $baseQuery)
                ->select('type', 
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(points) as total_points')
                )
                ->groupBy('type')
                ->get();
            
            // Statistiques par pack
            $statsByPack = (clone $baseQuery)
                ->whereNotNull('pack_id')
                ->select('pack_id', 
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(points) as total_points')
                )
                ->groupBy('pack_id')
                ->get();
            
            // Récupérer les noms des packs
            $packIds = $statsByPack->pluck('pack_id')->toArray();
            $packs = Pack::whereIn('id', $packIds)->get(['id', 'name']);
            
            // Ajouter les noms des packs aux statistiques
            $statsByPack = $statsByPack->map(function($stat) use ($packs) {
                $pack = $packs->firstWhere('id', $stat->pack_id);
                $stat->pack_name = $pack ? $pack->name : 'Pack inconnu';
                return $stat;
            });
            
            // Total des points attribués et convertis
            $totalPointsGained = (clone $baseQuery)
                ->where('type', 'gain')
                ->sum('points');
            $totalPointsConverted = (clone $baseQuery)
                ->where('type', 'conversion')
                ->sum('points');
            $currentBonusPoints = UserBonusPoint::where('user_id', $user->id)
                ->with('pack')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'stats_by_type' => $statsByType,
                    'stats_by_pack' => $statsByPack,
                    'total_points_gained' => $totalPointsGained,
                    'total_points_converted' => $totalPointsConverted,
                    'current_bonus_points' => $currentBonusPoints,
                    'period' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d')
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques des points bonus: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer les types d'historique de points bonus disponibles
     */
    public function getBonusPointsTypes()
    {
        try {
            $user = Auth::user();
            
            $types = UserBonusPointHistory::where('user_id', $user->id)
                ->select('type')
                ->distinct()
                ->pluck('type');
            
            return response()->json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des types de points bonus: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer un résumé des finances de l'utilisateur
     */
    public function getSummary(Request $request)
    {
        try {
            $user = Auth::user();
            $wallet = Wallet::where('user_id', $user->id)->first();
            
            if (!$wallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun portefeuille trouvé pour cet utilisateur'
                ], 404);
            }
            
            // Période par défaut: dernier mois
            $startDate = $request->date_from ? Carbon::parse($request->date_from) : Carbon::now()->subMonth();
            $endDate = $request->date_to ? Carbon::parse($request->date_to) : Carbon::now();
            
            // Récupérer les statistiques par type pour la période
            $statsByType = WalletTransaction::where('wallet_id', $wallet->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select('type', 
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(amount) as total_amount')
                )
                ->groupBy('type')
                ->get();
            
            // Récupérer le total des entrées et sorties pour la période
            $totalIn = WalletTransaction::where('wallet_id', $wallet->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('type', ['commission de parrainage', 'frais de retrait', 'frais de transfert', 'commission de retrait', 'bonus'])
                ->sum('amount');
            
            $totalOut = WalletTransaction::where('wallet_id', $wallet->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('type', 'withdrawal')
                ->sum('amount');
            
            // Récupérer les statistiques des points bonus
            $bonusPointsStats = UserBonusPointHistory::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select('type', 
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(points) as total_points')
                )
                ->groupBy('type')
                ->get();
            
            // Points bonus actuels
            $currentBonusPoints = UserBonusPoint::where('user_id', $user->id)
                ->with('pack')
                ->get();
                
            // Récupérer le nombre de demandes de retrait en attente
            $pendingWithdrawals = WalletTransaction::where('wallet_id', $wallet->id)
                ->where('type', 'withdrawal')
                ->where('status', 'pending')
                ->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'wallet_balance' => $wallet->balance,
                    'total_earned' => $wallet->total_earned,
                    'total_withdrawn' => $wallet->total_withdrawn,
                    'period_total_in' => $totalIn,
                    'period_total_out' => $totalOut,
                    'stats_by_type' => $statsByType,
                    'bonus_points_stats' => $bonusPointsStats,
                    'current_bonus_points' => $currentBonusPoints,
                    'pending_withdrawals' => $pendingWithdrawals,
                    'period' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d')
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du résumé des finances: ' . $e->getMessage()
            ], 500);
        }
    }
}
