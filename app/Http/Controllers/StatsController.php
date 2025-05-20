<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatsController extends Controller
{
    /**
     * Récupère les statistiques globales pour la page d'accueil
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHomeStats()
    {
        try {
            // Nombre d'utilisateurs actifs
            $activeUsers = User::where('status', 'active')->count();
            
            // Nombre de pays représentés (en comptant les pays distincts dans la table User)
            $countriesCount = DB::table('users')
                ->select('pays')
                ->whereNotNull('pays')
                ->distinct()
                ->get()
                ->count();

            // Taux de satisfaction basé sur les témoignages
            $testimonials = Testimonial::where('status', 'approved')->get();
            $satisfactionRate = 0;
            
            if ($testimonials->count() > 0) {
                $totalRating = $testimonials->sum('rating');
                $maxPossibleRating = $testimonials->count() * 5; // 5 étant la note maximale
                $satisfactionRate = round(($totalRating / $maxPossibleRating) * 100);
            }
            
            // Montant total des transactions
            $totalTransactions = WalletTransaction::where('type','commission de parrainage')
                ->where('status', 'completed')
                ->sum('amount');

            \Log::info($totalTransactions);
            
            return response()->json([
                'success' => true,
                'stats' => [
                    [
                        'number' => $activeUsers,
                        'label' => 'Membres Actifs',
                        'suffix' => '+',
                        'icon' => 'users'
                    ],
                    [
                        'number' => $countriesCount,
                        'label' => 'Pays Représentés',
                        'suffix' => '+',
                        'icon' => 'globe'
                    ],
                    [
                        'number' => $satisfactionRate,
                        'label' => 'Taux de Satisfaction',
                        'suffix' => '%',
                        'icon' => 'star'
                    ],
                    [
                        'number' => $totalTransactions,
                        'label' => '$ de commission gagné',
                        'suffix' => '$',
                        'icon' => 'currency-dollar'
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des statistiques',
                'stats' => [
                    [
                        'number' => 50000,
                        'label' => 'Membres Actifs',
                        'suffix' => '+',
                        'icon' => 'users'
                    ],
                    [
                        'number' => 150,
                        'label' => 'Pays Représentés',
                        'suffix' => '+',
                        'icon' => 'globe'
                    ],
                    [
                        'number' => 98,
                        'label' => 'Taux de Satisfaction',
                        'suffix' => '%',
                        'icon' => 'star'
                    ]
                ]
            ], 500);
        }
    }
}
