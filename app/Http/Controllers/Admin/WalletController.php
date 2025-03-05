<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletSystem;
use App\Models\WalletSystemTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function getWalletData()
    {
        try {
            // Récupérer le wallet de l'admin connecté
            $userWallet = Wallet::where('user_id', Auth::id())->first();
            $adminWallet = $userWallet ? [
                'balance' => number_format($userWallet->balance, 2) . ' $',
                'total_earned' => number_format($userWallet->total_earnings, 2) . ' $',
                'total_withdrawn' => number_format($userWallet->total_withdrawals, 2) . ' $',
            ] : null;

            // Récupérer le wallet system (il n'y en a qu'un seul)
            $systemWallet = WalletSystem::first();
            $systemWalletData = $systemWallet ? [
                'balance' => number_format($systemWallet->balance, 2) . ' $',
                'total_in' => number_format($systemWallet->total_transactions, 2) . ' $',
                'total_out' => number_format($systemWallet->total_withdrawals, 2) . ' $',
            ] : null;

            // Récupérer les transactions du wallet system
            $transactions = WalletSystemTransaction::with('walletSystem')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'amount' => number_format($transaction->amount, 2) . ' €',
                        'type' => $transaction->type,
                        'status' => $transaction->status,
                        'metadata' => $transaction->metadata,
                        'created_at' => $transaction->created_at->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'success' => true,
                'adminWallet' => $adminWallet,
                'systemWallet' => $systemWalletData,
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
} 