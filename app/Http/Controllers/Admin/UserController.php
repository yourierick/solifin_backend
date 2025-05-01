<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Pack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\UserPack;
use App\Models\UserBonusPoint;
use App\Models\BonusRates;
use App\Models\Commission;

class UserController extends BaseController
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Display a listing of the users.
     */
    public function index(Request $request)
    {
        try {
            $query = User::query()
                ->select('users.*') // Sélectionner explicitement les colonnes de la table users
                ->withCount('referrals')
                ->with(['packs' => function ($query) {
                    $query->select('user_packs.id', 'user_packs.user_id', 'user_packs.pack_id');
                }]);

            //\Log::info('Nombre total d\'utilisateurs avant filtres: ' . $query->count());

            // Appliquer les filtres
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                $query->where('users.status', $status);
            }

            if ($request->filled('has_pack')) {
                $hasPack = $request->input('has_pack');
                if ($hasPack == '1') {
                    $query->has('packs');
                } elseif ($hasPack == '0') {
                    $query->doesntHave('packs');
                }
            }

            $users = $query->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur dans UserController@index: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des utilisateurs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        try {
            $user = User::with(['packs', 'referrals'])
                ->withCount('referrals')
                ->findOrFail($id);

            // Récupérer le wallet de l'utilisateur à détailler
            $userWallet = Wallet::where('user_id', $id)->first();
            $wallet = $userWallet ? [
                'balance' => number_format($userWallet->balance, 2) . ' $',
                'total_earned' => number_format($userWallet->total_earned, 2) . ' $',
                'total_withdrawn' => number_format($userWallet->total_withdrawn, 2) . ' $',
            ] : null;

            // Récupérer les transactions du wallet
            $transactions = WalletTransaction::with('wallet')->where('wallet_id', $userWallet->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'amount' => number_format($transaction->amount, 2) . ' $',
                        'type' => $transaction->type,
                        'status' => $transaction->status,
                        'metadata' => $transaction->metadata,
                        'created_at' => $transaction->created_at->format('d/m/Y H:i:s')
                    ];
                });
            
            
            $userPacks = UserPack::with(['pack', 'sponsor'])
                ->where('user_id', $id)
                ->get()
                ->map(function ($userPack) {
                    $data = $userPack->toArray();
                    if ($userPack->sponsor) {
                        $data['sponsor_info'] = [
                            'name' => $userPack->sponsor->name,
                            'email' => $userPack->sponsor->email,
                            'phone' => $userPack->sponsor->phone,
                        ];
                    }
                    
                    // Récupérer le nombre de filleuls par génération pour ce pack
                    $referralsByGeneration = [0, 0, 0, 0]; // Initialiser un tableau pour 4 générations
                    
                    // Filleuls de première génération (directs)
                    $referralsByGeneration[0] = UserPack::where('sponsor_id', $userPack->user_id)
                        ->where('pack_id', $userPack->pack_id)
                        ->where('payment_status', 'completed')
                        ->count();
                    
                    // Pour les générations suivantes, nous utilisons une requête récursive avec CTE (Common Table Expression)
                    if ($referralsByGeneration[0] > 0) {
                        $referralsData = \DB::select("
                            WITH RECURSIVE filleuls AS (
                                -- Première génération (directs)
                                SELECT up1.user_id, up1.sponsor_id, 1 as generation
                                FROM user_packs up1
                                WHERE up1.sponsor_id = ? AND up1.pack_id = ? AND up1.payment_status = 'completed'
                                
                                UNION ALL
                                
                                -- Générations suivantes (2 à 4)
                                SELECT up2.user_id, up2.sponsor_id, f.generation + 1
                                FROM user_packs up2
                                INNER JOIN filleuls f ON up2.sponsor_id = f.user_id
                                WHERE up2.pack_id = ? AND up2.payment_status = 'completed' AND f.generation < 4
                            )
                            SELECT generation, COUNT(user_id) as total_filleuls
                            FROM filleuls
                            WHERE generation > 1
                            GROUP BY generation
                            ORDER BY generation
                        ", [$userPack->user_id, $userPack->pack_id, $userPack->pack_id]);
                        
                        // Remplir le tableau avec les résultats de la requête
                        foreach ($referralsData as $referral) {
                            // Les générations commencent à 1 dans la requête SQL, mais à 0 dans notre tableau
                            $index = $referral->generation - 1;
                            if ($index < 4) {
                                $referralsByGeneration[$index] = $referral->total_filleuls;
                            }
                        }
                    }
                    
                    $data['referrals_by_generation'] = $referralsByGeneration;
                    return $data;
                });

            if ($user->picture) {
                $user->profile_picture = asset('storage/' . $user->picture);
            }
            // Récupérer tous les packs actifs de l'utilisateur
            $utilisateur = User::find($id);
            $user_packs = $utilisateur->packs()
                ->wherePivot('payment_status', 'completed')
                ->wherePivot('status', 'active')
                ->get();

            $pointsByPack = [];
            $totalPoints = 0;
            $totalValue = 0;
            $totalUsedPoints = 0;
            
            foreach ($user_packs as $pack) {
                // Récupérer ou créer les points bonus pour ce pack
                $userPoints = UserBonusPoint::getOrCreate($id, $pack->id);
                
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
                'data' => [
                    'user' => $user,
                    'wallet' => $wallet,
                    'transactions' => $transactions,
                    'packs' => $userPacks,
                    'points' => [
                        'disponibles' => $totalPoints,
                        'utilises' => $totalUsedPoints,
                        'valeur_point' => round($averagePointValue, 2),
                        'valeur_totale' => $totalValue,
                        'points_par_pack' => $pointsByPack
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur dans UserController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des détails de l\'utilisateur'
            ], 500);
        }
    }

    /**
     * Récupère la liste des filleuls d'un utilisateur
     */
    public function referrals(User $user, Request $request)
    {
        try {
            $packId = $request->input('pack_id');
            $referrals = $user->getReferrals($packId);
            
            return response()->json([
                'success' => true,
                'data' => $referrals,
                'message' => 'Liste des filleuls récupérée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans UserController@referrals: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des filleuls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit(User $user)
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
                'phone' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:255'],
                'status' => ['required', 'string', 'in:active,inactive,suspended'],
                'password' => ['nullable', 'min:8', 'confirmed'],
                'is_admin' => ['boolean'],
            ]);

            // Mettre à jour les informations de base
            $user->fill($validated);

            // Mettre à jour le mot de passe si fourni
            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            // Gérer explicitement is_admin car il peut être false
            if ($request->has('is_admin')) {
                // Empêcher la désactivation du dernier admin
                if (!$request->boolean('is_admin') && $user->is_admin && User::where('is_admin', true)->count() === 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Impossible de retirer les droits du dernier administrateur'
                    ], 422);
                }
                $user->is_admin = $request->boolean('is_admin');
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès',
                'data' => $user
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Erreur dans UserController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour de l\'utilisateur'
            ], 500);
        }
    }

    public function destroy(User $user)
    {
        try {
            DB::beginTransaction();
            
            $user->delete(); // Cette méthode appellera notre méthode delete() personnalisée

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user status between active and inactive
     */
    public function toggleStatus($userId)
    {
        try {
            $user = User::find($userId);
            
            // Empêcher la désactivation du dernier administrateur
            if ($user->is_admin && User::where('is_admin', true)->count() == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de désactiver le dernier administrateur'
                ], 422);
            }

            // Toggle le statut
            $user->status = $user->status === 'active' ? 'inactive' : 'active';
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Statut de l\'utilisateur mis à jour avec succès',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur dans UserController@toggleStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du statut'
            ], 500);
        }
    }

    public function network(User $user)
    {
        try {
            $referrals = $user->referrals()
                ->with(['packs', 'wallet'])
                ->paginate(15);

            $networkStats = [
                'direct_referrals' => $user->referrals()->count(),
                'total_network' => $user->getAllDownlines()->count(),
                'active_referrals' => $user->referrals()->where('status', 'active')->count(),
                'total_commissions' => $user->wallet->transactions()
                    ->where('type', 'credit')
                    ->sum('amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'referrals' => $referrals,
                    'networkStats' => $networkStats
                ],
                'message' => 'Réseau de l\'utilisateur récupéré avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans UserController@network: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du réseau',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function transactions(User $user)
    {
        try {
            $transactions = $user->wallet->transactions()
                ->latest()
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'message' => 'Transactions de l\'utilisateur récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans UserController@transactions: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur
     */
    public function resetPassword($id, Request $request)
    {
        try {
            $request->validate([
                'new_password' => 'required|string|min:8',
                'admin_password' => 'required|string',
            ]);

            // Vérifier le mot de passe de l'administrateur
            $admin = auth()->user();
            if (!Hash::check($request->admin_password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe administrateur incorrect'
                ], 401);
            }

            // Trouver l'utilisateur et réinitialiser son mot de passe
            $user = User::findOrFail($id);
            $user->password = Hash::make($request->new_password);
            $user->save();

            // Journaliser l'action
            Log::info("Mot de passe réinitialisé pour l'utilisateur ID: {$id} par l'administrateur ID: {$admin->id}");

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans UserController@resetPassword: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Détails d'un utilisateur
    //récupérer les filleuls d'un pack
    public function getPackReferrals(Request $request, $id)
    {
        try {
            $pack = Pack::findOrFail($id);
            
            // Pour l'admin, on doit pouvoir spécifier l'utilisateur
            $userId = $request->query('user_id');
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID utilisateur requis'
                ], 400);
            }
            
            $user = User::findOrFail($userId);
            
            $userPack = UserPack::where('user_id', $userId)
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack non trouvé pour cet utilisateur'
                ], 404);
            }

            $allGenerations = [];
            
            // Première génération (niveau 1)
            $level1Referrals = UserPack::with(['user', 'sponsor', 'pack'])
                ->where('sponsor_id', $userId)
                ->where('pack_id', $pack->id)
                ->get()
                ->map(function ($referral) use ($userId, $pack) {
                    $commissions = Commission::where('user_id', $userId)
                        ->where('source_user_id', $referral->user_id)
                        ->where('pack_id', $pack->id)
                        ->where('status', "completed")
                        ->sum('amount');
                    return [
                        'id' => $referral->user->id ?? null,
                        'name' => $referral->user->name ?? 'N/A',
                        'purchase_date' => optional($referral->purchase_date)->format('d/m/Y'),
                        'pack_status' => $referral->status ?? 'inactive',
                        'total_commission' => $commissions ?? 0,
                        'sponsor_id' => $referral->sponsor_id,
                        'referral_code' => $referral->referral_code ?? 'N/A',
                        'pack_name' => $referral->referral_pack_name ?? 'N/A',
                        'pack_price' => $referral->pack->price ?? 0,
                        'expiry_date' => optional($referral->expiry_date)->format('d/m/Y')
                    ];
                });
            $allGenerations[] = $level1Referrals;

            // Générations 2 à 4
            for ($level = 2; $level <= 4; $level++) {
                $currentGeneration = collect();
                $previousGeneration = $allGenerations[$level - 2];

                foreach ($previousGeneration as $parent) {
                    $children = UserPack::with(['user', 'sponsor', 'pack'])
                        ->where('sponsor_id', $parent['id'])
                        ->where('pack_id', $pack->id)
                        ->get()
                        ->map(function ($referral) use ($parent, $userId, $pack) {
                            //calcul du total de commission générée par ce filleul pour cet utilisateur.
                            $commissions = Commission::where('user_id', $userId)
                                ->where('source_user_id', $referral->user_id)
                                ->where('pack_id', $pack->id)
                                ->where('status', "completed")
                                ->sum('amount');
                            return [
                                'id' => $referral->user->id ?? null,
                                'name' => $referral->user->name ?? 'N/A',
                                'purchase_date' => optional($referral->purchase_date)->format('d/m/Y'),
                                'pack_status' => $referral->status ?? 'inactive',
                                'total_commission' => $commissions ?? "0 $",
                                'sponsor_id' => $referral->sponsor_id,
                                'sponsor_name' => $parent['name'] ?? 'N/A',
                                'referral_code' => $referral->referral_code ?? 'N/A',
                                'pack_name' => $referral->pack->name ?? 'N/A',
                                'pack_price' => $referral->pack->price ?? 0,
                                'expiry_date' => optional($referral->expiry_date)->format('d/m/Y')
                            ];
                        });
                    $currentGeneration = $currentGeneration->concat($children);
                }
                $allGenerations[] = $currentGeneration;
            }

            return response()->json([
                'success' => true,
                'data' => $allGenerations
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des filleuls: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des filleuls: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les statistiques détaillées d'un pack pour un utilisateur spécifié
     * 
     * @param Request $request
     * @param int $id ID du pack
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetailedPackStats(Request $request, $id)
    {
        try {
            $userpack = UserPack::with('pack')->find($id);
            $pack = $userpack->pack;
            
            // Pour l'admin, on doit pouvoir spécifier l'utilisateur
            $userId = $request->query('user_id');
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID utilisateur requis'
                ], 400);
            }
            
            $user = User::findOrFail($userId);
            
            $userPack = UserPack::where('user_id', $userId)
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack non trouvé pour cet utilisateur'
                ], 404);
            }

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
                ->where('sponsor_id', $userId)
                ->where('pack_id', $pack->id)
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
            $gen1Commissions = Commission::where('user_id', $userId)
                ->where('pack_id', $pack->id)
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
                        ->where('pack_id', $pack->id)
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
                $genCommissions = Commission::where('user_id', $userId)
                    ->where('pack_id', $pack->id)
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
                
                $amount = Commission::where('user_id', $userId)
                    ->where('pack_id', $pack->id)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->sum('amount');
                    
                $monthlyCommissions[$month->format('Y-m')] = $amount;
            }
            
            // Trouver le top filleul (celui qui a recruté le plus de personnes)
            $topReferral = null;
            $maxRecruits = 0;
            
            foreach ($firstGenReferrals as $referral) {
                $recruitCount = UserPack::where('sponsor_id', $referral->user_id)
                    ->where('pack_id', $pack->id)
                    ->count();
                    
                if ($recruitCount > $maxRecruits) {
                    $maxRecruits = $recruitCount;
                    $topReferral = [
                        'id' => $referral->user->id,
                        'name' => $referral->user->name,
                        'recruit_count' => $recruitCount
                    ];
                }
            }

            // Récupérer les derniers paiements reçus
            $latestPayments = Commission::with('source_user')
                ->where('user_id', $userId)
                ->where('pack_id', $pack->id)
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function ($commission) {
                    return [
                        'id' => $commission->id,
                        'amount' => $commission->amount,
                        'date' => $commission->created_at->format('d/m/Y'),
                        'source' => $commission->source_user->name ?? 'Inconnu',
                        'status' => $commission->status,
                        'level' => $commission->level
                    ];
                });

            // Modifier la structure des données pour les filleuls
            $latestReferrals = collect($allReferrals)
                ->sortByDesc('purchase_date')
                ->take(10)
                ->map(function ($referral) {
                    $validityMonths = $referral['purchase_date'] && $referral['expiry_date'] 
                        ? $referral['purchase_date']->diffInMonths($referral['expiry_date'])
                        : 0;
                    
                    return [
                        'id' => $referral['id'],
                        'name' => $referral['name'],
                        'generation' => $referral['generation'],
                        'pack_name' => $referral['pack_name'],
                        'purchase_date' => $referral['purchase_date'] ? $referral['purchase_date']->format('d/m/Y') : 'N/A',
                        'expiry_date' => $referral['expiry_date'] ? $referral['expiry_date']->format('d/m/Y') : 'N/A',
                        'validity_months' => $validityMonths,
                        'status' => $referral['status']
                    ];
                })
                ->values()
                ->toArray();

            // Modifier la structure pour tous les filleuls
            $allReferrals = collect($allReferrals)
                ->map(function ($referral) {
                    $validityMonths = $referral['purchase_date'] && $referral['expiry_date'] 
                        ? $referral['purchase_date']->diffInMonths($referral['expiry_date'])
                        : 0;
                    
                    return [
                        'id' => $referral['id'],
                        'name' => $referral['name'],
                        'generation' => $referral['generation'],
                        'pack_name' => $referral['pack_name'],
                        'purchase_date' => $referral['purchase_date'] ? $referral['purchase_date']->format('d/m/Y') : 'N/A',
                        'expiry_date' => $referral['expiry_date'] ? $referral['expiry_date']->format('d/m/Y') : 'N/A',
                        'validity_months' => $validityMonths,
                        'status' => $referral['status']
                    ];
                })
                ->values()
                ->toArray();

            $bonus = UserBonusPoint::where('user_id', $userId)->where("pack_id", $pack->id)->first();
           
            $bonus_disponibles = 0;
            $bonus_utilises = 0;
            if ($bonus) {
                $bonus_disponibles = $bonus->points_disponibles;
                $bonus_utilises = $bonus->points_utilises;
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'general_stats' => [
                        'total_referrals' => $totalReferralsCount,
                        'referrals_by_generation' => $referralsByGeneration,
                        'active_referrals' => $activeReferralsCount,
                        'inactive_referrals' => $inactiveReferralsCount,
                        'total_commission' => $totalCommission,
                        'failed_commission' => $failedCommission,
                        'bonus_disponibles' => $bonus_disponibles,
                        'bonus_utilises' => $bonus_utilises,
                        'best_generation' => $bestGeneration
                    ],
                    'progression' => [
                        'monthly_signups' => $monthlySignups,
                        'monthly_commissions' => $monthlyCommissions,
                        'top_referral' => $topReferral
                    ],
                    'latest_referrals' => $latestReferrals,
                    'financial_info' => [
                        'total_commission' => $totalCommission,
                        'latest_payments' => $latestPayments
                    ],
                    'all_referrals' => $allReferrals,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des statistiques détaillées: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques détaillées: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle le statut d'un pack utilisateur (active/inactive)
     *
     * @param Request $request
     * @param int $packId ID du pack utilisateur
     * @return \Illuminate\Http\JsonResponse
     */
    public function togglePackStatus(Request $request, $packId)
    {
        try {
            // Trouver le pack utilisateur
            $userPack = UserPack::findOrFail($packId);
            
            // Vérifier que le pack existe
            if (!$userPack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack utilisateur non trouvé'
                ], 404);
            }
            
            // Inverser le statut
            $newStatus = $userPack->status === 'active' ? 'inactive' : 'active';
            $userPack->status = $newStatus;
            $userPack->save();
            
            // Log l'action
            Log::info("Pack utilisateur ID {$packId} statut changé à {$newStatus} par admin ID " . $request->user()->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Statut du pack mis à jour avec succès',
                'data' => [
                    'id' => $userPack->id,
                    'status' => $userPack->status
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du changement de statut du pack: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut du pack: ' . $e->getMessage()
            ], 500);
        }
    }
}