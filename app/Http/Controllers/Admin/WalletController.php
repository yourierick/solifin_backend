<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletSystem;
use App\Models\WalletSystemTransaction;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class WalletController extends Controller
{
    public function getWalletData()
    {
        try {
            // Récupérer le wallet de l'admin connecté
            $userWallet = Wallet::where('user_id', Auth::id())->first();
            $adminWallet = $userWallet ? [
                'balance' => number_format($userWallet->balance, 2) . ' $',
                'total_earned' => number_format($userWallet->total_earned, 2) . ' $',
                'total_withdrawn' => number_format($userWallet->total_withdrawn, 2) . ' $',
            ] : null;

            // Récupérer le wallet system (il n'y en a qu'un seul)
            $systemWallet = WalletSystem::first();
            $systemWalletData = $systemWallet ? [
                'balance' => number_format($systemWallet->balance, 2) . ' $',
                'total_in' => number_format($systemWallet->total_in, 2) . ' $',
                'total_out' => number_format($systemWallet->total_out, 2) . ' $',
            ] : null;

            // Récupérer les transactions du wallet system
            $systemwallettransactions = WalletSystemTransaction::with('walletSystem')
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

            // Récupérer les transactions du wallet
            $adminwallettransactions = WalletTransaction::with('wallet')->where('wallet_id', $userWallet->id)
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
                'adminWallet' => $adminWallet,
                'systemWallet' => $systemWalletData,
                'systemwallettransactions' => $systemwallettransactions,
                'adminwallettransactions' => $adminwallettransactions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function withdraw(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'wallet_id' => 'required',
    //             'wallet_type' => 'required|in:admin,system',
    //             'payment_method' => 'required',
    //             'amount' => 'required|numeric|min:0',
    //         ]);

    //         // Logique de retrait à implémenter selon vos besoins

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Demande de retrait enregistrée avec succès'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur lors de la demande de retrait',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Transfert de fonds entre wallets
     */
    public function funds_transfer(Request $request)
    {
        try {
            $request->validate([
                'recipient_account_id' => 'required',
                'amount' => 'required|numeric|min:0',
                'description' => 'required',
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

            $userWallet->withdrawFunds($request->amount, "transfer", "completed", ["bénéficiaire" => $recipientWallet->user->name, "montant"=>$request->amount, "description"=>$request->description]);
            $recipientWallet->addFunds($request->amount, "reception", "completed", ["créditeur" => $userWallet->user->name, "montant"=>$request->amount, "description"=>$request->description]);


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
} 