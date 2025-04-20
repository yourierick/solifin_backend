<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Models\WalletSystem;
use App\Models\Wallet;
use App\Models\User;
use App\Notifications\WithdrawalOtpNotification;
use App\Notifications\WithdrawalRequestCreated;
use App\Notifications\WithdrawalRequestProcessed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS;

class WithdrawalController extends Controller
{
    protected $vonageClient;

    public function __construct()
    {
        $basic = new \Vonage\Client\Credentials\Basic(
            config('services.vonage.key'),
            config('services.vonage.secret')
        );
        $this->vonageClient = new \Vonage\Client($basic);
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


    public function sendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'required_if:payment_type,mobile-money',
                'payment_method' => 'required|string',
                'payment_type' => 'required|string|in:mobile-money,bank-transfer,money-transfer,credit-card',
                'amount' => 'required|numeric|min:0',
                'currency' => 'required|string',
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

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            
            // Vérifier le format du numéro de téléphone si présent
            if ($request->has('phone_number') && !empty($request->phone_number)) {
                $this->formatPhoneNumber($request->phone_number);
            }

            //Vérifier que l'utilisateur a un numéro enregistré et valide
            if (!$user->phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez d\'abord enregistrer votre numéro de téléphone dans votre profil'
                ], 400);
            }

            $formatted_phone = $this->formatPhoneNumber($user->phone);

            // Générer un OTP
            $otp = rand(100000, 999999);
            
            // Stocker l'OTP dans la base de données au lieu de la session
            DB::table('withdrawal_otps')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'otp' => $otp,
                    'expires_at' => now()->addMinutes(10),
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
            
            Log::info('OTP généré', ['otp' => $otp, 'user_id' => $user->id]);

            // Envoyer l'OTP par email
            try {
                $user->notify(new WithdrawalOtpNotification($otp));
            } catch (\Exception $e) {
                throw $e;
            }

            // Envoyer l'OTP par SMS
            try {
                $message = "Votre code OTP pour le retrait est : $otp pour votre demande de retrait SOLIFIN au numéro: " . json_encode($request->payment_details);
                
                $response = $this->vonageClient->sms()->send(
                    new \Vonage\SMS\Message\SMS(
                        $formatted_phone,
                        config('services.vonage.sms_from', 'SOLIFIN'),
                        $message
                    )
                );

            } catch (\Exception $e) {
                Log::error('Erreur Vonage', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e; // Remonter l'erreur pour la gérer plus haut
            }

            return response()->json([
                'success' => true,
                'message' => 'Code OTP envoyé par email et SMS',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur générale lors de l\'envoi du code OTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du code OTP: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
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
                'otp' => 'required',
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

            // Vérifier l'OTP
            $storedOtp = DB::table('withdrawal_otps')->where('user_id', $request->user()->id)->first();
            \Log::info('Vérification OTP', [
                'stored_otp' => $storedOtp->otp,
                'request_otp' => $request->otp,
                'match' => ($storedOtp->otp == $request->otp),
            ]);
            
            if (!$storedOtp || $storedOtp->otp != $request->otp || $storedOtp->expires_at < now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code OTP invalide ou expiré. Veuillez demander un nouveau code OTP.'
                ], 422);
            }

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

            // Créer une transaction dans le wallet
            $wallet->transactions()->create([
                'type' => 'withdrawal',
                'amount' => $request->amount,
                'status' => 'pending',
                'metadata' => [
                    'withdrawal_request_id' => $withdrawalRequest->id,
                    'payment_method' => $request->payment_method,
                    'montant_a_retirer' => $request->amount,
                    'fee_percentage' => $request->fee_percentage,
                    'frais_de_retrait' => $request->withdrawal_fee,
                    'frais_de_commission' => $request->referral_commission,
                    'montant_total_a_payer' => $request->total_amount,
                    'payment_details' => $request->payment_details,
                    'status' => 'pending',
                ]
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

            DB::beginTransaction();

            // Mettre à jour la transaction
            $transaction = $withdrawal->user->wallet->transactions()
                ->where('type', 'withdrawal')
                ->where('metadata->withdrawal_request_id', $id)
                ->first();

            if ($transaction) {
                $transaction->status = 'completed';
                $transaction->save();
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

            // Créer une transaction de remboursement
            $wallet->transactions()->create([
                'amount' => $withdrawal->amount,
                'type' => 'refund',
                'status' => 'completed',
                'metadata' => [
                    'source' => 'withdrawal_rejected',
                    'withdrawal_request_id' => $withdrawal->id
                ]
            ]);

            // Mettre à jour la transaction originale
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
}