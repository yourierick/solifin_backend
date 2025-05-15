<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExchangeRatesController extends Controller
{
    /**
     * Les devises supportées par l'application
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
     * Récupère tous les taux de change
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $rates = ExchangeRates::all(['id', 'currency', 'target_currency', 'rate', 'last_api_update']);
            
            // Organiser les taux par devise source
            $organizedRates = [];
            foreach ($this->currencies as $currency) {
                $organizedRates[$currency] = $rates->filter(function ($rate) use ($currency) {
                    return $rate->currency === $currency;
                })->values();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'rates' => $organizedRates,
                    'currencies' => $this->currencies,
                    'last_update' => $rates->max('last_api_update')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des taux de change', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des taux de change',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour les taux de change depuis l'API externe
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            // Récupérer la devise de base (USD par défaut)
            $baseCurrency = $request->input('base_currency', 'USD');
            
            if (!in_array($baseCurrency, $this->currencies)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Devise de base non supportée'
                ], 400);
            }
            
            // Supprimer d'abord toutes les entrées existantes pour éviter d'avoir des devises non supportées
            ExchangeRates::truncate();
            
            // Mettre à jour les taux pour la devise de base
            $success = ExchangeRates::updateRatesFromApi($baseCurrency, $this->currencies);
            
            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => "Échec de la mise à jour des taux de change pour {$baseCurrency}"
                ], 500);
            }
            
            // Pour les autres devises de la liste, si elles sont différentes de la devise de base
            $updatedCurrencies = [$baseCurrency];
            $failedCurrencies = [];
            
            foreach ($this->currencies as $currency) {
                if ($currency !== $baseCurrency) {
                    $currencySuccess = ExchangeRates::updateRatesFromApi($currency, $this->currencies);
                    
                    if ($currencySuccess) {
                        $updatedCurrencies[] = $currency;
                    } else {
                        $failedCurrencies[] = $currency;
                    }
                }
            }
            
            // Vérifier que toutes les paires de devises nécessaires sont disponibles
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
            
            // Essayer de calculer les paires manquantes via USD
            $calculatedPairs = [];
            $failedCalculations = [];
            
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
                    
                    $calculatedPairs[] = "{$from} -> {$to}";
                } else {
                    $failedCalculations[] = "{$from} -> {$to}";
                }
            }
            
            // Récupérer les taux mis à jour
            $rates = ExchangeRates::all(['id', 'currency', 'target_currency', 'rate', 'last_api_update']);
            
            // Organiser les taux par devise source
            $organizedRates = [];
            foreach ($this->currencies as $currency) {
                $organizedRates[$currency] = $rates->filter(function ($rate) use ($currency) {
                    return $rate->currency === $currency;
                })->values();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'rates' => $organizedRates,
                    'currencies' => $this->currencies,
                    'last_update' => now(),
                    'updated_currencies' => $updatedCurrencies,
                    'failed_currencies' => $failedCurrencies,
                    'calculated_pairs' => $calculatedPairs,
                    'failed_calculations' => $failedCalculations
                ],
                'message' => 'Taux de change mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour des taux de change', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour des taux de change',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
