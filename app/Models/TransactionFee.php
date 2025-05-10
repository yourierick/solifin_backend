<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ExchangeRates;
use Carbon\Carbon;

class TransactionFee extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array
     */
    protected $fillable = [
        'payment_method',
        'payment_type',
        'transfer_fee_percentage',
        'withdrawal_fee_percentage',
        'fee_fixed',
        'fee_cap',
        'is_active',
        'last_api_update',
        'api_response'
    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'transfer_fee_percentage' => 'float',
        'withdrawal_fee_percentage' => 'float',
        'fee_fixed' => 'float',
        'fee_cap' => 'float',
        'is_active' => 'boolean',
        'last_api_update' => 'datetime',
        'api_response' => 'array'
    ];

    /**
     * Récupère les frais de transaction pour un moyen de paiement spécifique.
     *
     * @param string $paymentMethod
     * @param string|null $paymentType
     * @return TransactionFee|null
     */
    public static function getFeesForPaymentMethod(string $paymentMethod, string $paymentType = null)
    {
        $query = self::where('payment_method', $paymentMethod)
                    ->where('is_active', true);
        
        if ($paymentType) {
            $query->where('payment_type', $paymentType);
        }
        
        return $query->first();
    }

    /**
     * Calcule les frais de transfert pour un montant donné.
     *
     * @param float $amount
     * @return float
     */
    public function calculateTransferFee(float $amount, $currency): float
    {
        //Convertir le montant fixe des frais dans la dévise de l'utilisateur lorsque ce n'est pas le USD
        // if ($currency !== "USD") {
        //     $this->fee_fixed = $this->convertFromUSD($this->fee_fixed, $currency);
        // }

        $fee = $amount * ($this->transfer_fee_percentage / 100);
        
        // // Appliquer le montant minimum des frais
        // if ($fee < $this->fee_fixed) {
        //     $fee = $this->fee_fixed;
        // }
        
        // // Appliquer le montant maximum des frais si défini
        // if ($this->fee_cap && $fee > $this->fee_cap) {
        //     $fee = $this->fee_cap;
        //}
        
        return round($fee, 2);
    }

    /**
     * Calcule les frais de retrait pour un montant donné.
     *
     * @param float $amount
     * @return float
     */
    public function calculateWithdrawalFee(float $amount): float
    {
        $fee = $amount * ($this->withdrawal_fee_percentage / 100);
        
        // // Appliquer le montant minimum des frais
        // if ($fee < $this->fee_fixed) {
        //     $fee = $this->fee_fixed;
        // }
        
        // // Appliquer le montant maximum des frais si défini
        // if ($this->fee_cap && $fee > $this->fee_cap) {
        //     $fee = $this->fee_cap;
        // }
        
        return round($fee, 2);
    }

    /**
     * Calcule les frais d'achat pour un montant donné.
     *
     * @param float $amount
     * @return float
     */
    // public function calculatePurchaseFee(float $amount): float
    // {
    //     $fee = $amount * ($this->purchase_fee_percentage / 100);
        
    //     // Appliquer le montant minimum des frais
    //     if ($fee < $this->min_fee_amount) {
    //         $fee = $this->min_fee_amount;
    //     }
        
    //     // Appliquer le montant maximum des frais si défini
    //     if ($this->max_fee_amount && $fee > $this->max_fee_amount) {
    //         $fee = $this->max_fee_amount;
    //     }
        
    //     return round($fee, 2);
    // }

    /**
     * Met à jour les frais de transaction depuis l'API externe.
     *
     * @return bool
     */
    public static function updateFeesFromApi(): bool
    {
        try {
            // URL de l'API à définir dans le fichier .env
            $apiUrl = config('services.transaction_fees.api_url');
            $apiKey = config('services.transaction_fees.api_key');
            
            if (!$apiUrl) {
                Log::error('URL de l\'API des frais de transaction non définie dans la configuration');
                return false;
            }
            
            // Effectuer la requête API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json'
            ])->get($apiUrl);
            
            if (!$response->successful()) {
                Log::error('Erreur lors de la récupération des frais de transaction depuis l\'API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }
            
            $data = $response->json();
            
            // Traiter les données et mettre à jour la base de données
            foreach ($data['payment_methods'] ?? [] as $method) {
                self::updateOrCreate(
                    [
                        'payment_method' => $method['name'],
                        'payment_type' => $method['type'],
                    ],
                    [
                        'transfer_fee_percentage' => $method['transfer_fee_percentage'] ?? 0,
                        'withdrawal_fee_percentage' => $method['withdrawal_fee_percentage'] ?? 0,
                        'fee_fixed' => $method['fee_fixed'] ?? 0,
                        'fee_cap' => $method['fee_cap'] ?? null,
                        'is_active' => $method['is_active'] ?? true,
                        'last_api_update' => now(),
                        'api_response' => $method
                    ]
                );
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Exception lors de la mise à jour des frais de transaction', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }


    /**
     * Convertit un montant depuis la devise USD vers une autre devise.
     *
     * @param float $amount
     * @param string $currency
     * @return float
     */
    private function convertFromUSD($amount, $currency)
    {
        if ($currency === 'USD') {
            return $amount;
        }
        
        try {
            // Récupérer le taux de conversion depuis la BD
            $exchangeRate = ExchangeRates::where('currency', "USD")->where("target_currency", $currency)->first();
            if ($exchangeRate) {
                return $amount * $exchangeRate->rate;
            }
        } catch (\Exception $e) {
            \Log::error('Erreur lors de l\'appel à l\'API de conversion: ' . $e->getMessage());
        }
        return $amount;
    }
}
