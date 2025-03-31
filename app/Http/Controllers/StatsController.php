<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Pack;
use App\Models\UserPack;
use App\Models\Commission;

class StatsController extends Controller
{
    public function getGlobalStats(Request $request)
    {
        try {
            $user = $request->user();

            // Récupérer tous les filleuls (toutes générations confondues)
            $allReferrals = [];
            $totalReferralsCount = 0;
            $referralsByGeneration = [0, 0, 0, 0]; // Compteur pour chaque génération
            $commissionsByGeneration = [0, 0, 0, 0]; // Commissions pour chaque génération
            $activeReferralsCount = 0;
            $inactiveReferralsCount = 0;
            $totalCommission = 0;
            $failedCommission = 0;

            // Récupérer les filleuls de première génération
            $firstGenReferrals = UserPack::with(['user', 'pack'])
                ->where('sponsor_id', $user->id)
                ->get();

            $referralsByGeneration[0] = $firstGenReferrals->count();
            $totalReferralsCount += $referralsByGeneration[0];
            
            // Compter les actifs/inactifs de première génération
            foreach ($firstGenReferrals as $referral) {
                if ($referral->status === 'active') {
                    $activeReferralsCount++;
                } else {
                    $inactiveReferralsCount++;
                }
                
                // Ajouter à la liste complète des filleuls
                $allReferrals[] = [
                    'id' => $referral->user->id,
                    'name' => $referral->user->name,
                    'generation' => 1,
                    'purchase_date' => $referral->purchase_date,
                    'expiry_date' => $referral->expiry_date,
                    'status' => $referral->status,
                    'pack_name' => $referral->pack->name
                ];
            }

            // Récupérer les commissions de première génération
            $gen1Commissions = Commission::where('user_id', $user->id)
                ->where('level', 1)
                ->get();
                
            $commissionsByGeneration[0] = $gen1Commissions->where('status', 'completed')->sum('amount');
            $totalCommission += $commissionsByGeneration[0];
            $failedCommission += $gen1Commissions->where('status', 'failed')->sum('amount');

            // Récupérer les filleuls et commissions des générations 2 à 4
            $currentGenReferrals = $firstGenReferrals->pluck('user_id')->toArray();
            
            for ($generation = 2; $generation <= 4; $generation++) {
                $nextGenReferrals = [];
                
                foreach ($currentGenReferrals as $sponsorId) {
                    $referrals = UserPack::with(['user', 'pack'])
                        ->where('sponsor_id', $sponsorId)
                        ->get();
                        
                    foreach ($referrals as $referral) {
                        $nextGenReferrals[] = $referral->user_id;
                        
                        // Compter par statut
                        if ($referral->status === 'active') {
                            $activeReferralsCount++;
                        } else {
                            $inactiveReferralsCount++;
                        }
                        
                        // Ajouter à la liste complète des filleuls
                        $allReferrals[] = [
                            'id' => $referral->user->id,
                            'name' => $referral->user->name,
                            'generation' => $generation,
                            'purchase_date' => $referral->purchase_date,
                            'expiry_date' => $referral->expiry_date,
                            'status' => $referral->status,
                            'pack_name' => $referral->pack->name
                        ];
                    }
                    
                    $referralsByGeneration[$generation-1] += $referrals->count();
                    $totalReferralsCount += $referrals->count();
                }
                
                // Récupérer les commissions pour cette génération
                $genCommissions = Commission::where('user_id', $user->id)
                    ->where('level', $generation)
                    ->get();
                    
                $commissionsByGeneration[$generation-1] = $genCommissions->where('status', 'completed')->sum('amount');
                $totalCommission += $commissionsByGeneration[$generation-1];
                $failedCommission += $genCommissions->where('status', 'failed')->sum('amount');
                
                $currentGenReferrals = $nextGenReferrals;
            }

            // Déterminer la meilleure génération (celle qui a rapporté le plus)
            $bestGeneration = array_search(max($commissionsByGeneration), $commissionsByGeneration) + 1;

            // Récupérer les données pour les graphiques d'évolution
            $sixMonthsAgo = now()->subMonths(6);
            
            // Inscriptions mensuelles
            $monthlySignups = [];
            for ($i = 0; $i < 6; $i++) {
                $month = now()->subMonths($i);
                $count = collect($allReferrals)
                    ->filter(function ($referral) use ($month) {
                        return $referral['purchase_date'] && 
                               date('Y-m', strtotime($referral['purchase_date'])) === $month->format('Y-m');
                    })
                    ->count();
                    
                $monthlySignups[$month->format('Y-m')] = $count;
            }
            
            // Commissions mensuelles
            $monthlyCommissions = [];
            for ($i = 0; $i < 6; $i++) {
                $month = now()->subMonths($i);
                $startOfMonth = $month->copy()->startOfMonth();
                $endOfMonth = $month->copy()->endOfMonth();
                
                $amount = Commission::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->sum('amount');
                    
                $monthlyCommissions[$month->format('Y-m')] = $amount;
            }

            // Récupérer les derniers paiements reçus
            $latestPayments = Commission::with(['source_user', 'pack'])
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function ($commission) {
                    return [
                        'id' => $commission->id,
                        'amount' => $commission->amount,
                        'date' => $commission->created_at->format('d/m/Y'),
                        'source' => $commission->source_user ? $commission->source_user->name : 'Inconnu',
                        'level' => $commission->level
                    ];
                });

            // Modifier la structure des données pour les filleuls
            $latestReferrals = collect($allReferrals)
                ->sortByDesc('purchase_date')
                ->take(10)
                ->map(function ($referral) {
                    return [
                        'id' => $referral['id'],
                        'name' => $referral['name'],
                        'pack_name' => $referral['pack_name'],
                        'purchase_date' => $referral['purchase_date'] ? $referral['purchase_date']->format('d/m/Y') : 'N/A',
                        'expiry_date' => $referral['expiry_date'] ? $referral['expiry_date']->format('d/m/Y') : 'N/A',
                        'generation' => $referral['generation'],
                        'status' => $referral['status']
                    ];
                })
                ->values()
                ->toArray();

            // Statistiques par pack
            $packsPerformance = Pack::all()->map(function ($pack) use ($user) {
                // Compter tous les filleuls de la 1ère à la 4ème génération
                $totalReferrals = 0;
                $currentGenReferrals = [[$user->id]];
                
                for ($generation = 1; $generation <= 4; $generation++) {
                    $nextGenReferrals = [];
                    foreach ($currentGenReferrals[$generation - 1] as $sponsorId) {
                        $referrals = UserPack::where('sponsor_id', $sponsorId)
                            ->where('pack_id', $pack->id)
                            ->get();
                        $totalReferrals += $referrals->count();
                        $nextGenReferrals = array_merge($nextGenReferrals, $referrals->pluck('user_id')->toArray());
                    }
                    $currentGenReferrals[] = $nextGenReferrals;
                }

                // Calculer les performances mensuelles (première génération uniquement)
                $currentMonth = now()->format('Y-m');
                $firstGenMonthlyCount = UserPack::where('sponsor_id', $user->id)
                    ->where('pack_id', $pack->id)
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->count();

                // Déterminer le nombre d'étoiles et la couleur
                $stars = 0;
                $color = 'error'; // rouge par défaut

                if ($firstGenMonthlyCount >= 20) {
                    $stars = 5;
                    $color = 'success';
                } elseif ($firstGenMonthlyCount >= 16) {
                    $stars = 4;
                    $color = 'success';
                } elseif ($firstGenMonthlyCount >= 8) {
                    $stars = 3;
                    $color = 'primary';
                } elseif ($firstGenMonthlyCount >= 5) {
                    $stars = 2;
                    $color = 'primary';
                } elseif ($firstGenMonthlyCount >= 1) {
                    $stars = 1;
                    $color = 'warning';
                }

                $totalCommissions = Commission::where('user_id', $user->id)
                    ->where('pack_id', $pack->id)
                    ->where('status', 'completed')
                    ->sum('amount');

                return [
                    'id' => $pack->id,
                    'name' => $pack->name,
                    'total_referrals' => $totalReferrals,
                    'total_commissions' => $totalCommissions,
                    'performance' => [
                        'stars' => $stars,
                        'color' => $color,
                        'monthly_count' => $firstGenMonthlyCount,
                        'month' => $currentMonth
                    ]
                ];
            });

            // Distribution des filleuls par pack
            $referralsByPack = Pack::all()->map(function ($pack) use ($allReferrals) {
                return [
                    'pack_name' => $pack->name,
                    'count' => collect($allReferrals)->where('pack_name', $pack->name)->count()
                ];
            });

            // Distribution des commissions par pack
            $commissionsByPack = Pack::all()->map(function ($pack) use ($user) {
                $amount = Commission::where('user_id', $user->id)
                    ->where('pack_id', $pack->id)
                    ->where('status', 'completed')
                    ->sum('amount');

                return [
                    'pack_name' => $pack->name,
                    'amount' => $amount
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'general_stats' => [
                        'wallet' => $user->wallet,
                        'total_referrals' => $totalReferralsCount,
                        'referrals_by_generation' => $referralsByGeneration,
                        'active_referrals' => $activeReferralsCount,
                        'inactive_referrals' => $inactiveReferralsCount,
                        'total_commission' => $totalCommission,
                        'failed_commission' => $failedCommission,
                        'best_generation' => $bestGeneration
                    ],
                    'progression' => [
                        'monthly_signups' => $monthlySignups,
                        'monthly_commissions' => $monthlyCommissions
                    ],
                    'packs_performance' => $packsPerformance,
                    'latest_referrals' => $latestReferrals,
                    'financial_info' => [
                        'total_commission' => $totalCommission,
                        'latest_payments' => $latestPayments
                    ],
                    'visualizations' => [
                        'referrals_by_pack' => $referralsByPack,
                        'commissions_by_pack' => $commissionsByPack
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des statistiques globales: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques globales'
            ], 500);
        }
    }
}
