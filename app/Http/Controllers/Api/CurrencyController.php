<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyController extends Controller
{
    /**
     * Convertir un montant d'une devise à une autre
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function convert(Request $request)
    {
        // Log la requête entrante pour le débogage
        Log::info('Requête de conversion reçue', [
            'request_data' => $request->all()
        ]);

        // Valider les données d'entrée avec des règles plus souples
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'from' => 'required|string|max:3',
            'to' => 'required|string|max:3',
        ]);

        if ($validator->fails()) {
            Log::error('Validation échouée pour la conversion de devise', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Données invalides pour la conversion',
                'errors' => $validator->errors()
            ], 400);
        }

        $amount = $request->amount;
        $from = strtoupper($request->from);
        $to = strtoupper($request->to);

        // Si les devises sont identiques, pas besoin de conversion
        if ($from === $to) {
            return response()->json([
                'success' => true,
                'convertedAmount' => $amount,
                'from' => $from,
                'to' => $to,
                'rate' => 1
            ]);
        }

        try {
            // Récupérer les taux de conversion depuis l'API externe
            $conversionRates = $this->getConversionRates($from);
            
            if (!isset($conversionRates[$to])) {
                Log::error("Taux de conversion non disponible pour la paire {$from}/{$to}", [
                    'available_rates' => array_keys($conversionRates)
                ]);
                return response()->json([
                    'success' => false,
                    'message' => "Taux de conversion non disponible pour la devise {$to}"
                ], 400);
            }

            $rate = $conversionRates[$to];
            $convertedAmount = $amount * $rate;

            // Arrondir à 2 décimales
            $convertedAmount = round($convertedAmount, 2);

            Log::info("Conversion réussie: {$amount} {$from} = {$convertedAmount} {$to} (taux: {$rate})");

            return response()->json([
                'success' => true,
                'convertedAmount' => $convertedAmount,
                'from' => $from,
                'to' => $to,
                'rate' => $rate
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur de conversion de devise: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la conversion de devise: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les taux de conversion pour une devise de base
     * en utilisant une API externe gratuite
     *
     * @param string $baseCurrency
     * @return array
     */
    private function getConversionRates($baseCurrency)
    {
        try {
            // Utilisation de l'API ExchangeRate-API (version gratuite)
            $response = Http::get('https://open.er-api.com/v6/latest/' . $baseCurrency);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Vérifier si la réponse contient les taux de change
                if (isset($data['rates'])) {
                    Log::info('Taux de conversion récupérés avec succès pour ' . $baseCurrency);
                    return $data['rates'];
                }
            }
            
            // En cas d'échec, on log l'erreur
            Log::error('Échec de récupération des taux de conversion: ' . $response->body());
            
            // Fallback sur les taux fixes en cas d'échec de l'API
            return $this->getFixedRates($baseCurrency);
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération des taux de conversion: ' . $e->getMessage());
            
            // Fallback sur les taux fixes en cas d'exception
            return $this->getFixedRates($baseCurrency);
        }
    }
    
    /**
     * Taux de conversion fixes à utiliser en cas d'échec de l'API
     *
     * @param string $baseCurrency
     * @return array
     */
    private function getFixedRates($baseCurrency)
    {
        $rates = [
            'USD' => [
                'EUR' => 0.91,
                'XOF' => 602.5,
                'CFA' => 602.5,
            ],
            'EUR' => [
                'USD' => 1.10,
                'XOF' => 655.957,
                'CFA' => 655.957,
            ],
            'XOF' => [
                'USD' => 0.00166,
                'EUR' => 0.00152,
                'CFA' => 1,
            ],
            'CFA' => [
                'USD' => 0.00166,
                'EUR' => 0.00152,
                'XOF' => 1,
            ],
        ];

        return $rates[$baseCurrency] ?? [];
    }
}
