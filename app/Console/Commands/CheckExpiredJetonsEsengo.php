<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserJetonEsengo;
use App\Models\UserJetonEsengoHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckExpiredJetonsEsengo extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'solifin:check-expired-jetons-esengo';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Vérifie et marque les jetons Esengo expirés';

    /**
     * Exécute la commande console.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Vérification des jetons Esengo expirés...');
        
        try {
            // Récupérer tous les jetons expirés mais non marqués comme utilisés
            $expiredJetons = UserJetonEsengo::where('is_used', false)
                ->where('date_expiration', '<', Carbon::now())
                ->get();
            
            if ($expiredJetons->isEmpty()) {
                $this->info('Aucun jeton Esengo expiré trouvé.');
                return Command::SUCCESS;
            }
            
            $this->info('Nombre de jetons expirés trouvés: ' . $expiredJetons->count());
            $processedCount = 0;
            
            DB::beginTransaction();
            
            try {
                foreach ($expiredJetons as $jeton) {
                    // Enregistrer l'expiration dans l'historique
                    UserJetonEsengoHistory::logExpiration(
                        $jeton,
                        'Jeton expiré automatiquement par le système',
                        [
                            'expired_at' => $jeton->date_expiration->format('Y-m-d H:i:s'),
                            'checked_at' => Carbon::now()->format('Y-m-d H:i:s')
                        ]
                    );
                    
                    $processedCount++;
                }
                
                DB::commit();
                $this->info("Traitement terminé. $processedCount jetons expirés ont été traités.");
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur lors de la vérification des jetons expirés: ' . $e->getMessage());
            Log::error('Erreur lors de la vérification des jetons expirés: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
