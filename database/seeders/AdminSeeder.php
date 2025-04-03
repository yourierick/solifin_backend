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

        // Attribuer tous les packs à l'administrateur
        Pack::chunk(100, function ($packs) use ($admin) {
            foreach ($packs as $pack) {
                UserPack::create([
                    'user_id' => $admin->id,
                    'pack_id' => $pack->id,
                    'referral_prefix' => 'SPR',
                    'referral_pack_name' => $pack->name,
                    'referral_letter' => 'A',
                    'referral_number' => 1,
                    'referral_code' => sprintf("SPR-%s-A-0001", strtoupper(substr($pack->name, 0, 3))),
                    'payment_status' => 'completed',
                    'purchase_date' => now()
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