<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPack;
use App\Models\Wallet;
use App\Models\WalletSystem;
use App\Models\Pack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\ReferralCodeService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\CommissionService;
use App\Notifications\VerifyEmailWithCredentials;

class RegisterController extends Controller
{
    public function register(Request $request, $pack_id)
    {
        try {
            //\Log::info('Register request: ' . json_encode($request->all()));
            // Valider les données
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'address' => 'required|string',
                'phone' => 'required|string',
                'whatsapp' => 'nullable|string',
                'sponsor_code' => 'required|exists:user_packs,referral_code',
                'duration_months' => 'required|integer|min:1',
                'payment_method' => 'required|string',
                'payment_details' => 'required|array',
                'gender' => 'required|string',
                'country' => 'required|string',
                'province' => 'required|string',
                'city' => 'required|string',
                'currency' => 'nullable|string|max:3',
                'amount' => 'required|numeric',
                'fees' => 'nullable|numeric',
            ]);

            DB::beginTransaction();

            $pack = Pack::find($pack_id);

            // Récupérer les informations de paiement
            $paymentMethod = $validated['payment_method'];
            $paymentAmount = $validated['amount'];
            $paymentCurrency = $validated['currency'] ?? 'USD';
            $transactionFees = $validated['fees'] ?? 0;
            
            // Si la devise n'est pas en USD, convertir le montant en USD (devise de base)
            $amountInUSD = $paymentAmount;
            if ($paymentCurrency !== 'USD') {
                try {
                    // Appel à un service de conversion de devise
                    $amountInUSD = $this->convertToUSD($paymentAmount, $paymentCurrency);
                    $amountInUSD = round($amountInUSD, 0);
                } catch (\Exception $e) {
                    \Log::error('Erreur lors de la conversion de devise: ' . $e->getMessage());
                    // Fallback: utiliser un taux de conversion fixe ou une estimation
                    $amountInUSD = $this->estimateUSDAmount($paymentAmount, $paymentCurrency);
                }
            }
            
            // Soustraire les frais de transaction s'ils sont inclus dans le montant
            $feesInUSD = $this->convertToUSD($transactionFees, $paymentCurrency);
            $netAmountInUSD = $amountInUSD - $feesInUSD;
            
            // Vérifier que le montant net est suffisant pour couvrir le coût du pack
            $packCost = $pack->price * $validated['duration_months'];
            if ($amountInUSD < $packCost) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant payé est insuffisant pour couvrir le coût du pack'
                ], 400);
            }

            //Logique de paiement api à implémenter

            // Enregistrer le paiement dans le système
            $walletsystem = WalletSystem::first();
            $walletsystem->addFunds($netAmountInUSD, "sales", "completed", [
                "user" => $validated["name"], 
                "pack_id" => $pack->id, 
                "payment_details" => $validated['payment_details'],
                "pack_name" => $pack->name, 
                "sponsor_code" => $validated['sponsor_code'], 
                "duration" => $validated['duration_months'],
                "original_amount" => $paymentAmount,
                "original_currency" => $paymentCurrency,
                "transaction_fees" => $transactionFees,
                "converted_amount" => $netAmountInUSD,
                "payment_method" => $paymentMethod
            ]);

            // Stocker le mot de passe en clair temporairement pour l'email
            $plainPassword = $validated['password'];
            
            // Créer l'utilisateur
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($plainPassword),
                'whatsapp' => $request->get('whatsapp'),
                'phone' => $validated['phone'],
                'sexe' => $validated['gender'],
                'pays' => $validated['country'],
                'province' => $validated['province'],
                'ville' => $validated['city'],
                'address' => $validated['address'],
                'status' => 'active',
                'pack_de_publication_id' => $pack->id,
            ]);

            $account_id = "00-CPT-".$user->id;
            $user->account_id = $account_id;
            $user->update();

            // Traiter le code de parrainage
            $sponsorCode = $validated['sponsor_code'];
            $sponsorPack = UserPack::where('referral_code', $sponsorCode)->first();

            // Créer le wallet
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ]);

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
                'expiry_date' => now()->addMonths($validated['duration_months']),
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

            $user_pack = UserPack::where('user_id', $user->id)->where('pack_id', $pack->id)->where('referral_code', $referralCode)->first();

            // Distribuer les commissions
            $this->processCommissions($user_pack, $validated['duration_months']);
            
            DB::commit();

            // Envoyer l'email de vérification avec les informations supplémentaires
            $user->notify(new VerifyEmailWithCredentials($pack_id, $validated['duration_months'], $plainPassword, $referralCode, $referralLink));

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur lors de l\'inscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription'
            ], 500);
        }
    }

    private function processCommissions(UserPack $user_pack, $duration_months)
    {
        $commissionService = new CommissionService();
        $commissionService->distributeCommissions($user_pack, $duration_months);
    }

    public function validateReferralCode(Request $request)
    {
        $code = $request->input('code');
        return response()->json([
            'valid' => ReferralCodeService::isValidCode($code)
        ]);
    }

    /**
     * Estimation de conversion en USD si le service de conversion échoue
     * 
     * @param float $amount Montant à convertir
     * @param string $currency Devise d'origine
     * @return float Montant estimé en USD
     */
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
            // Essayer d'utiliser l'API de conversion de devise
            $response = \Http::get('https://api.exchangerate-api.com/v4/latest/USD');
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['rates'][$currency])) {
                    $rate = 1 / $data['rates'][$currency];
                    return $amount * $rate;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Erreur lors de l\'appel à l\'API de conversion: ' . $e->getMessage());
        }
        
        // Si l'API échoue, utiliser l'estimation
        return $this->estimateUSDAmount($amount, $currency);
    }
}