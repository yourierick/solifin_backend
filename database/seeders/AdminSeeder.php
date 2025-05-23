<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletSystem;
use App\Models\Pack;
use App\Models\UserPack;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Créer l'administrateur
        $admin = User::create([
            'name' => 'Mukuta Bitangalo Erick',
            'account_id' => '00-CPT-01',
            'sexe' => 'homme',
            'pays' => 'CD',
            'province' => 'SudKivu',
            'ville' => 'Bukavu',
            'address' => '1 Rue de la Paix, 75001 Bukavu',
            'status' => 'active',
            'email' => 'admin@solifin.com',
            'password' => Hash::make('admin123'),
            'is_admin' => true,
            'email_verified_at' => now(),
            'phone' => '+243813728334',
        ]);

        // Créer le wallet pour l'administrateur
        Wallet::create([
            'user_id' => $admin->id,
            'balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        // Récupérer l'URL du frontend depuis le fichier .env
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        
        // Attribuer tous les packs à l'administrateur
        Pack::chunk(100, function ($packs) use ($admin) {
            foreach ($packs as $pack) {
                $referralLetter = substr($pack->name, 0, 1);
                $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $referralCode = 'SPR' . $referralLetter . $referralNumber;

                // Vérifier que le code est unique
                while (UserPack::where('referral_code', $referralCode)->exists()) {
                    $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $referralCode = 'SPR' . $referralLetter . $referralNumber;
                }

                // Créer le lien de parrainage en utilisant l'URL du frontend
                $referralLink = $frontendUrl . "/register?referral_code=" . $referralCode;
                UserPack::create([
                    'user_id' => $admin->id,
                    'pack_id' => $pack->id,
                    'referral_prefix' => 'SPR',
                    'referral_pack_name' => $pack->name,
                    'referral_letter' => $referralLetter,
                    'referral_number' => $referralNumber,
                    'referral_code' => $referralCode,
                    'link_referral' => $referralLink,
                    'status' => 'active',
                    'purchase_date' => now(),
                    'expiry_date' => null,
                    'payment_status' => 'completed',
                    'is_admin_pack' => true,
                ]);
            }
        });

        // WalletSystem::create([
        //     'balance' => 0,
        //     'total_in' => 0,
        //     'total_out' => 0,
        // ]);
    }
}