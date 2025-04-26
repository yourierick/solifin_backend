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
    protected $signature = 'solifin:process-bonus-points {frequency? : Fréquence spécifique à traiter (daily, weekly, monthly, yearly)}';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Traite l\'attribution des points bonus selon la fréquence spécifiée ou toutes les fréquences';

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
                if (!in_array($frequency, ['daily', 'weekly', 'monthly', 'yearly'])) {
                    $this->error("Fréquence invalide: $frequency. Utilisez daily, weekly, monthly ou yearly.");
                    return Command::FAILURE;
                }
                
                $this->info("Traitement des points bonus pour la fréquence: $frequency");
                $stats = $this->bonusPointsService->processBonusPointsByFrequency($frequency);
            } else {
                // Déterminer les fréquences à traiter en fonction du jour
                $today = Carbon::now();
                $frequencies = ['daily']; // Toujours traiter les bonus journaliers
                
                // Si on est lundi, traiter les bonus hebdomadaires
                if ($today->dayOfWeek === 1) { // 1 = Lundi
                    $frequencies[] = 'weekly';
                }
                
                // Si on est le premier jour du mois, traiter les bonus mensuels
                if ($today->day === 1) {
                    $frequencies[] = 'monthly';
                }
                
                // Si on est le premier jour de l'année, traiter les bonus annuels
                if ($today->day === 1 && $today->month === 1) {
                    $frequencies[] = 'yearly';
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
            
            $this->info('Traitement terminé avec succès.');
            $this->info("Utilisateurs traités: {$stats['users_processed']}");
            $this->info("Points attribués: {$stats['points_attributed']}");
            
            if ($stats['errors'] > 0) {
                $this->warn("Erreurs rencontrées: {$stats['errors']}");
                if (isset($stats['error_message'])) {
                    $this->warn("Message d'erreur: {$stats['error_message']}");
                }
            }
            
            // Journaliser les résultats
            Log::info('Attribution des points bonus terminée', $stats);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Une erreur est survenue lors du traitement des points bonus: ' . $e->getMessage());
            Log::error('Erreur lors de l\'attribution des points bonus: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}
