<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRates extends Model
{
    protected $fillable = [
        'currency',
        'target_currency',
        'rate',
        'last_api_update',
        'api_response'
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'last_api_update' => 'datetime',
        'api_response' => 'array'
    ];

    /**
     * Récupère le taux de change pour une paire de devises
     *
     * @param string $currency Devise source
     * @param string $targetCurrency Devise cible
     * @return float|null Taux de change ou null si non trouvé
     */
    public static function getRateForCurrency(string $currency, string $targetCurrency): ?float
    {
        return self::where('currency', $currency)
                    ->where('target_currency', $targetCurrency)
                    ->first()
                    ?->rate;
    }

    /**
     * Met à jour les taux de change depuis l'API externe
     * 
     * @param string $baseCurrency Devise de base (USD par défaut)
     * @param array $targetCurrencies Devises cibles à récupérer
     * @return bool Succès ou échec de la mise à jour
     */
    public static function updateRatesFromApi(string $baseCurrency = 'USD', array $targetCurrencies = null): bool
    {
        try {
            // Devises supportées par l'application (basées sur config.js)
            $supportedCurrencies = $targetCurrencies ?? [
                'USD', // Dollar américain
                'EUR', // Euro
                'XOF', // Franc CFA BCEAO
                'XAF', // Franc CFA BEAC
                'CDF', // Franc Congolais
            ];
            
            $apiUrl = "https://open.er-api.com/v6/latest/{$baseCurrency}";
            
            Log::info("Récupération des taux de change pour {$baseCurrency} depuis {$apiUrl}");
            
            $response = Http::get($apiUrl);
            
            if (!$response->successful()) {
                Log::error('Erreur lors de la récupération des taux de change depuis l\'API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }
            
            $data = $response->json();
            
            if (!isset($data['rates']) || empty($data['rates'])) {
                Log::error('Aucun taux de change trouvé dans la réponse de l\'API', [
                    'response' => $data
                ]);
                return false;
            }
            
            $timestamp = now();
            $updatedCount = 0;
            
            // Filtrer uniquement les devises supportées
            $filteredRates = array_intersect_key($data['rates'], array_flip($supportedCurrencies));
            
            foreach ($filteredRates as $targetCurrency => $rate) {
                // Ne pas créer d'entrée pour la même devise
                if ($baseCurrency === $targetCurrency) {
                    continue;
                }
                
                // Mettre à jour ou créer l'entrée pour cette paire de devises
                self::updateOrCreate(
                    [
                        'currency' => $baseCurrency,
                        'target_currency' => $targetCurrency
                    ],
                    [
                        'rate' => $rate,
                        'last_api_update' => $timestamp,
                        'api_response' => [
                            'source' => 'open.er-api.com',
                            'timestamp' => $data['time_last_update_unix'] ?? null,
                            'base_code' => $data['base_code'] ?? $baseCurrency
                        ]
                    ]
                );
                
                // Calculer également le taux inverse
                if ($rate > 0) {
                    self::updateOrCreate(
                        [
                            'currency' => $targetCurrency,
                            'target_currency' => $baseCurrency
                        ],
                        [
                            'rate' => 1 / $rate,
                            'last_api_update' => $timestamp,
                            'api_response' => [
                                'source' => 'open.er-api.com',
                                'timestamp' => $data['time_last_update_unix'] ?? null,
                                'base_code' => $data['base_code'] ?? $baseCurrency,
                                'inverse_calculated' => true
                            ]
                        ]
                    );
                }
                
                $updatedCount += 2; // Compter la paire et son inverse
            }
            
            Log::info("Mise à jour réussie des taux de change: {$updatedCount} paires mises à jour");
            return true;
        } catch (\Exception $e) {
            Log::error('Exception lors de la mise à jour des taux de change', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Convertit un montant d'une devise à une autre
     *
     * @param float $amount Montant à convertir
     * @param string $fromCurrency Devise source
     * @param string $toCurrency Devise cible
     * @return float|null Montant converti ou null en cas d'échec
     */
    public static function convert(float $amount, string $fromCurrency, string $toCurrency): ?float
    {
        // Si les devises sont identiques, pas de conversion nécessaire
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }
        
        try {
            // Chercher un taux direct
            $rate = self::getRateForCurrency($fromCurrency, $toCurrency);
            
            // Si le taux direct n'existe pas, essayer de passer par USD comme intermédiaire
            if ($rate === null) {
                $fromToUsd = self::getRateForCurrency($fromCurrency, 'USD');
                $usdToTarget = self::getRateForCurrency('USD', $toCurrency);
                
                if ($fromToUsd !== null && $usdToTarget !== null) {
                    $rate = $fromToUsd * $usdToTarget;
                } else {
                    Log::warning("Taux de conversion non disponible pour {$fromCurrency} vers {$toCurrency}");
                    return null;
                }
            }
            
            return round($amount * $rate, 2);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la conversion de {$fromCurrency} vers {$toCurrency}", [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }
}
