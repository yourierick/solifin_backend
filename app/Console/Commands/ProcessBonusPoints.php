<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BonusPointsService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Commande pour traiter l'attribution des points bonus
 * Cette commande peut être exécutée manuellement ou via le planificateur Laravel
 * Elle permet de traiter une fréquence spécifique ou toutes les fréquences selon le jour
 */
class ProcessBonusPoints extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'solifin:process-bonus-points {frequency? : Fréquence spécifique à traiter (weekly, monthly)}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Traite l\'attribution des points bonus selon la fréquence spécifiée';

    /**
     * Le service d'attribution des points bonus.
     *
     * @var \App\Services\BonusPointsService
     */
    protected $bonusPointsService;

    /**
     * Crée une nouvelle instance de commande.
     *
     * @param \App\Services\BonusPointsService $bonusPointsService
     * @return void
     */
    public function __construct(BonusPointsService $bonusPointsService)
    {
        parent::__construct();
        $this->bonusPointsService = $bonusPointsService;
    }

    /**
     * Exécute la commande console.
     *
     * @return int
     */
    public function handle()
    {
        $frequency = $this->argument('frequency');
        
        $this->info('Début du traitement des points bonus...');
        
        try {
            if ($frequency) {
                // Vérifier que la fréquence est valide
                if (!in_array($frequency, ['weekly', 'monthly'])) {
                    $this->error("Fréquence invalide: $frequency. Utilisez weekly ou monthly.");
                    return Command::FAILURE;
                }
                
                $this->info("Traitement des points bonus pour la fréquence: $frequency");
                $stats = $this->bonusPointsService->processBonusPointsByFrequency($frequency);
            } else {
                // Déterminer les fréquences à traiter en fonction du jour
                $today = Carbon::now();
                $frequencies = [];
                
                // Si on est lundi, traiter les bonus sur délais (hebdomadaires)
                if ($today->dayOfWeek === 1) { // 1 = Lundi
                    $frequencies[] = 'weekly'; // Pour les bonus sur délais
                    $this->info("Traitement des bonus sur délais (hebdomadaire)");
                }
                
                // Si on est le premier jour du mois, traiter les jetons Esengo (mensuels)
                if ($today->day === 1) {
                    $frequencies[] = 'monthly'; // Pour les jetons Esengo
                    $this->info("Traitement des jetons Esengo (mensuel)");
                }
                
                $stats = ['users_processed' => 0, 'points_attributed' => 0, 'errors' => 0];
                
                foreach ($frequencies as $freq) {
                    $this->info("Traitement des points bonus pour la fréquence: $freq");
                    $result = $this->bonusPointsService->processBonusPointsByFrequency($freq);
                    
                    if (isset($result['error_message'])) {
                        $this->error("Erreur lors du traitement de la fréquence $freq: {$result['error_message']}");
                    }
                    
                    $stats['users_processed'] += $result['users_processed'];
                    $stats['points_attributed'] += $result['points_attributed'];
                    $stats['errors'] += $result['errors'];
                }
            }
            
            $this->info("Traitement terminé. {$stats['users_processed']} utilisateurs traités, {$stats['points_attributed']} points attribués, {$stats['errors']} erreurs.");
            
            if ($stats['errors'] > 0) {
                return Command::FAILURE;
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur lors du traitement des points bonus: ' . $e->getMessage());
            Log::error('Erreur lors du traitement des points bonus: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
