<?php

namespace App\Http\Controllers;

use App\Models\WithdrawalRequest;
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
            $phone = '243' . $phone;
        }
        
        return $phone;
    }

    public function sendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone_number' => 'required|string'
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
                'phone' => $user->phone
            ]);
            
            // Vérifier si l'utilisateur a un numéro de téléphone enregistré
            if (!$user->phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez d\'abord enregistrer votre numéro de téléphone dans votre profil'
                ], 400);
            }

            $otp = rand(100000, 999999);
            session(['withdrawal_otp' => $otp]);
            Log::info('OTP généré', ['otp' => $otp]);

            // Envoyer l'OTP par email
            try {
                $user->notify(new WithdrawalOtpNotification($otp));
            } catch (\Exception $e) {
                throw $e; // Remonter l'erreur pour la gérer plus haut
            }

            // Envoyer l'OTP par SMS
            try {
                $formattedPhone = $this->formatPhoneNumber($user->phone);
                
                $response = $this->vonageClient->sms()->send(
                    new \Vonage\SMS\Message\SMS(
                        $formattedPhone,
                        config('services.vonage.sms_from', 'SOLIFIN'),
                        "Votre code OTP pour le retrait est : $otp pour votre demande de retrait SOLIFIN au numéro: ". 
                        $request->phone_number
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
                'wallet_type' => 'required|in:admin,system',
                'payment_method' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'phone_number' => 'required_if:payment_method,orange-money,airtel-money,m-pesa,afrimoney',
                'otp' => 'required_if:payment_method,orange-money,airtel-money,m-pesa,afrimoney',
                'card_details' => 'required_if:payment_method,visa,mastercard,credit-card|array',
                'card_details.number' => 'required_if:payment_method,visa,mastercard,credit-card',
                'card_details.expiry' => 'required_if:payment_method,visa,mastercard,credit-card',
                'card_details.cvv' => 'required_if:payment_method,visa,mastercard,credit-card',
                'card_details.holder_name' => 'required_if:payment_method,visa,mastercard,credit-card'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            //Vérifier si l'utilisateur a assez d'argent dans son wallet pour cette demande
            $wallet = Wallet::find($request->wallet_id);
            if ($wallet->balance < $request->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas suffisamment d\'argent dans votre portefeuille (' . $wallet->balance . ' ' . $wallet->currency . ' vs ' . $request->amount . ' ' . $wallet->currency . ')'
                ], 400);
            }
            
            // Vérifier l'OTP pour les paiements mobile money
            if (in_array($request->payment_method, ['orange-money', 'airtel-money', 'm-pesa', 'afrimoney'])) {
                $storedOtp = session('withdrawal_otp');
                if (!$storedOtp || $storedOtp != $request->otp) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Code OTP invalide'
                    ], 400);
                }
            }

            DB::beginTransaction();

            // Créer la demande de retrait
            $withdrawalRequest = WithdrawalRequest::create([
                'user_id' => Auth::id(),
                'amount' => $request->amount,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_details' => $request->payment_method === 'orange-money' || $request->payment_method === 'airtel-money' || $request->payment_method === 'm-pesa' || $request->payment_method === 'afrimoney'
                    ? ['phone_number' => $request->phone_number]
                    : $request->card_details
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
            $requests = WithdrawalRequest::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'requests' => $requests
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
}