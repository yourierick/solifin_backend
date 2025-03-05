<?php

namespace App\Http\Controllers;

use App\Models\Pack;
use App\Models\User;
use App\Models\UserPack;
use App\Services\CommissionService;
use App\Notifications\PackPurchased;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\WalletSystem;

class PackPurchaseController extends Controller
{
    protected $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    public function show($sponsor_code)
    {
        try {
            // $purchase = UserPack::with(['pack', 'user'])
            //     ->findOrFail($purchaseId);

            $user_pack = UserPack::with(['user', 'pack'])->where('referral_code', $sponsor_code)->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'pack' => $user_pack->pack,
                    'sponsor' => $user_pack->user
                    //'purchase' => $purchase
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des détails de l\'achat',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function purchase(Request $request, Pack $pack)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'duration_months' => 'required|integer|min:1',
            ]);

            $total_paid = $pack->price * $validated['duration_months'];
            $walletsystem = WalletSystem::first();
            $walletsystem->balance += $total_paid;
            $walletsystem->total_in += $total_paid;
            $walletsystem->update(); 

            $referralLetter = substr($pack->name, 0, 1);
            $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $referralCode = 'SPR' . $referralLetter . $referralNumber;

            // Vérifier que le code est unique
            while (UserPack::where('referral_code', $referralCode)->exists()) {
                $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $referralCode = 'SPR' . $referralLetter . $referralNumber;
            }

            $userPack = $request->user()->packs()->create([
                'pack_id' => $pack->id,
                'status' => 'active',
                'purchase_date' => now(),
                'expiry_date' => now()->addMonths($validated['duration_months']),
                'is_admin_pack' => false,
                'payment_status' => 'completed',
                'referral_pack_name' => 'SPR',
                'referral_pack_name' => $pack->name,
                'referral_letter' => $referralLetter,
                'referral_number' => $referralNumber,
                'referral_code' => $referralCode
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pack acheté avec succès',
                'data' => $userPack
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur dans PackController@purchase: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'achat du pack'
            ], 500);
        }
    }


    // public function initiate(Request $request)
    // {
    //     try {
    //         $validated = $request->validate([
    //             'pack_id' => 'required|exists:packs,id',
    //             'sponsor_code' => 'required|exists:user_packs,referral_code'
    //         ]);

    //         $sponsorPack = UserPack::where('referral_code', $request->sponsor_code)
    //             ->where('pack_id', $request->pack_id)
    //             ->where('payment_status', 'completed')
    //             ->first();

    //         if (!$sponsorPack) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Le parrain ne possède pas ce pack'
    //             ], 422);
    //         }

    //         // Générer un code de parrainage unique
    //         $pack = Pack::find($request->pack_id);
    //         $referralCode = $this->generateReferralCode($pack->name);

    //         // Créer l'entrée d'achat
    //         $purchase = UserPack::create([
    //             'user_id' => auth()->id(),
    //             'pack_id' => $request->pack_id,
    //             'sponsor_id' => $sponsorPack->user_id,
    //             'referral_prefix' => 'SPR',
    //             'referral_pack_name' => $pack->name,
    //             'referral_letter' => substr($referralCode, -6, 1),
    //             'referral_number' => intval(substr($referralCode, -4)),
    //             'referral_code' => $referralCode,
    //             'payment_status' => 'pending'
    //         ]);

    //         return response()->json([
    //             'success' => true,
    //             'data' => [
    //                 'purchase_id' => $purchase->id,
    //                 'payment_url' => route('payment.process', $purchase->id)
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur lors de l\'initialisation de l\'achat',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function process(Request $request, $purchaseId)
    // {
    //     try {
    //         $validated = $request->validate([
    //             'payment_method' => 'required|in:wallet,card,bank_transfer'
    //         ]);

    //         $purchase = UserPack::with(['pack', 'user.wallet'])->findOrFail($purchaseId);

    //         if ($request->payment_method === 'wallet') {
    //             // Vérifier si l'utilisateur a suffisamment de fonds
    //             if (!$purchase->user->wallet || $purchase->user->wallet->balance < $purchase->pack->price) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Solde insuffisant dans le portefeuille'
    //                 ], 422);
    //             }

    //             DB::transaction(function () use ($purchase) {
    //                 // Débiter le portefeuille
    //                 $purchase->user->wallet->decrement('balance', $purchase->pack->price);

    //                 // Mettre à jour le statut de l'achat
    //                 $purchase->update([
    //                     'payment_status' => 'completed',
    //                     'purchase_date' => now()
    //                 ]);

    //                 // Distribuer les commissions
    //                 $this->commissionService->distributeCommissions($purchase);

    //                 // Notifier l'utilisateur
    //                 $purchase->user->notify(new PackPurchased($purchase));
    //             });

    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Paiement effectué avec succès via le portefeuille'
    //             ]);
    //         } else {
    //             // Simulation de paiement par carte ou virement
    //             $success = true; // Pour les tests

    //             if ($success) {
    //                 DB::transaction(function () use ($purchase) {
    //                     $purchase->update([
    //                         'payment_status' => 'completed',
    //                         'purchase_date' => now()
    //                     ]);

    //                     $this->commissionService->distributeCommissions($purchase);
    //                     $purchase->user->notify(new PackPurchased($purchase));
    //                 });

    //                 return response()->json([
    //                     'success' => true,
    //                     'message' => 'Paiement effectué avec succès'
    //                 ]);
    //             }
    //         }

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Le paiement a échoué'
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur lors du traitement du paiement',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // protected function generateReferralCode($packName)
    // {
    //     $prefix = 'SPR';
    //     $packCode = Str::upper(Str::substr($packName, 0, 3));
        
    //     do {
    //         $letter = chr(rand(65, 90)); // A-Z
    //         $number = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    //         $referralCode = $prefix . $packCode . $letter . $number;
    //     } while (UserPack::where('referral_code', $referralCode)->exists());

    //     return $referralCode;
    // }
}
