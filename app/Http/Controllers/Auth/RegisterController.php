<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPack;
use App\Models\Wallet;
use App\Models\WalletSystem;
use App\Models\Pack;
use App\Models\Page;
use App\Models\ReferralInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\ReferralCodeService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\CommissionService;
use App\Models\ExchangeRates;
use App\Notifications\VerifyEmailWithCredentials;
use App\Notifications\ReferralInvitationConverted;
use Carbon\Carbon;
use App\Models\Setting;


class RegisterController extends Controller
{
    public function register(Request $request, $pack_id)
    {
        try {
            // Valider les données
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'address' => 'required|string',
                'phone' => 'required|string',
                'whatsapp' => 'nullable|string',
                'sponsor_code' => 'required|exists:user_packs,referral_code',
                'invitation_code' => 'nullable|string',
                'duration_months' => 'required|integer|min:1',
                'payment_method' => 'required|string',
                'payment_type' => 'required|string',
                'payment_details' => 'required|array',
                'gender' => 'required|string',
                'country' => 'required|string',
                'province' => 'required|string',
                'city' => 'required|string',
                'currency' => 'nullable|string|max:3',
                'amount' => 'required|numeric',
                'fees' => 'required|numeric',
                'phoneNumber' => 'nullable|string',
                'acquisition_source' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $pack = Pack::find($pack_id);

            // Récupérer les informations de paiement
            $paymentMethod = $validated['payment_method']; // Méthode spécifique (visa, m-pesa, etc.)
            $paymentType = $validated['payment_type']; // Type général (credit-card, mobile-money, etc.)
            $paymentAmount = $validated['amount']; // Montant sans les frais
            $paymentCurrency = $validated['currency'];
            $paymentDetails = $validated['payment_details'] ?? [];

            // Normaliser le nom de la méthode de paiement si c'est wallet
            if ($paymentMethod === 'wallet') {
                $paymentMethod = 'solifin-wallet';
            }

            // Recalculer les frais globaux de transaction
            $globalFeePercentage = (float) Setting::getValue('transfer_fee_percentage', 0);
            $globalfees = ((float)$paymentAmount) * ($globalFeePercentage / 100);

            // Recalculer les frais de transaction côté backend pour éviter les manipulations côté frontend
            // Récupérer les frais de transaction pour cette méthode de paiement spécifique
            $transactionFeeModel = \App\Models\TransactionFee::where('payment_method', $paymentMethod)
                                                           ->where('is_active', true);
            
            $transactionFee = $transactionFeeModel->first();
            
            // Calculer les frais de transaction spécifiques à la méthode de paiement
            $specificfees = 0;
            if ($transactionFee) {
                $specificfees = $transactionFee->calculateTransferFee((float) $paymentAmount, $paymentCurrency);
            }
            
            // Montant total incluant les frais
            $totalAmount = $paymentAmount + $globalfees;
            
            // Si la devise n'est pas en USD, convertir le montant en USD (devise de base)
            $amountInUSD = $totalAmount;
            if ($paymentCurrency !== 'USD') {
                try {
                    // Appel à un service de conversion de devise
                    $amountInUSD = $this->convertToUSD($totalAmount, $paymentCurrency);
                    $amountInUSD = round($amountInUSD, 2);
                    $globalfees = $this->convertToUSD($globalfees, $paymentCurrency);
                    $globalfees = round($globalfees, 2);
                    $specificfees = $this->convertToUSD($specificfees, $paymentCurrency);
                    $specificfees = round($specificfees, 2);
                } catch (\Exception $e) {
                    return response()->json([
                        "success" => false, 
                        "message" => "Erreur lors de la conversion de la dévise, veuillez utiliser le $"
                    ]);
                }
            }
            
           // Soustraire les frais de transaction s'ils sont inclus dans le montant
           $AmountInUSDWithoutSpecificFees = round($amountInUSD - $specificfees, 2);//Montant total à payer sans les frais de transaction spécifiques au moyen de paiement choisi
           $netAmountInUSD = round($amountInUSD - $globalfees, 0);//Montant total à payer sans les frais de transaction
            
            // Vérifier que le montant net est suffisant pour couvrir le coût du pack
            $packCost = $pack->price * $validated['duration_months'];

            if ($netAmountInUSD < $packCost) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant payé est insuffisant pour couvrir le coût du pack'
                ], 400);
            }

            //Logique de paiement api à implémenter

            // Enregistrer le paiement dans le système
            $walletsystem = WalletSystem::first();
            if (!$walletsystem) {
                $walletsystem = WalletSystem::create([
                    "balance" => 0,
                    "total_in" => 0,
                    "total_out" => 0,
                ]);
            }
            $walletsystem->addFunds($AmountInUSDWithoutSpecificFees, "sales", "completed", [
                "user" => $validated["name"], 
                "pack_id" => $pack->id, 
                "Détails de paiement" => $validated['payment_details'],
                "Méthode de paiement" => $paymentMethod,
                "Type de paiement" => $paymentType,
                "Nom du pack" => $pack->name, 
                "Code sponsor" => $validated['sponsor_code'], 
                "Duration" => $validated['duration_months'],
                "Montant Original" => $paymentAmount,
                "Dévise Originale" => $paymentCurrency,
                "Frais globaux" => $globalfees,
                "Frais spécifiques" => $specificfees,
                "Montant net" => $netAmountInUSD,
            ]);

            // Stocker le mot de passe en clair temporairement pour l'email
            $plainPassword = $validated['password'];
            
            // Créer l'utilisateur
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($plainPassword),
                'sexe' => $validated['gender'],
                'address' => $validated['address'],
                'phone' => $validated['phone'],
                'whatsapp' => $validated['whatsapp'] ?? null,
                'pays' => $validated['country'],
                'province' => $validated['province'],
                'ville' => $validated['city'],
                'status' => 'active',
                'is_admin' => false,
                'acquisition_source' => $validated['acquisition_source'] ?? null,
                'pack_de_publication_id' => $pack->id,
            ]);

            $user->account_id = '00-CPT-'.$user->id;
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

            // Créer la page
            Page::create([
                'user_id' => $user->id,
                'nombre_abonnes' => 0,
                'nombre_likes' => 0,
                'photo_de_couverture' => null,
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
            
            // Mettre à jour le statut de l'invitation si un code d'invitation a été fourni
            if (!empty($validated['invitation_code'])) {
                $this->updateInvitationStatus($validated['invitation_code'], $user->id);
            }
            
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
            \Log::error($e->getTraceAsString());
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
    // private function estimateUSDAmount($amount, $currency)
    // {
    //     // Taux de conversion approximatifs (à mettre à jour régulièrement)
    //     $rates = [
    //         'EUR' => 1.09,
    //         'GBP' => 1.27,
    //         'CAD' => 0.73,
    //         'AUD' => 0.66,
    //         'JPY' => 0.0067,
    //         'CHF' => 1.12,
    //         'CNY' => 0.14,
    //         'INR' => 0.012,
    //         'BRL' => 0.19,
    //         'ZAR' => 0.054,
    //         'NGN' => 0.00065,
    //         'GHS' => 0.071,
    //         'XOF' => 0.0017,
    //         'XAF' => 0.0017,
    //         'CDF' => 0.0017,
    //     ];
        
    //     if (isset($rates[$currency])) {
    //         return $amount * $rates[$currency];
    //     }
        
    //     // Si la devise n'est pas dans la liste, utiliser un taux par défaut
    //     //\Log::warning("Devise non reconnue pour la conversion: $currency. Utilisation du taux par défaut.");
    //     return $amount;
    // }
    
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
    }
    
    /**
     * Met à jour le statut d'une invitation après l'inscription d'un utilisateur
     * 
     * @param string $invitationCode Code de l'invitation
     * @param int $userId ID de l'utilisateur qui s'est inscrit
     * @return void
     */
    private function updateInvitationStatus($invitationCode, $userId)
    {
        try {
            $invitation = ReferralInvitation::where('invitation_code', $invitationCode)
                ->whereIn('status', ['pending', 'sent', 'opened'])
                ->first();
                
            if ($invitation) {
                $invitation->status = 'registered';
                $invitation->registered_at = Carbon::now();
                $invitation->save();
                
                // Récupérer le propriétaire de l'invitation et l'utilisateur nouvellement inscrit
                $invitation_owner = $invitation->user;
                $new_user = User::find($userId);
                
                // Envoyer une notification au propriétaire de l'invitation
                if ($invitation_owner && $new_user) {
                    $invitation_owner->notify(new ReferralInvitationConverted($invitation, $new_user));
                }
            }
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la mise à jour du statut de l\'invitation: ' . $e->getMessage());
        }
    }
}