<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserPack;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckPackExpiration extends Command
{
    protected $signature = 'packs:check-expiration';
    protected $description = 'Vérifie et met à jour le statut des packs expirés';

    public function handle()
    {
        $this->info('Vérification des packs expirés...');

        try {
            // Récupérer tous les packs actifs qui sont expirés
            $expiredPacks = UserPack::where('status', 'active')
                ->where('is_admin_pack', false)
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<', Carbon::now())
                ->get();

            foreach ($expiredPacks as $userPack) {
                $userPack->update(['status' => 'expired']);
                
                Log::info("Pack expiré mis à jour", [
                    'user_id' => $userPack->user_id,
                    'pack_id' => $userPack->pack_id,
                    'expiry_date' => $userPack->expiry_date
                ]);
            }

            $this->info("Nombre de packs expirés mis à jour : " . $expiredPacks->count());

        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification des packs expirés: ' . $e->getMessage());
            $this->error('Une erreur est survenue lors de la vérification des packs expirés');
        }
    }
}
