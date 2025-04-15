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
        // Supprimer tous les caractères non numériques
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // S'assurer que le numéro commence par le code pays
        if (!str_starts_with($phone, '243')) {
            // Si le numéro commence par 0, le remplacer par 243
            if (str_starts_with($phone, '0')) {
                $phone = '243' . substr($phone, 1);
            } else {
                $phone = '243' . $phone;
            }
        }
        
        // Vérifier la longueur après formatage
        if (strlen($phone) !== 12) {
            throw new \InvalidArgumentException('Le numéro de téléphone doit contenir 9 chiffres après le code pays (243)');
        }
        
        return $phone;
    }

    public function sendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'required_if:payment_method,orange-money,airtel-money,m-pesa,afrimoney',
                'payment_method' => 'required|string|in:mobile-money,card'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            Log::info('Tentative d\'envoi OTP pour l\'utilisateur', [
                'user_id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'payment_method' => $request->payment_method
            ]);
            
            // Pour Mobile Money, vérifier que le numéro de téléphone est fourni et valide
            if ($request->payment_method === 'mobile-money') {
                if (!$request->phone_number) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le numéro de téléphone est requis pour le paiement Mobile Money'
                    ], 422);
                }

                try {
                    $this->formatPhoneNumber($request->phone_number);
                } catch (\InvalidArgumentException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], 422);
                }
            }

            // Pour la carte, vérifier que l'utilisateur a un numéro enregistré et valide
            if ($request->payment_method === 'card') {
                if (!$user->phone) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Veuillez d\'abord enregistrer votre numéro de téléphone dans votre profil'
                    ], 400);
                }

                try {
                    $this->formatPhoneNumber($user->phone);
                } catch (\InvalidArgumentException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le numéro de téléphone dans votre profil est invalide. ' . $e->getMessage()
                    ], 400);
                }
            }

            $otp = rand(100000, 999999);
            session(['withdrawal_otp' => $otp]);
            Log::info('OTP généré', ['otp' => $otp]);

            // Envoyer l'OTP par email
            try {
                $user->notify(new WithdrawalOtpNotification($otp));
            } catch (\Exception $e) {
                throw $e;
            }

            // Envoyer l'OTP par SMS
            try {    
                $formattedPhone = $this->formatPhoneNumber($user->phone);
                
                $message = $request->payment_method === 'mobile-money'
                    ? "Votre code OTP pour le retrait est : $otp pour votre demande de retrait SOLIFIN au numéro: " . $request->phone_number
                    : "Votre code OTP pour le retrait par carte bancaire est : $otp";
                
                $response = $this->vonageClient->sms()->send(
                    new \Vonage\SMS\Message\SMS(
                        $formattedPhone,
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

    public function request(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'wallet_id' => 'required|exists:wallets,id',
                'payment_method' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'phone_number' => 'required_if:payment_method,orange-money,airtel-money,m-pesa,afrimoney',
                'otp' => 'required',
                'card_details' => 'required_if:payment_method,card|array',
                'card_details.number' => 'required_if:payment_method,card',
                'card_details.expiry' => 'required_if:payment_method,card',
                'card_details.holder_name' => 'required_if:payment_method,card'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérifier le format du numéro de téléphone pour Mobile Money
            if ($request->payment_method === 'mobile-money') {
                try {
                    $this->formatPhoneNumber($request->phone_number);
                } catch (\InvalidArgumentException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], 422);
                }
            }

            // Vérifier l'OTP
            $storedOtp = session('withdrawal_otp');
            if (!$storedOtp || $storedOtp != $request->otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code OTP invalide'
                ], 422);
            }

            // Récupérer le portefeuille
            $wallet = Wallet::findOrFail($request->wallet_id);
            
            // Vérifier le solde
            if ($wallet->balance < $request->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas suffisamment d\'argent dans votre portefeuille (' . $wallet->balance . ' ' . $wallet->currency . ' vs ' . $request->amount . ' ' . $wallet->currency . ')'
                ], 400);
            }

            DB::beginTransaction();

            $withdrawalRequest = WithdrawalRequest::create([
                'user_id' => auth()->id(),
                'amount' => $request->amount,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_details' => $request->payment_method === 'orange-money' || $request->payment_method === 'airtel-money' || $request->payment_method === 'm-pesa' || $request->payment_method === 'afrimoney'
                    ? ['phone_number' => $this->formatPhoneNumber($request->phone_number)]
                    : $request->card_details, "link" => "/admin/withdrawal-requests"
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
                    'payment_details' => $request->payment_method === 'orange-money' || $request->payment_method === 'airtel-money' || $request->payment_method === 'm-pesa' || $request->payment_method === 'afrimoney'
                        ? ['phone_number' => $this->formatPhoneNumber($request->phone_number)]
                        : $request->card_details,
                    'status' => 'en attente',
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
                'withdrawal_request' => $withdrawalRequest
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