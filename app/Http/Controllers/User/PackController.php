<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserPack;
use App\Models\Wallet;
use App\Models\WalletSystem;
use App\Models\Pack;
use App\Models\Commission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PackController extends Controller
{
    //récupérer tous les packs achetés par l'utilisateur
    public function getUserPacks(Request $request)
    {
        try {
            $userPacks = UserPack::with(['pack', 'sponsor'])
                ->where('user_id', $request->user()->id)
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
                    return $data;
                });

            return response()->json([
                'success' => true,
                'data' => $userPacks
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des packs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des packs'
            ], 500);
        }
    }

    //renouvellement d'un pack
    public function renewPack(Request $request, Pack $pack)
    {
        try {
            // Vérifier si l'utilisateur a déjà ce pack
            $userPack = UserPack::where('user_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack non trouvé'
                ], 404);
            }

            // Logique de renouvellement à implémenter
            // Pour l'instant, on met juste à jour la date d'expiration
            $userPack->expiry_date = now()->addMonths($request->duration_months ?? 1);
            $userPack->status = 'active';
            $userPack->save();

            return response()->json([
                'success' => true,
                'message' => 'Pack renouvelé avec succès',
                'data' => $userPack
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du renouvellement du pack'
            ], 500);
        }
    }

    //Achat d'un nouveau pack
    public function purchase_a_new_pack(Request $request)
    {
        try {
            $request->validate([
                'pack_id' => 'required|exists:packs,id',
                'payment_method' => 'required|string',
                'sponsor_code' => 'nullable|exists:user_packs,referral_code',
                'months' => 'required|integer|min:1',
                'amount' => 'required|numeric|min:0'
            ]);

            $user = $request->user();
            $pack = Pack::findOrFail($request->pack_id);
            
            // Vérifier si le montant est correct
            $expectedAmount = $pack->price * $request->months;
            if ($expectedAmount !== floatval($request->amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant calculé ne correspond pas au prix du pack'
                ], 400);
            }

            DB::beginTransaction();
            
            try {
                if ($request->payment_method === 'wallet') {
                    // Vérifier le solde du wallet
                    $userWallet = Wallet::where('user_id', $user->id)->first();
                    
                    if (!$userWallet || $userWallet->balance < $request->amount) {
                        throw new \Exception('Solde insuffisant dans votre wallet');
                    }

                    // Vérifier si l'utilisateur a déjà ce pack
                    $existingUserPack = UserPack::where('user_id', $user->id)
                        ->where('pack_id', $pack->id)
                        ->first();

                    if ($existingUserPack) {
                        // Prolonger la période de validité
                        $newExpiryDate = $existingUserPack->expiry_date > now() 
                            ? Carbon::parse($existingUserPack->expiry_date) 
                            : now();
                        $existingUserPack->expiry_date = $newExpiryDate->addMonths($request->months);
                        $existingUserPack->save();
                    } else {

                        //Si un code parrain est fourni, lier l'utilisateur au parrain
                        $sponsorPack = UserPack::where('referral_code', $request->sponsor_code)->first();

                        // Générer un code de parrainage unique
                        $referralLetter = substr($pack->name, 0, 1);
                        $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $referralCode = 'SPR' . $referralLetter . $referralNumber;

                        // Vérifier que le code est unique
                        while (UserPack::where('referral_code', $referralCode)->exists()) {
                            $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                            $referralCode = 'SPR' . $referralLetter . $referralNumber;
                        }

                        // Attacher le pack à l'utilisateur
                        $user->packs()->attach($pack->id, [
                            'status' => 'active',
                            'purchase_date' => now(),
                            'expiry_date' => now()->addMonths($validated['duration_months']),
                            'is_admin_pack' => false,
                            'payment_status' => 'completed',
                            'referral_prefix' => 'SPR',
                            'referral_pack_name' => $pack->name,
                            'referral_letter' => $referralLetter,
                            'referral_number' => $referralNumber,
                            'referral_code' => $referralCode,
                            'link_referral' => $referralLink,
                            'sponsor_id' => $sponsorPack->user_id,
                        ]);
                    }

                    // Déduire le montant du wallet de l'utilisateur
                    $userWallet->balance -= $request->amount;
                    $userWallet->withdrawFunds($request->amount, "transfer", "completed", ["pack_id"=>$pack->id, "pack_name"=>$pack->name, 
                    "duration"=>$request->months]);

                    // Ajouter le montant au wallet system
                    $walletsystem = WalletSystem::first();
                    if (!$systemWallet) {
                        $systemWallet = WalletSystem::create(['balance' => 0]);
                    }
                    $walletsystem->addFunds($request->amount, "sales", "completed", ["user"=>$user->name, "pack_id"=>$pack->id, "pack_name"=>$pack->name, 
                        "duration"=>$request->months]);

                    // Récupérer l'URL du frontend depuis le fichier .env
                    $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

                    // Créer le lien de parrainage en utilisant l'URL du frontend
                    $referralLink = $frontendUrl . "/register?referral_code=" . $referralCode;
                } else {
                    // Pour les autres méthodes de paiement (à implémenter)
                    return response()->json([
                        'success' => false,
                        'message' => 'Cette méthode de paiement n\'est pas encore disponible'
                    ], 400);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Pack acheté avec succès',
                    'referral_link' => $referralLink
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    //téléchargement du pack (les formations et autres fichiers associés au pack)
    public function downloadPack(Pack $pack, Request $request)
    {
        try {
            
            // Vérifier si l'utilisateur a accès à ce pack
            $userPack = UserPack::where('user_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                \Log::warning('Accès refusé au pack ' . $pack->id . ' pour l\'utilisateur ' . $request->user()->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à ce pack'
                ], 403);
            }
            
            // Vérifier si le pack a un fichier associé
            if (!$pack->formations) {
                \Log::warning('Aucun fichier associé au pack ' . $pack->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier du pack n\'est pas disponible'
                ], 404);
            }

            if (!Storage::disk('public')->exists($pack->formations)) {
                \Log::warning('Fichier non trouvé: ' . $pack->formations);
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier du pack n\'est pas disponible'
                ], 404);
            }

            return Storage::disk('public')->download($pack->formations, "pack-{$pack->id}.zip");

        } catch (\Exception $e) {
            \Log::error('Erreur lors du téléchargement du pack: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement du pack'
            ], 500);
        }
    }

    //récupérer les filleuls d'un pack
    public function getPackReferrals(Request $request, Pack $pack)
    {
        try {
            $userPack = UserPack::where('user_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack non trouvé'
                ], 404);
            }

            $allGenerations = [];
            
            // Première génération (niveau 1)
            $level1Referrals = UserPack::with(['user', 'sponsor', 'pack'])
                ->where('sponsor_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->get()
                ->map(function ($referral) use ($request, $pack) {
                    $commissions = Commission::where('user_id', $request->user()->id)->where('source_user_id', $referral->user_id)->where('pack_id', $pack->id)->where('status', "completed")->sum('amount');
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
                        ->map(function ($referral) use ($parent, $request, $pack) {
                            //calcul du total de commission générée par ce filleul pour cet utilisateur.
                            $commissions = Commission::where('user_id', $request->user()->id)->where('source_user_id', $referral->user_id)->where('pack_id', $pack->id)->where('status', "completed")->sum('amount');
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
            \Log::error('Erreur lors de la récupération des filleuls: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des filleuls'
            ], 500);
        }
    }

    /**
     * Récupère les statistiques détaillées d'un pack pour l'utilisateur connecté
     * 
     * @param Request $request
     * @param Pack $pack
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetailedPackStats(Request $request, Pack $pack)
    {
        try {
            $userPack = UserPack::where('user_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack non trouvé'
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
                ->where('sponsor_id', $request->user()->id)
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
            $gen1Commissions = Commission::where('user_id', $request->user()->id)
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
                $genCommissions = Commission::where('user_id', $request->user()->id)
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
                
                $amount = Commission::where('user_id', $request->user()->id)
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
                ->where('user_id', $request->user()->id)
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
                    'all_referrals' => $allReferrals
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des statistiques détaillées: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques détaillées'
            ], 500);
        }
    }
} 