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
            // Valider les données
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'address' => 'required|string',
                'phone' => 'required|string',
                'sponsor_code' => 'nullable|exists:user_packs,referral_code',
                'duration_months' => 'required|integer|min:1',
                'gender' => 'required|string',
                'country' => 'required|string',
                'province' => 'required|string',
                'city' => 'required|string',
            ]);

            DB::beginTransaction();

            $pack = Pack::find($pack_id);

            $total_paid = $pack->price * $validated['duration_months'];
            $walletsystem = WalletSystem::first();
            $walletsystem->addFunds($total_paid, "sales", "completed", ["user"=>$validated["name"], "pack_id"=>$pack->id, "pack_name"=>$pack->name, 
            "sponsor_code"=>$validated['sponsor_code'], "duration"=>$validated['duration_months']]);

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
}