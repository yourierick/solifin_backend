<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

class CheckCountryAccess
{
    /**
     * Liste des pays de test avec leurs IPs pour faciliter les tests en local
     */
    private $testCountries = [
        'FR' => '82.65.123.45',    // France
        'US' => '104.28.86.53',    // États-Unis
        'DE' => '91.198.174.192',  // Allemagne
        'GB' => '51.36.108.45',    // Royaume-Uni
        'CI' => '154.68.45.136',   // Côte d'Ivoire
        'SN' => '41.82.123.45',    // Sénégal
        'CM' => '41.202.207.1',    // Cameroun
        'CD' => '41.243.11.130',   // République Démocratique du Congo
    ];

    /**
     * Vérifie si l'accès est autorisé en fonction du pays de l'utilisateur.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Vérifier si les restrictions par pays sont activées
        $isRestrictionEnabled = Setting::where('key', 'enable_country_restrictions')->first();
        
        if (!$isRestrictionEnabled || $isRestrictionEnabled->value !== '1') {
            return $next($request);
        }

        // Récupérer les paramètres de pays
        $countrySettings = Setting::where('key', 'country_restrictions')->first();
        $countries = [];
        
        if ($countrySettings) {
            $countries = json_decode($countrySettings->value, true) ?: [];
        }

        // Si aucun pays n'est configuré, autoriser l'accès
        if (empty($countries)) {
            return $next($request);
        }

        // Récupérer l'adresse IP du client
        $clientIp = $request->ip();
        $testCountryCode = $request->header('X-Test-Country');
        
        // Pour les tests, permettre de simuler un pays via l'en-tête HTTP
        if ($testCountryCode && env('APP_ENV') !== 'production') {
            // Si un code de pays de test est fourni, utiliser l'IP correspondante
            if (array_key_exists($testCountryCode, $this->testCountries)) {
                $clientIp = $this->testCountries[$testCountryCode];
                Log::info("Test de pays: Simulation de l'IP {$clientIp} pour le pays {$testCountryCode}");
            } else {
                Log::warning("Code de pays de test invalide: {$testCountryCode}");
            }
        }
        
        // Pour les environnements de développement, autoriser toujours l'accès sauf si on simule un pays
        if (($clientIp === '127.0.0.1' || $clientIp === '::1' || env('APP_ENV') === 'local') && !$testCountryCode) {
            return $next($request);
        }

        // Déterminer le pays à partir de l'adresse IP
        $userCountry = $this->getCountryFromIp($clientIp);
        
        // Pour les tests, si un code de pays est fourni, l'utiliser directement
        if ($testCountryCode && env('APP_ENV') !== 'production') {
            $userCountry = $testCountryCode;
            Log::info("Test de pays: Utilisation directe du code pays {$userCountry}");
        }
        
        // Si le pays n'a pas pu être déterminé, autoriser l'accès par défaut
        if (!$userCountry) {
            Log::warning("Impossible de déterminer le pays pour l'IP: {$clientIp}");
            return $next($request);
        }

        // Vérifier si le pays est dans la liste et s'il est autorisé
        $countryConfig = collect($countries)->firstWhere('code', $userCountry);
        
        if ($countryConfig && $countryConfig['is_allowed']) {
            return $next($request);
        }

        // Si le pays n'est pas dans la liste, vérifier s'il y a des pays autorisés
        $hasAllowedCountries = collect($countries)->contains('is_allowed', true);
        
        // S'il n'y a aucun pays autorisé, bloquer l'accès
        if (!$hasAllowedCountries) {
            return $next($request);
        }

        // Si le pays n'est pas dans la liste mais qu'il y a des pays autorisés, bloquer l'accès
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé depuis votre pays',
            'country_code' => $userCountry,
            'access_denied' => true
        ], 403);
    }

    /**
     * Détermine le pays à partir de l'adresse IP.
     *
     * @param  string  $ip
     * @return string|null
     */
    private function getCountryFromIp($ip)
    {
        try {
            // Pour les tests, vérifier si l'IP correspond à un pays de test
            foreach ($this->testCountries as $countryCode => $testIp) {
                if ($ip === $testIp) {
                    Log::info("Test de pays: IP {$ip} identifiée comme {$countryCode}");
                    return $countryCode;
                }
            }
            
            // Utiliser un service de géolocalisation d'IP
            // Pour cet exemple, nous utilisons l'API ipinfo.io
            $response = file_get_contents("https://ipinfo.io/{$ip}/json");
            $data = json_decode($response, true);
            
            return $data['country'] ?? null;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la géolocalisation de l'IP {$ip}: " . $e->getMessage());
            return null;
        }
    }
}
