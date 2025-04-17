<?php

namespace App\Console\Commands;

use App\Models\ExchangeRates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange:update {--base=USD : Devise de base pour les taux de change}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Met à jour les taux de change des devises depuis une API externe';

    /**
     * Les devises à mettre à jour (basées sur config.js)
     * 
     * @var array
     */
    protected $currencies = [
        'USD', // Dollar américain (devise de base)
        'EUR', // Euro
        'XOF', // Franc CFA BCEAO
        'XAF', // Franc CFA BEAC
        'CDF', // Franc Congolais
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $baseCurrency = $this->option('base');
        
        $this->info("Mise à jour des taux de change avec {$baseCurrency} comme devise de base...");
        
        try {
            // Supprimer d'abord toutes les entrées existantes pour éviter d'avoir des devises non supportées
            $this->info("Suppression des taux de change existants...");
            ExchangeRates::truncate();
            
            // Mettre à jour les taux pour la devise de base
            $success = ExchangeRates::updateRatesFromApi($baseCurrency, $this->currencies);
            
            if (!$success) {
                $this->error("Échec de la mise à jour des taux de change pour {$baseCurrency}");
                return 1;
            }
            
            $this->info("Taux de change pour {$baseCurrency} mis à jour avec succès.");
            
            // Pour les autres devises de la liste, si elles sont différentes de la devise de base
            foreach ($this->currencies as $currency) {
                if ($currency !== $baseCurrency) {
                    $this->info("Récupération des taux de change pour {$currency}...");
                    $success = ExchangeRates::updateRatesFromApi($currency, $this->currencies);
                    
                    if ($success) {
                        $this->info("Taux de change pour {$currency} mis à jour avec succès.");
                    } else {
                        $this->warn("Échec de la mise à jour des taux de change pour {$currency}");
                    }
                }
            }
            
            // Vérifier que toutes les paires de devises nécessaires sont disponibles
            $this->info("Vérification des paires de devises...");
            $missingPairs = [];
            
            foreach ($this->currencies as $from) {
                foreach ($this->currencies as $to) {
                    if ($from !== $to) {
                        $rate = ExchangeRates::getRateForCurrency($from, $to);
                        
                        if ($rate === null) {
                            $missingPairs[] = "{$from} -> {$to}";
                        }
                    }
                }
            }
            
            if (count($missingPairs) > 0) {
                $this->warn("Paires de devises manquantes: " . implode(', ', $missingPairs));
                
                // Essayer de calculer les paires manquantes via USD
                $this->info("Tentative de calcul des paires manquantes via USD...");
                
                foreach ($missingPairs as $pair) {
                    list($from, $to) = explode(' -> ', $pair);
                    
                    $fromToUsd = ExchangeRates::getRateForCurrency($from, 'USD');
                    $usdToTo = ExchangeRates::getRateForCurrency('USD', $to);
                    
                    if ($fromToUsd !== null && $usdToTo !== null) {
                        $calculatedRate = $fromToUsd * $usdToTo;
                        
                        ExchangeRates::updateOrCreate(
                            [
                                'currency' => $from,
                                'target_currency' => $to
                            ],
                            [
                                'rate' => $calculatedRate,
                                'last_api_update' => now(),
                                'api_response' => [
                                    'source' => 'calculated',
                                    'from_to_usd' => $fromToUsd,
                                    'usd_to_to' => $usdToTo
                                ]
                            ]
                        );
                        
                        $this->info("Paire {$from} -> {$to} calculée avec succès (taux: {$calculatedRate})");
                    } else {
                        $this->error("Impossible de calculer la paire {$from} -> {$to}");
                    }
                }
            }
            
            // Afficher un résumé des taux de change
            $this->info("\nRésumé des taux de change :");
            $this->table(
                ['De', 'Vers', 'Taux'],
                ExchangeRates::all(['currency', 'target_currency', 'rate'])->map(function ($rate) {
                    return [
                        $rate->currency,
                        $rate->target_currency,
                        number_format($rate->rate, 6)
                    ];
                })->toArray()
            );
            
            $this->info("Mise à jour des taux de change terminée.");
            return 0;
        } catch (\Exception $e) {
            $this->error("Une erreur est survenue: " . $e->getMessage());
            Log::error("Erreur lors de la mise à jour des taux de change", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
