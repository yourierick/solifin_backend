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

        foreach ($publicites as $publicite) {
            $dateCreation = Carbon::parse($publicite->created_at);
            $dateExpiration = $dateCreation->addDays($publicite->duree_affichage);

            if ($now->gt($dateExpiration)) {
                $publicite->statut = 'expiré';
                $publicite->save();
                $count++;
            }
        }

        $this->info("$count publicités ont été marquées comme expirées.");
    }

    /**
     * Mettre à jour le statut des offres d'emploi expirées
     */
    private function updateOffreEmploiStatus()
    {
        // Récupérer les offres d'emploi dont la date limite est passée
        $offres = OffreEmploi::where('statut', 'approuvé')
            ->where('etat', 'disponible')
            ->whereNotNull('date_limite')
            ->where('date_limite', '<', Carbon::now()->toDateString())
            ->get();

        $count = $offres->count();

        foreach ($offres as $offre) {
            $offre->statut = 'expiré';
            $offre->save();
        }

        $this->info("$count offres d'emploi ont été marquées comme expirées.");
    }

    /**
     * Mettre à jour le statut des opportunités d'affaires expirées
     */
    private function updateOpportuniteAffaireStatus()
    {
        // Récupérer les opportunités d'affaires dont la date limite est passée
        $opportunites = OpportuniteAffaire::where('statut', 'approuvé')
            ->where('etat', 'disponible')
            ->whereNotNull('date_limite')
            ->where('date_limite', '<', Carbon::now()->toDateString())
            ->get();

        $count = $opportunites->count();

        foreach ($opportunites as $opportunite) {
            $opportunite->statut = 'expiré';
            $opportunite->save();
        }

        $this->info("$count opportunités d'affaires ont été marquées comme expirées.");
    }
}
