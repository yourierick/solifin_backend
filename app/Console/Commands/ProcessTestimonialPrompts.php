<?php

namespace App\Console\Commands;

use App\Models\TestimonialPrompt;
use App\Services\TestimonialPromptService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessTestimonialPrompts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'testimonials:process-prompts {--batch=100 : Nombre d\'utilisateurs à traiter par lot} {--expire : Marquer les invitations expirées}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vérifie et crée des invitations à témoigner pour les utilisateurs éligibles';

    /**
     * Le service de gestion des invitations à témoigner.
     *
     * @var \App\Services\TestimonialPromptService
     */
    protected $testimonialPromptService;

    /**
     * Crée une nouvelle instance de la commande.
     *
     * @param \App\Services\TestimonialPromptService $testimonialPromptService
     * @return void
     */
    public function __construct(TestimonialPromptService $testimonialPromptService)
    {
        parent::__construct();
        $this->testimonialPromptService = $testimonialPromptService;
    }

    /**
     * Exécute la commande console.
     *
     * @return int Code de sortie
     */
    public function handle(): int
    {
        $this->info('Démarrage du traitement des invitations à témoigner...');
        
        // Marquer les invitations expirées si l'option est spécifiée
        if ($this->option('expire')) {
            $expiredCount = $this->testimonialPromptService->expireOldPrompts();
            $this->info("{$expiredCount} invitations ont été marquées comme expirées.");
        }
        
        // Récupérer la taille du lot depuis les options
        $batchSize = (int) $this->option('batch');
        
        // Traiter les utilisateurs éligibles
        $startTime = microtime(true);
        $stats = $this->testimonialPromptService->processEligibleUsers($batchSize);
        $duration = round(microtime(true) - $startTime, 2);
        
        // Afficher les statistiques
        $this->info('Traitement terminé en ' . $duration . ' secondes.');
        $this->table(
            ['Utilisateurs traités', 'Invitations créées', 'Utilisateurs ignorés', 'Erreurs'],
            [[$stats['processed'], $stats['created'], $stats['skipped'], $stats['errors']]]
        );
        
        // Journaliser les résultats
        Log::info('Traitement des invitations à témoigner terminé', [
            'duration' => $duration,
            'stats' => $stats,
        ]);
        
        // Afficher les statistiques globales
        $this->newLine();
        $this->info('Statistiques globales :');
        $pendingCount = TestimonialPrompt::pending()->count();
        $displayedCount = TestimonialPrompt::displayed()->count();
        $submittedCount = TestimonialPrompt::submitted()->count();
        $declinedCount = TestimonialPrompt::declined()->count();
        $expiredCount = TestimonialPrompt::expired()->count();
        
        $this->table(
            ['En attente', 'Affichées', 'Soumises', 'Déclinées', 'Expirées'],
            [[$pendingCount, $displayedCount, $submittedCount, $declinedCount, $expiredCount]]
        );
        
        return Command::SUCCESS;
    }
}
