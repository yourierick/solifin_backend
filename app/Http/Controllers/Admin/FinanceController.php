<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WalletSystem;
use App\Models\WalletSystemTransaction;
use App\Models\UserBonusPointHistory;
use App\Models\Pack;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    /**
     * Récupérer toutes les transactions du système de portefeuille
     */
    public function index(Request $request)
    {
        try {
            $query = WalletSystemTransaction::query()
                ->with('walletSystem')
                ->orderBy('created_at', 'desc');

            // Filtrer par type si spécifié
            if ($request->has('type') && !empty($request->type)) {
                $query->where('type', $request->type);
            }

            // Filtrer par date de début si spécifiée
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            // Filtrer par date de fin si spécifiée
            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Filtrer par statut si spécifié
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            $transactions = $query->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques des transactions regroupées par type
     */
    public function getStatsByType(Request $request)
    {
        try {
            $query = WalletSystemTransaction::query();

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
     * Récupérer les statistiques des transactions par période (jour, semaine, mois)
     */
    public function getStatsByPeriod(Request $request)
    {
        try {
            $period = $request->period ?? 'month';
            $type = $request->type ?? null;
            
            $query = WalletSystemTransaction::query();
            
            // Filtrer par type si spécifié
            if (!empty($type)) {
                $query->where('type', $type);
            }

            // Filtrer par date de début si spécifiée
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            // Filtrer par date de fin si spécifiée
            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Grouper par période
            switch ($period) {
                case 'day':
                    $stats = $query->select(
                        DB::raw('DATE(created_at) as period'),
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(amount) as total_amount')
                    )
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
                    break;
                
                case 'week':
                    $stats = $query->select(
                        DB::raw('YEARWEEK(created_at) as period'),
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(amount) as total_amount'),
                        DB::raw('MIN(DATE(created_at)) as start_date'),
                        DB::raw('MAX(DATE(created_at)) as end_date')
                    )
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
                    break;
                
                case 'month':
                default:
                    $stats = $query->select(
                        DB::raw('DATE_FORMAT(created_at, "%Y-%m") as period'),
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(amount) as total_amount')
                    )
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'period_type' => $period
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques par période: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les types de transactions disponibles
     */
    public function getTransactionTypes()
    {
        try {
            $types = WalletSystemTransaction::select('type')
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
     * Récupérer le solde actuel du système
     */
    public function getSystemBalance()
    {
        try {
            $walletSystem = WalletSystem::first();
            
            if (!$walletSystem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun système de portefeuille trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => $walletSystem->balance,
                    'total_in' => $walletSystem->total_in,
                    'total_out' => $walletSystem->total_out
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du solde du système: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer le résumé des finances
     */
    public function getSummary(Request $request)
    {
        try {
            // Période par défaut: dernier mois
            $startDate = $request->date_from ? Carbon::parse($request->date_from) : Carbon::now()->subMonth();
            $endDate = $request->date_to ? Carbon::parse($request->date_to) : Carbon::now();

            // Récupérer le solde actuel
            $walletSystem = WalletSystem::first();
            
            if (!$walletSystem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun système de portefeuille trouvé'
                ], 404);
            }

            // Récupérer les statistiques par type pour la période
            $statsByType = WalletSystemTransaction::whereBetween('created_at', [$startDate, $endDate])
                ->select('type', 
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(amount) as total_amount')
                )
                ->groupBy('type')
                ->get();

            // Récupérer le total des entrées et sorties pour la période
            $totalIn = WalletSystemTransaction::whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('type', ['sales', 'commission de parrainage', 'frais de retrait', 'frais de transfert', 'commission de retrait', 'bonus'])
                ->sum('amount');

            $totalOut = WalletSystemTransaction::whereBetween('created_at', [$startDate, $endDate])
                ->where('type', 'withdrawal')
                ->sum('amount');

            // Récupérer le nombre de transactions par jour pour la période
            $transactionsByDay = WalletSystemTransaction::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(amount) as total_amount')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'current_balance' => $walletSystem->balance,
                    'total_in_all_time' => $walletSystem->total_in,
                    'total_out_all_time' => $walletSystem->total_out,
                    'period_total_in' => $totalIn,
                    'period_total_out' => $totalOut,
                    'stats_by_type' => $statsByType,
                    'transactions_by_day' => $transactionsByDay,
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

    /**
     * Récupérer l'historique des points bonus
     */
    public function getBonusPointsHistory(Request $request)
    {
        try {
            $query = UserBonusPointHistory::with(['user', 'pack'])
                ->orderBy('created_at', 'desc');

            // Filtrer par utilisateur si spécifié
            if ($request->has('user_id') && !empty($request->user_id)) {
                $query->where('user_id', $request->user_id);
            }

            // Filtrer par pack si spécifié
            if ($request->has('pack_id') && !empty($request->pack_id)) {
                $query->where('pack_id', $request->pack_id);
            }

            // Filtrer par type si spécifié
            if ($request->has('type') && !empty($request->type)) {
                $query->where('type', $request->type);
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
                      ->orWhere('id', 'like', $searchTerm)
                      ->orWhereHas('user', function($userQuery) use ($searchTerm) {
                          $userQuery->where('name', 'like', $searchTerm)
                                   ->orWhere('email', 'like', $searchTerm);
                      });
                });
            }

            $history = $query->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique des points bonus: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques des points bonus
     */
    public function getBonusPointsStats(Request $request)
    {
        try {
            // Définir la période de filtrage
            $startDate = $request->has('date_from') && !empty($request->date_from) 
                ? Carbon::parse($request->date_from)->startOfDay() 
                : Carbon::now()->subMonths(1)->startOfDay();
                
            $endDate = $request->has('date_to') && !empty($request->date_to) 
                ? Carbon::parse($request->date_to)->endOfDay() 
                : Carbon::now()->endOfDay();
            
            // Construire la requête de base avec les filtres de date
            $baseQuery = UserBonusPointHistory::whereBetween('created_at', [$startDate, $endDate]);
            
            // Filtrer par utilisateur si spécifié
            if ($request->has('user_id') && !empty($request->user_id)) {
                $baseQuery->where('user_id', $request->user_id);
            }
            
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
            $packQuery = (clone $baseQuery)->whereNotNull('pack_id');
            $statsByPack = $packQuery
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

            // Top utilisateurs avec le plus de points
            $topUsersQuery = DB::table('user_bonus_points');
            
            // Filtrer par utilisateur si spécifié
            if ($request->has('user_id') && !empty($request->user_id)) {
                $topUsersQuery->where('user_id', $request->user_id);
            }
            
            // Filtrer par pack si spécifié
            if ($request->has('pack_id') && !empty($request->pack_id)) {
                $userIds = (clone $baseQuery)->where('pack_id', $request->pack_id)->pluck('user_id')->unique();
                if ($userIds->count() > 0) {
                    $topUsersQuery->whereIn('user_id', $userIds);
                }
            }
            
            $topUsers = $topUsersQuery
                ->select('user_id', DB::raw('SUM(points_disponibles) as total_points'))
                ->groupBy('user_id')
                ->orderBy('total_points', 'desc')
                ->limit(10)
                ->get();

            // Récupérer les informations des utilisateurs
            $userIds = $topUsers->pluck('user_id')->toArray();
            $users = User::whereIn('id', $userIds)->get(['id', 'name', 'email']);
            
            // Ajouter les noms des utilisateurs aux statistiques
            $topUsers = $topUsers->map(function($user) use ($users) {
                $userInfo = $users->firstWhere('id', $user->user_id);
                $user->user_name = $userInfo ? $userInfo->name : 'Utilisateur inconnu';
                $user->user_email = $userInfo ? $userInfo->email : '';
                return $user;
            });

            // Total des points attribués et convertis
            $totalPointsGained = (clone $baseQuery)
                ->where('type', 'gain')
                ->sum('points');

            $totalPointsConverted = (clone $baseQuery)
                ->where('type', 'conversion')
                ->sum('points');

            return response()->json([
                'success' => true,
                'data' => [
                    'stats_by_type' => $statsByType,
                    'stats_by_pack' => $statsByPack,
                    'top_users' => $topUsers,
                    'total_points_gained' => $totalPointsGained,
                    'total_points_converted' => $totalPointsConverted,
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
            $types = UserBonusPointHistory::select('type')
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
}
