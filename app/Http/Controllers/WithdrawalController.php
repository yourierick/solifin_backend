<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Models\WalletSystem;
use App\Models\Wallet;
use App\Models\User;
use App\Models\Setting;
use App\Notifications\WithdrawalRequestCreated;
use App\Notifications\WithdrawalRequestProcessed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class WithdrawalController extends Controller
{
    public function __construct()
    {
        // Constructeur simplifié - Service Vonage supprimé
    }

    protected function formatPhoneNumber($phone)
    {
        // Le traitement de l'indicatif téléphonique est maintenant géré côté frontend
        // Cette fonction ne fait plus que vérifier la validité du numéro
        
        // Supprimer tous les caractères non numériques
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Vérifier que le numéro n'est pas vide
        if (empty($phone)) {
            throw new \InvalidArgumentException("Le numéro de téléphone ne peut pas être vide");
        }
        
        // Retourner le numéro tel quel (déjà formaté par le frontend)
        return $phone;
    }


    public function request(Request $request, $walletId)
    {
        \Log::info('Tentative de retrait - Début', [
            'request' => $request->all(),
            'wallet_id' => $walletId,
            'method' => $request->method(),
            'url' => $request->url(),
            'headers' => $request->header()
        ]);
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'required_if:payment_type,mobile-money',
                'payment_method' => 'required|string',
                'payment_type' => 'required|string|in:mobile-money,bank-transfer,money-transfer,credit-card',
                'amount' => 'required|numeric|min:0',
                'currency' => 'required|string',
                'password' => 'required',
                'withdrawal_fee' => 'required|numeric',
                'referral_commission' => 'required|numeric',
                'total_amount' => 'required|numeric',
                'fee_percentage' => 'required|numeric',
                'account_name' => 'required_if:payment_type,credit-card',
                'account_number' => 'required_if:payment_type,bank-transfer',
                'bank_name' => 'required_if:payment_type,bank-transfer',
                'id_type' => 'required_if:payment_type,money-transfer',
                'id_number' => 'required_if:payment_type,money-transfer',
                'full_name' => 'required_if:payment_type,money-transfer',
                'recipient_country' => 'required_if:payment_type,money-transfer',
                'recipient_city' => 'required_if:payment_type,money-transfer',
            ]);

            \Log::info('Validation terminée', [
                'passes' => !$validator->fails(),
                'errors_count' => count($validator->errors()),
                'errors' => $validator->errors()->toArray()
            ]);

            if ($validator->fails()) {
                \Log::error('Validation error', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérifier le format du numéro de téléphone si présent
            if ($request->has('phone_number') && !empty($request->phone_number)) {
                $this->formatPhoneNumber($request->phone_number);
            }

            // Vérifier l'authentification (mot de passe)
            $user = $request->user();
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe incorrect. Veuillez réessayer.'
                ], 422);
            }
            \Log::info('Authentification par mot de passe réussie', [
                'user_id' => $user->id
            ]);

            // Récupérer le portefeuille
            $wallet = Wallet::findOrFail($walletId);
            
            // Vérifier le solde
            if ($wallet->balance < $request->total_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas suffisamment d\'argent dans votre portefeuille (' . $wallet->balance . ' ' . $wallet->currency . ' vs ' . $request->amount . ' ' . $wallet->currency . ')'
                ], 400);
            }

            DB::beginTransaction();

            $withdrawalRequest = WithdrawalRequest::create([
                'user_id' => auth()->id(),
                'amount' => $request->total_amount,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_details' => [
                    "montant_a_retirer" => $request->amount,
                    "devise" => $request->currency,
                    "fee_percentage" => $request->fee_percentage,
                    "frais_de_retrait" => $request->withdrawal_fee,
                    "frais_de_commission" => $request->referral_commission,
                    "montant_total_a_payer" => $request->total_amount,
                    "payment_details" => $request->payment_details, 
                    "link" => "/admin/withdrawal-requests"
                ]
            ]);

            $user = $request->user();
            $wallet = $user->wallet;

            //Géler le montant à retirer du wallet de l'utilisateur
            $wallet->withdrawFunds($request->total_amount, "withdrawal", "pending", [
                'withdrawal_request_id' => $withdrawalRequest->id,
                'Dévise' => $request->currency,
                'Méthode de paiement' => $request->payment_method,
                'Montant à rétirer' => $request->amount,
                'Pourcentage des frais' => $request->fee_percentage,
                'Frais de retrait' => $request->withdrawal_fee,
                'Frais de commission' => $request->referral_commission,
                'Montant total à payer' => $request->total_amount,
                'Détails de paiement' => $request->payment_details,
                'Statut' => 'En attente',
            ]);

            DB::commit();

            // Notifier l'administrateur
            $admin = User::where('is_admin', true)->first();
            if ($admin) {
                $admin->notify(new WithdrawalRequestCreated($withdrawalRequest));
            }

            return response()->json([
                'success' => true,
                'message' => 'Demande de retrait créée avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la création de la demande', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getRequests()
    {
        try {
            $requests = WithdrawalRequest::with(['user', 'user.wallet'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'user_id' => $request->user_id,
                        'user_name' => $request->user->name,
                        'user' => $request->user,
                        'wallet_balance' => $request->user->wallet->balance,
                        'amount' => $request->amount,
                        'status' => $request->status,
                        'payment_method' => $request->payment_method,
                        'payment_details' => $request->payment_details,
                        'admin_note' => $request->admin_note,
                        'created_at' => $request->created_at,
                        'processed_at' => $request->processed_at,
                    ];
                });

            $walletSystem = WalletSystem::first()->balance;

            return response()->json([
                'success' => true,
                'requests' => $requests,
                'wallet_system_balance' => $walletSystem
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des demandes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des demandes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel($id)
    {
        try {
            $withdrawal = WithdrawalRequest::find($id);

            DB::beginTransaction();

            // Mettre à jour la transaction
            \Log::info($withdrawal->user->wallet);
            $transaction = $withdrawal->user->wallet->transactions()
                ->where('type', 'withdrawal')
                ->where('metadata->withdrawal_request_id', $id)
                ->first();

            if ($transaction) {
                $transaction->status = 'cancelled';
                $transaction->save();
            }

            // Annuler la demande
            if ($withdrawal) {
                $withdrawal->status = 'cancelled';
                $withdrawal->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande annulée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de l\'annulation de la demande', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $withdrawal = WithdrawalRequest::find($id);

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande de retrait non trouvée'
                ], 404);
            }

            DB::beginTransaction();

            // Supprimer la transaction associée si elle existe
            $transaction = $withdrawal->user->wallet->transactions()
                ->where('type', 'withdrawal')
                ->where('metadata->withdrawal_request_id', $id)
                ->first();

            if ($transaction) {
                $transaction->delete();
            }

            // Supprimer la demande
            $withdrawal->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la suppression de la demande', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function approve(Request $request, $id)
    {
        try {
            $withdrawal = WithdrawalRequest::find($id);

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande de retrait non trouvée'
                ], 404);
            }

            if ($withdrawal->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette demande ne peut pas être approuvée car elle n\'est pas en attente'
                ], 400);
            }

            if ($withdrawal->amount > $withdrawal->user->wallet->balance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le solde de ce compte est insuffisant pour effectuer ce retrait'
                ], 400);
            }

            $fee_percentage = $withdrawal->payment_details['fee_percentage'];
            $commission_fees = $withdrawal->payment_details['frais_de_commission'];
            
            $transactionFeeModel = TransactionFee::where('payment_method', $withdrawal->payment_method)
                ->where('is_active', true);
            
            $transactionFee = $transactionFeeModel->first();
            
            // Recalculer les frais de transaction
            $globalFeePercentage = (float) Setting::getValue('withdrawal_fee_percentage', 0);
            $globalfees = ((float)$withdrawal->payment_details['montant_a_retirer']) * ($globalFeePercentage / 100);

            $specificfees = 0;
            if ($transactionFee) {
                $specificfees = $transactionFee->calculateWithdrawalFee((float) $withdrawal->payment_details['montant_a_retirer'], "USD");
            }
            $frais_system = $globalfees - $specificfees; //frais qui resteront après paiement des frais de l'api et qui seront reversés dans le système
            
            //Implémenter le paiement API
            DB::beginTransaction();

            $transaction = $withdrawal->user->wallet->transactions()
                ->where('type', 'withdrawal')
                ->where('metadata->withdrawal_request_id', $id)
                ->first();

            if ($transaction) {
                $transaction->status = 'completed';
                $transaction->save();
            }

            $walletsystem = WalletSystem::first();
            if (!$walletsystem) {
                $walletsystem = WalletSystem::create(['balance' => 0]);
            }

            if ($frais_system > 0) {
                $walletsystem->transactions()->create([
                    'type' => 'frais de retrait',
                    'amount' => $frais_system,
                    'status' => 'completed',
                    'metadata' => [
                        'user' => $withdrawal->user->name,
                        'Montant original' => $withdrawal->payment_details['montant_a_retirer'],
                        'Dévise originale' => "USD",
                        'Frais de transaction' => $globalfees,
                        'Pourcentage global de transaction' => $globalFeePercentage,
                        'Frais API' => $specificfees,
                        'Déscription' => "frais de transaction de " . $frais_system . " $ pour le retrait d'un montant de " . $withdrawal->payment_details['montant_a_retirer'] . " $ par le compte " . $withdrawal->user->account_id,
                    ]
                ]);
            }

            $walletsystem->deductFunds($withdrawal->payment_details['montant_a_retirer'], "withdrawal", "completed", [
                "user" => $withdrawal->user->name, 
                "Montant original" => $withdrawal->payment_details['montant_a_retirer'],
                "Dévise originale" => "USD",
                "Frais de transaction" => $globalfees,
                "Pourcentage global de transaction" => $globalFeePercentage,
                "Frais API" => $specificfees,
                "Description" => "retrait de ". $withdrawal->payment_details['montant_a_retirer'] . " $ par le compte " . $withdrawal->user->account_id,
            ]);

            $firstuserpack = UserPack::where('user_id', $withdrawal->user->id)->first(); //récupérer le premier pack de l'utilisateur
            $sponsor = $firstuserpack->sponsor;
            if ($sponsor) {
                $sponsor->wallet->addFunds($commission_fees, "commission de retrait", "completed", [
                    "Source" => $withdrawal->user->name, 
                    "Type" => "commission de retrait",
                    "Montant" => $commission_fees,
                    "Déscription" => "commission de ". $commission_fees . " $ pour le retrait d'un montant de ". $withdrawal->payment_details['montant_a_retirer'] ." $ par votre filleul " . $withdrawal->user->name,
                ]);

                $walletsystem->transactions()->create([
                    "wallet_system_id" => $walletsystem->id,
                    'amount' => $commission_fees,
                    'type' => "commission de retrait",
                    'status' => "completed",
                    'metadata' => [
                        "Type de transaction" => "Commission de retrait",
                        "Source" => $withdrawal->user->name,
                        "Bénéficiaire" => $sponsor->name,
                        "Montant" => $commission_fees,
                        "Déscription" => "commission de ". $commission_fees . " $ pour le retrait d'un montant de ". $withdrawal->payment_details['montant_a_retirer'] ." $ par votre filleul " . $withdrawal->user->name,
                    ]
                ]);
            }

            // Approuver la demande
            $withdrawal->status = 'approved';
            $withdrawal->admin_note = $request->admin_note;
            $withdrawal->processed_by = auth()->id();
            $withdrawal->processed_at = now();
            $withdrawal->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande approuvée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de l\'approbation de la demande', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'approbation de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $withdrawal = WithdrawalRequest::find($id);

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande de retrait non trouvée'
                ], 404);
            }

            if ($withdrawal->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette demande ne peut pas être rejetée car elle n\'est pas en attente'
                ], 400);
            }

            DB::beginTransaction();

            // Rembourser le montant au wallet de l'utilisateur
            $user = $withdrawal->user;
            $wallet = $user->wallet;
            $wallet->balance += $withdrawal->amount;
            $wallet->save();

            // Mettre à jour la transaction
            $transaction = $wallet->transactions()
                ->where('type', 'withdrawal')
                ->where('metadata->withdrawal_request_id', $id)
                ->first();

            if ($transaction) {
                $transaction->status = 'failed';
                $transaction->save();
            }

            // Rejeter la demande
            $withdrawal->status = 'rejected';
            $withdrawal->admin_note = $request->admin_note;
            $withdrawal->processed_by = auth()->id();
            $withdrawal->processed_at = now();
            $withdrawal->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande rejetée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors du rejet de la demande', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rejet de la demande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère le pourcentage de commission de parrainage depuis les paramètres du système
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReferralCommissionPercentage()
    {
        try {
            // Récupérer le paramètre withdrawal_commission
            $setting = Setting::where('key', 'withdrawal_commission')->first();
            
            if ($setting) {
                return response()->json([
                    'success' => true,
                    'percentage' => (float) $setting->value,
                    'description' => $setting->description
                ]);
            } else {
                // Si le paramètre n'est pas défini, retourner 0%
                return response()->json([
                    'success' => true,
                    'percentage' => 0,
                    'description' => 'Pourcentage de commission de parrainage (valeur par défaut)'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du pourcentage de commission de parrainage', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du pourcentage de commission de parrainage',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}