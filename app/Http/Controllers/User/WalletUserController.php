<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Setting;

class WalletUserController extends Controller
{
    // Récupérer les données du wallet de l'utilisateur connecté
    public function getWalletData()
    {
        try {
            // Récupérer le wallet de l'utilisateur connecté
            $userWallet = Wallet::where('user_id', Auth::id())->first();
            $Wallet = $userWallet ? [
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

            return response()->json([
                'success' => true,
                'userWallet' => $userWallet,
                'transactions' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Récupérer le solde du wallet de l'utilisateur connecté
    public function getWalletBalance()
    {
        try {
            $userWallet = Wallet::where('user_id', Auth::id())->first();
            return response()->json([
                'success' => true,
                'balance' => number_format($userWallet->balance, 2) . ' $'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du solde',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    public function withdraw(Request $request)
    {
        try {
            $request->validate([
                'wallet_id' => 'required',
                'wallet_type' => 'required|in:admin,system',
                'payment_method' => 'required',
                'amount' => 'required|numeric|min:0',
            ]);

            // Logique de retrait à implémenter selon vos besoins

            return response()->json([
                'success' => true,
                'message' => 'Demande de retrait enregistrée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la demande de retrait',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Transfert de fonds entre wallets
     */
    public function funds_transfer(Request $request)
    {
        try {
            $request->validate([
                'recipient_account_id' => 'required',
                'amount' => 'required|numeric|min:0',
                'original_amount' => 'required|numeric',
                'fee_amount' => 'required|numeric',
                'fee_percentage' => 'required|numeric',
                'password' => 'required'
            ]);

            // Vérifier le mot de passe de l'utilisateur
            $user = Auth::user();
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe incorrect'
                ], 401);
            }

            $userWallet = Wallet::where('user_id', Auth::id())->first();
            $recipient = User::where("account_id", $request->recipient_account_id)->first();
            
            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Compte du bénéficiaire non trouvé'
                ], 404);
            }
            
            $recipientWallet = Wallet::where('user_id', $recipient->id)->first();

            if (!$recipientWallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Compte du bénéficiaire non trouvé'
                ], 404);
            }

            if ($userWallet->id == $recipientWallet->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas transférer des fonds sur votre propre compte'
                ], 400);
            }

            if ($userWallet->balance < $request->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solde insuffisant'
                ], 400);
            }

            if ($request->fee_amount > 0) {
                // Ajouter la transaction au wallet system
                $walletsystem = WalletSystem::first();
                if (!$walletsystem) {
                    $walletsystem = WalletSystem::create(['balance' => 0]);
                }
                $walletsystem->addFunds(0, "sales", "completed", [
                    "user" => $user->name, 
                    "original_amount" => $request->original_amount,
                    "original_currency" => "USD",
                    "transaction_fees" => $request->fee_amount,
                    "transaction_percentage" => $request->fee_percentage,
                    "description" => "frais de transfert de ". $request->fee_amount . " $ pour le transfert d'un montant de ". $request->amount-$request->fee_amount ." $ par le compte " . $user->account_id . " au compte " . $request->recipient_account_id,
                ]);
            }

            $userWallet->withdrawFunds($request->amount, "transfer", "completed", ["bénéficiaire" => $recipientWallet->user->name, "montant"=>$request->original_amount, "description"=>$request->description]);
            $recipientWallet->addFunds($request->original_amount, "reception", "completed", ["créditeur" => $userWallet->user->name, "montant"=>$request->original_amount, "description"=>$request->description]);


            return response()->json([
                'success' => true,
                'message' => 'Transfert effectué avec succès'
            ]);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du transfert',
                'error' => 'Erreur lors du transfert'
            ], 500);
        }
    }

    /**
     * Récupérer les informations d'un utilisateur par son account_id pour le transfert des fonds wallet-wallet
     */
    public function getRecipientInfo($account_id)
    {
        $recipient = \App\Models\User::where('account_id', $account_id)->first();
        if (!$recipient) {
            return response()->json([
                'success' => false,
                'message' => 'Destinataire introuvable'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'user' => [
                'account_id' => $recipient->account_id,
                'name' => $recipient->name,
                'phone' => $recipient->phone,
                'whatsapp' => $recipient->whatsapp,
                'email' => $recipient->email,
                'address' => $recipient->address,
            ]
        ]);
    }

    /**
     * Récupère le pourcentage de frais de transfert depuis les paramètres du système
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransferFeePercentage()
    {
        $feePercentage = \App\Models\Setting::where('key', 'sending_fee_percentage')->first();
        
        if (!$feePercentage) {
            // Valeur par défaut si le paramètre n'existe pas
            $feePercentage = 0;
        } else {
            $feePercentage = floatval($feePercentage->value);
        }
        
        return response()->json([
            'success' => true,
            'fee_percentage' => $feePercentage
        ]);
    }
} 