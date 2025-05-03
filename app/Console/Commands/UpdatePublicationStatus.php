<?php

namespace App\Console\Commands;

use App\Models\Publicite;
use App\Models\OffreEmploi;
use App\Models\OpportuniteAffaire;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdatePublicationStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publications:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mettre à jour le statut des publications expirées';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->updatePubliciteStatus();
        $this->updateOffreEmploiStatus();
        $this->updateOpportuniteAffaireStatus();

        $this->info('Statut des publications mis à jour avec succès.');

        return Command::SUCCESS;
    }

    /**
     * Mettre à jour le statut des publicités expirées
     */
    private function updatePubliciteStatus()
    {
        // Récupérer les publicités qui ont dépassé leur durée d'affichage
        $publicites = Publicite::where('statut', 'approuvé')
            ->where('etat', 'disponible')
            ->whereNotNull('duree_affichage')
            ->get();

        $now = Carbon::now();
        $count = 0;
        $updatedCount = 0;

        foreach ($publicites as $publicite) {
            $dateCreation = Carbon::parse($publicite->created_at);
            $dateExpiration = $dateCreation->addDays($publicite->duree_affichage);

            if ($now->gt($dateExpiration)) {
                $publicite->statut = 'expiré';
                $publicite->duree_affichage = 0;
                $publicite->save();
                $count++;
            } else {
                // Calculer les jours restants
                $joursRestants = $now->diffInDays($dateExpiration, false);
                
                // Si la durée d'affichage est différente des jours restants, mettre à jour
                if ($publicite->duree_affichage != $joursRestants && $joursRestants >= 0) {
                    $publicite->duree_affichage = $joursRestants;
                    $publicite->save();
                    $updatedCount++;
                }
            }
        }

        $this->info("$count publicités ont expirées.");
        $this->info("$updatedCount publicités ont eu leur durée d'affichage mise à jour.");
    }

    /**
     * Mettre à jour le statut des offres d'emploi expirées
     */
    private function updateOffreEmploiStatus()
    {
        // Récupérer les offres d'emploi
        $offres = OffreEmploi::where('statut', 'approuvé')
            ->where('etat', 'disponible')
            ->get();

        $now = Carbon::now();
        $countDateLimite = 0;
        $countDureeAffichage = 0;
        $updatedCount = 0;

        foreach ($offres as $offre) {
            $isExpired = false;
            
            // Vérifier si la date limite est dépassée
            if ($offre->date_limite && Carbon::parse($offre->date_limite)->lt($now)) {
                $isExpired = true;
                $countDateLimite++;
            }
            
            // Vérifier si la durée d'affichage est dépassée
            if (!$isExpired && $offre->duree_affichage) {
                $dateCreation = Carbon::parse($offre->created_at);
                $dateExpiration = $dateCreation->addDays($offre->duree_affichage);
                
                if ($now->gt($dateExpiration)) {
                    $isExpired = true;
                    $countDureeAffichage++;
                } else {
                    // Calculer les jours restants
                    $joursRestants = $now->diffInDays($dateExpiration, false);
                    
                    // Si la durée d'affichage est différente des jours restants, mettre à jour
                    if ($offre->duree_affichage != $joursRestants && $joursRestants >= 0) {
                        $offre->duree_affichage = $joursRestants;
                        $offre->save();
                        $updatedCount++;
                    }
                }
            }
            
            // Marquer comme expiré si nécessaire
            if ($isExpired) {
                $offre->statut = 'expiré';
                $offre->duree_affichage = 0;
                $offre->save();
            }
        }

        $this->info("$countDateLimite offres d'emploi ont expiré (date limite).");
        $this->info("$countDureeAffichage offres d'emploi ont expiré (durée d'affichage).");
        $this->info("$updatedCount offres d'emploi ont eu leur durée d'affichage mise à jour.");
    }

    /**
     * Mettre à jour le statut des opportunités d'affaires expirées
     */
    private function updateOpportuniteAffaireStatus()
    {
        // Récupérer les opportunités d'affaires
        $opportunites = OpportuniteAffaire::where('statut', 'approuvé')
            ->where('etat', 'disponible')
            ->get();

        $now = Carbon::now();
        $countDateLimite = 0;
        $countDureeAffichage = 0;
        $updatedCount = 0;

        foreach ($opportunites as $opportunite) {
            $isExpired = false;
            
            // Vérifier si la date limite est dépassée
            if ($opportunite->date_limite && Carbon::parse($opportunite->date_limite)->lt($now)) {
                $isExpired = true;
                $countDateLimite++;
            }
            
            // Vérifier si la durée d'affichage est dépassée
            if (!$isExpired && $opportunite->duree_affichage) {
                $dateCreation = Carbon::parse($opportunite->created_at);
                $dateExpiration = $dateCreation->addDays($opportunite->duree_affichage);
                
                if ($now->gt($dateExpiration)) {
                    $isExpired = true;
                    $countDureeAffichage++;
                } else {
                    // Calculer les jours restants
                    $joursRestants = $now->diffInDays($dateExpiration, false);
                    
                    // Si la durée d'affichage est différente des jours restants, mettre à jour
                    if ($opportunite->duree_affichage != $joursRestants && $joursRestants >= 0) {
                        $opportunite->duree_affichage = $joursRestants;
                        $opportunite->save();
                        $updatedCount++;
                    }
                }
            }
            
            // Marquer comme expiré si nécessaire
            if ($isExpired) {
                $opportunite->statut = 'expiré';
                $opportunite->duree_affichage = 0;
                $opportunite->save();
            }
        }

        $this->info("$countDateLimite opportunités d'affaires ont expiré (date limite).");
        $this->info("$countDureeAffichage opportunités d'affaires ont expiré (durée d'affichage).");
        $this->info("$updatedCount opportunités d'affaires ont eu leur durée d'affichage mise à jour.");
    }
}
