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
use Carbon\Carbon;
use App\Models\TransactionFee;
use App\Models\ExchangeRates;
use App\Services\CommissionService;

class PackController extends Controller
{
    //pour la distribution des commissions
    private function processCommissions(UserPack $user_pack, $duration_months)
    {
        $commissionService = new CommissionService();
        $commissionService->distributeCommissions($user_pack, $duration_months);
    }

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
        \Log::info(['Renewing pack: ' . $pack->name, $request->all()]);
        try {
            $validated = $request->validate([
                'payment_method' => 'required|string',
                'payment_details'=> ['requiredif:payment_method,credit-card|mobile-money', 'array'],
                'payment_type' => 'required|string',
                'duration_months' => 'required|integer|min:1',
                'amount' => 'required|numeric|min:0',
                'currency' => 'required|string',
                'fees' => 'required|numeric|min:0',
            ]);

            $paymentMethod = $validated['payment_method']; // Méthode spécifique (visa, m-pesa, etc.)
            $paymentType = $validated['payment_type']; // Type général (credit-card, mobile-money, etc.)
            $paymentAmount = $validated['amount']; // Montant sans les frais
            $paymentCurrency = $validated['currency'] ?? 'USD';

            $transactionFeeModel = TransactionFee::where('payment_method', $paymentMethod)
                ->where('is_active', true);
            
            $transactionFee = $transactionFeeModel->first();
            
            // Calculer les frais de transaction
            $transactionFees = 0;
            if ($transactionFee) {
                $transactionFees = $transactionFee->calculateTransferFee((float) $paymentAmount, $paymentCurrency);
                //\Log::info('Frais de transaction recalculés: ' . $transactionFees . ' pour la méthode ' . $paymentMethod);
            } else {
                //\Log::warning('Aucun frais de transaction trouvé pour la méthode ' . $paymentMethod);
            }
            
            // Montant total incluant les frais
            $totalAmount = $paymentAmount + $transactionFees;
            
            // Si la devise n'est pas en USD, convertir le montant en USD (devise de base)
            $amountInUSD = $totalAmount;
            if ($paymentCurrency !== 'USD') {
                try {
                    // Appel à un service de conversion de devise
                    $amountInUSD = $this->convertToUSD($totalAmount, $paymentCurrency);
                    $amountInUSD = round($amountInUSD, 0);
                } catch (\Exception $e) {
                    \Log::error('Erreur lors de la conversion de devise: ' . $e->getMessage());
                    // Fallback: utiliser un taux de conversion fixe ou une estimation
                    $amountInUSD = $this->estimateUSDAmount($totalAmount, $paymentCurrency);
                }
            }
            
            // Soustraire les frais de transaction s'ils sont inclus dans le montant
            $feesInUSD = $this->convertToUSD($transactionFees, $paymentCurrency);
            $netAmountInUSD = round($amountInUSD - $feesInUSD, 0);
            
            // Vérifier que le montant net est suffisant pour couvrir le coût du pack
            $packCost = $pack->price * $validated['duration_months'];
            if ($netAmountInUSD < $packCost) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant payé est insuffisant pour couvrir le coût du pack'
                ], 400);
            }

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
            
            $user = $userPack->user;
            DB::beginTransaction();

            if ($validated['payment_method'] === 'solifin-wallet') {
                $userWallet = $userPack->user->wallet;
                
                // Vérifier si le solde est suffisant
                if ($userWallet->balance < $amountInUSD) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solde insuffisant dans votre wallet'
                    ], 400);
                }

                // Retirer les fonds du wallet
                $userWallet->withdrawFunds($amountInUSD, "transfer", "completed", [
                    "pack_id" => $pack->id, 
                    "pack_name" => $pack->name, 
                    "duration" => $validated['duration_months'], 
                    "payment_method" => $validated['payment_method'],
                    "payment_details" => $validated['payment_details'] ?? [],
                    "currency" => $validated['currency'],
                    "original_amount" => $validated['amount'],
                    "payment_type" => $validated['payment_type'],
                    "fees" => $feesInUSD
                ]);
            } else {
                //implémenter le paiement API
            }

            // Ajouter le montant au wallet system (sans les frais)
            $walletsystem = WalletSystem::first();
            if (!$walletsystem) {
                $walletsystem = WalletSystem::create(['balance' => 0]);
            }
            
            $walletsystem->addFunds($validated['payment_method'] !== "solifin-wallet" ? $netAmountInUSD : $amountInUSD, "sales", "completed", [
                "user" => $user->name, 
                "pack_id" => $pack->id, 
                "pack_name" => $pack->name, 
                "duration" => $validated['duration_months'], 
                "payment_method" => $validated['payment_method'], 
                "payment_details" => $validated['payment_details'] ?? [],
                "currency" => $validated['currency'],
                "original_amount" => $validated['amount'],
                "fees" => $feesInUSD
            ]);

            // on met à jour la date d'expiration
            $userPack->expiry_date = now()->addMonths($validated['duration_months']);
            $userPack->status = 'active';
            $userPack->save();

            // Distribuer les commissions
            \Log::info('Distributing commissions for user pack: ' . $validated['duration_months']);
            $this->processCommissions($userPack, $validated['duration_months']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pack renouvelé avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Erreur lors du renouvellement du pack: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du renouvellement du pack: ' . $e->getMessage()
            ], 500);
        }
    }

    //Achat d'un nouveau pack
    public function purchase_a_new_pack(Request $request)
    {
        try {
            $validated = $request->validate([
                'packId' => 'required|exists:packs,id',
                'payment_method' => 'required|string',
                'payment_type' => 'required|string',
                'payment_details'=> ['requiredif:payment_type, credit-card|mobile-money', 'array'],
                'phoneNumber' => 'requiredif:payment_type, mobile-money|string',
                'referralCode' => 'nullable|exists:user_packs,referral_code', //code du sponsor
                'months' => 'required|integer|min:1',
                'amount' => 'required|numeric|min:0',
                'fees' => 'required|numeric|min:0',
                'currency' => 'required|string',
            ]);

            $user = $request->user();
            $pack = Pack::findOrFail($request->packId);

            $paymentMethod = $validated['payment_method']; // Méthode spécifique (visa, m-pesa, etc.)
            $paymentType = $validated['payment_type']; // Type général (credit-card, mobile-money, etc.)
            $paymentAmount = $validated['amount']; // Montant sans les frais
            $paymentCurrency = $validated['currency'] ?? 'USD';

            $transactionFeeModel = TransactionFee::where('payment_method', $paymentMethod)
                                                           ->where('is_active', true);
            
            $transactionFee = $transactionFeeModel->first();
            
            // Calculer les frais de transaction
            $transactionFees = 0;
            if ($transactionFee) {
                $transactionFees = $transactionFee->calculateTransferFee((float) $paymentAmount, $paymentCurrency);
                //\Log::info('Frais de transaction recalculés: ' . $transactionFees . ' pour la méthode ' . $paymentMethod);
            } else {
                //\Log::warning('Aucun frais de transaction trouvé pour la méthode ' . $paymentMethod);
            }
            
            // Montant total incluant les frais
            $totalAmount = $paymentAmount + $transactionFees;
            
            // Si la devise n'est pas en USD, convertir le montant en USD (devise de base)
            $amountInUSD = $totalAmount;
            if ($paymentCurrency !== 'USD') {
                try {
                    // Appel à un service de conversion de devise
                    $amountInUSD = $this->convertToUSD($totalAmount, $paymentCurrency);
                    $amountInUSD = round($amountInUSD, 0);
                } catch (\Exception $e) {
                    \Log::error('Erreur lors de la conversion de devise: ' . $e->getMessage());
                    // Fallback: utiliser un taux de conversion fixe ou une estimation
                    $amountInUSD = $this->estimateUSDAmount($totalAmount, $paymentCurrency);
                }
            }
            
            // Soustraire les frais de transaction s'ils sont inclus dans le montant
            $feesInUSD = $this->convertToUSD($transactionFees, $paymentCurrency);
            $netAmountInUSD = round($amountInUSD - $feesInUSD, 0);
            
            // Vérifier que le montant net est suffisant pour couvrir le coût du pack
            $packCost = $pack->price * $validated['months'];
            if ($netAmountInUSD < $packCost) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant payé est insuffisant pour couvrir le coût du pack'
                ], 400);
            }

            DB::beginTransaction();
            
            try {
                if ($request->payment_method === 'solifin-wallet') {
                    // Vérifier le solde du wallet
                    $userWallet = Wallet::where('user_id', $user->id)->first();
                    
                    if (!$userWallet || $userWallet->balance < $amountInUSD) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Solde insuffisant dans votre wallet'
                        ], 400);
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
                        $sponsorPack = UserPack::where('referral_code', $request->referralCode)->first();

                        // Générer un code de parrainage unique
                        $referralLetter = substr($pack->name, 0, 1);
                        $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $referralCode = 'SPR' . $referralLetter . $referralNumber;

                        // Vérifier que le code est unique
                        while (UserPack::where('referral_code', $referralCode)->exists()) {
                            $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                            $referralCode = 'SPR' . $referralLetter . $referralNumber;
                        }

                        // Récupérer l'URL du frontend depuis le fichier .env
                        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

                        // Créer le lien de parrainage en utilisant l'URL du frontend
                        $referralLink = $frontendUrl . "/register?referral_code=" . $referralCode;

                        // Attacher le pack à l'utilisateur
                        $user->packs()->attach($pack->id, [
                            'status' => 'active',
                            'purchase_date' => now(),
                            'expiry_date' => now()->addMonths($validated['months']),
                            'is_admin_pack' => false,
                            'payment_status' => 'completed',
                            'referral_prefix' => 'SPR',
                            'referral_pack_name' => $pack->name,
                            'referral_letter' => $referralLetter,
                            'referral_number' => $referralNumber,
                            'referral_code' => $referralCode,
                            'link_referral' => $referralLink,
                            'sponsor_id' => $sponsorPack->user_id ?? null,
                        ]);
                        
                        // Récupérer l'instance UserPack créée
                        $userpack = UserPack::where('user_id', $user->id)
                                          ->where('pack_id', $pack->id)
                                          ->where('referral_code', $referralCode)
                                          ->first();
                    }

                    // Déduire le montant du wallet de l'utilisateur
                    $userWallet->withdrawFunds($amountInUSD, "transfer", "completed", ["pack_id"=>$pack->id, "pack_name"=>$pack->name, 
                    "duration"=>$request->months, "payment_method"=>$request->payment_method, "payment_type"=>$request->payment_type, 
                    "payment_details"=>$request->payment_details, "referral_code"=>$request->referralCode, "currency"=>$request->currency
                    ]);

                    // Ajouter le montant au wallet system
                    $walletsystem = WalletSystem::first();
                    if (!$walletsystem) {
                        $walletsystem = WalletSystem::create(['balance' => 0]);
                    }
                    $walletsystem->addFunds($amountInUSD, "sales", "completed", [
                        "user" => $validated["name"], 
                        "pack_id" => $pack->id, 
                        "payment_details" => $validated['payment_details'],
                        "payment_method" => $paymentMethod,
                        "payment_type" => $paymentType,
                        "pack_name" => $pack->name, 
                        "sponsor_code" => $validated['sponsor_code'], 
                        "duration" => $validated['duration_months'],
                        "original_amount" => $paymentAmount,
                        "original_currency" => $paymentCurrency,
                        "transaction_fees" => $validated['fees'],
                        "converted_amount" => $netAmountInUSD,
                    ]);

                } else {
                    // Vérifier si l'utilisateur a déjà ce pack
                    $existingUserPack = UserPack::where('user_id', $user->id)
                        ->where('pack_id', $pack->id)
                        ->first();

                    if ($existingUserPack) {
                        //Implémenter le paiement API


                        // Prolonger la période de validité
                        $newExpiryDate = $existingUserPack->expiry_date > now() 
                            ? Carbon::parse($existingUserPack->expiry_date) 
                            : now();
                        $existingUserPack->expiry_date = $newExpiryDate->addMonths($request->months);
                        $existingUserPack->save();
                    } else {
                        //Implémenter le paiement API


                        //Si un code parrain est fourni, lier l'utilisateur au parrain
                        $sponsorPack = UserPack::where('referral_code', $request->referralCode)->first();

                        // Générer un code de parrainage unique
                        $referralLetter = substr($pack->name, 0, 1);
                        $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $referralCode = 'SPR' . $referralLetter . $referralNumber;

                        // Vérifier que le code est unique
                        while (UserPack::where('referral_code', $referralCode)->exists()) {
                            $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                            $referralCode = 'SPR' . $referralLetter . $referralNumber;
                        }

                        // Récupérer l'URL du frontend depuis le fichier .env
                        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

                        // Créer le lien de parrainage en utilisant l'URL du frontend
                        $referralLink = $frontendUrl . "/register?referral_code=" . $referralCode;

                        // Attacher le pack à l'utilisateur
                        $user->packs()->attach($pack->id, [
                            'status' => 'active',
                            'purchase_date' => now(),
                            'expiry_date' => now()->addMonths($validated['months']),
                            'is_admin_pack' => false,
                            'payment_status' => 'completed',
                            'referral_prefix' => 'SPR',
                            'referral_pack_name' => $pack->name,
                            'referral_letter' => $referralLetter,
                            'referral_number' => $referralNumber,
                            'referral_code' => $referralCode,
                            'link_referral' => $referralLink,
                            'sponsor_id' => $sponsorPack->user_id ?? null,
                        ]);
                        
                        // Récupérer l'instance UserPack créée
                        $userpack = UserPack::where('user_id', $user->id)
                                          ->where('pack_id', $pack->id)
                                          ->where('referral_code', $referralCode)
                                          ->first();
                    }

                    // Ajouter le montant au wallet system
                    $walletsystem = WalletSystem::first();
                    if (!$walletsystem) {
                        $walletsystem = WalletSystem::create(['balance' => 0]);
                    }
                    $walletsystem->addFunds($netAmountInUSD, "sales", "completed", [
                        "user" => $validated["name"], 
                        "pack_id" => $pack->id, 
                        "payment_details" => $validated['payment_details'],
                        "payment_method" => $paymentMethod,
                        "payment_type" => $paymentType,
                        "pack_name" => $pack->name, 
                        "sponsor_code" => $validated['sponsor_code'], 
                        "duration" => $validated['duration_months'],
                        "original_amount" => $paymentAmount,
                        "original_currency" => $paymentCurrency,
                        "transaction_fees" => $transactionFees,
                        "converted_amount" => $netAmountInUSD,
                    ]);
                }

                // Distribuer les commissions
                if ($existingUserPack) {
                    $this->processCommissions($existingUserPack, $validated['months']);
                }else {
                    $this->processCommissions($userpack, $validated['months']);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Pack acheté avec succès',
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'une erreur est survenue lors du traitement'
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


    //-------------------------------------FONCTION DE CONVERSION----------------------------------//
    private function estimateUSDAmount($amount, $currency)
    {
        // Taux de conversion approximatifs (à mettre à jour régulièrement)
        $rates = [
            'EUR' => 1.09,
            'GBP' => 1.27,
            'CAD' => 0.73,
            'AUD' => 0.66,
            'JPY' => 0.0067,
            'CHF' => 1.12,
            'CNY' => 0.14,
            'INR' => 0.012,
            'BRL' => 0.19,
            'ZAR' => 0.054,
            'NGN' => 0.00065,
            'GHS' => 0.071,
            'XOF' => 0.0017,
            'XAF' => 0.0017,
            'CDF' => 0.0017,
        ];
        
        if (isset($rates[$currency])) {
            return $amount * $rates[$currency];
        }
        
        // Si la devise n'est pas dans la liste, utiliser un taux par défaut
        //\Log::warning("Devise non reconnue pour la conversion: $currency. Utilisation du taux par défaut.");
        return $amount;
    }
    
    /**
     * Convertit un montant d'une devise en USD
     * 
     * @param float $amount Montant à convertir
     * @param string $currency Devise d'origine
     * @return float Montant en USD
     */
    private function convertToUSD($amount, $currency)
    {
        if ($currency === 'USD') {
            return $amount;
        }
        
        try {
            // Récupérer le taux de conversion depuis la BD
            $exchangeRate = ExchangeRates::where('currency', $currency)->where("target_currency", "USD")->first();
            if ($exchangeRate) {
                return $amount * $exchangeRate->rate;
            }
        } catch (\Exception $e) {
            \Log::error('Erreur lors de l\'appel à l\'API de conversion: ' . $e->getMessage());
        }
        
        // Si l'API échoue, utiliser l'estimation
        return $this->estimateUSDAmount($amount, $currency);
    }
} 