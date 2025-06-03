<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\JetonEsengoSeeder;
use Illuminate\Support\Facades\Log;

class SeedJetonsEsengo extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'solifin:seed-jetons-esengo';

    /**
     * La description de la commande console.
     *
     * @var string
     */
    protected $description = 'Initialise des jetons Esengo de test pour les utilisateurs existants';

    /**
     * Exécute la commande console.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Initialisation des jetons Esengo de test...');
        
        try {
            $seeder = new JetonEsengoSeeder();
            $seeder->setCommand($this);
            $seeder->run();
            
            $this->info('Initialisation des jetons Esengo terminée avec succès.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur lors de l\'initialisation des jetons Esengo: ' . $e->getMessage());
            Log::error('Erreur lors de l\'initialisation des jetons Esengo: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
