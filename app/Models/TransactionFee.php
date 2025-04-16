<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        'provider',
        'transfer_fee_percentage',
        'withdrawal_fee_percentage',
        'purchase_fee_percentage',
        'min_fee_amount',
        'max_fee_amount',
        'currency',
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
        'purchase_fee_percentage' => 'float',
        'min_fee_amount' => 'float',
        'max_fee_amount' => 'float',
        'is_active' => 'boolean',
        'last_api_update' => 'datetime',
        'api_response' => 'array'
    ];

    /**
     * Récupère les frais de transaction pour un moyen de paiement spécifique.
     *
     * @param string $paymentMethod
     * @param string|null $countryCode
     * @return TransactionFee|null
     */
    public static function getFeesForPaymentMethod(string $paymentMethod)
    {
        return self::where('payment_method', $paymentMethod)
                  ->where('is_active', true)
                  ->first();
    }

    /**
     * Calcule les frais de transfert pour un montant donné.
     *
     * @param float $amount
     * @return float
     */
    public function calculateTransferFee(float $amount): float
    {
        $fee = $amount * ($this->transfer_fee_percentage / 100);
        
        // Appliquer le montant minimum des frais
        if ($fee < $this->min_fee_amount) {
            $fee = $this->min_fee_amount;
        }
        
        // Appliquer le montant maximum des frais si défini
        if ($this->max_fee_amount && $fee > $this->max_fee_amount) {
            $fee = $this->max_fee_amount;
        }
        
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
        
        // Appliquer le montant minimum des frais
        if ($fee < $this->min_fee_amount) {
            $fee = $this->min_fee_amount;
        }
        
        // Appliquer le montant maximum des frais si défini
        if ($this->max_fee_amount && $fee > $this->max_fee_amount) {
            $fee = $this->max_fee_amount;
        }
        
        return round($fee, 2);
    }

    /**
     * Calcule les frais d'achat pour un montant donné.
     *
     * @param float $amount
     * @return float
     */
    public function calculatePurchaseFee(float $amount): float
    {
        $fee = $amount * ($this->purchase_fee_percentage / 100);
        
        // Appliquer le montant minimum des frais
        if ($fee < $this->min_fee_amount) {
            $fee = $this->min_fee_amount;
        }
        
        // Appliquer le montant maximum des frais si défini
        if ($this->max_fee_amount && $fee > $this->max_fee_amount) {
            $fee = $this->max_fee_amount;
        }
        
        return round($fee, 2);
    }

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
                        'provider' => $method['provider'],
                        'country_code' => $method['country_code'] ?? null
                    ],
                    [
                        'transfer_fee_percentage' => $method['transfer_fee_percentage'] ?? 0,
                        'withdrawal_fee_percentage' => $method['withdrawal_fee_percentage'] ?? 0,
                        'purchase_fee_percentage' => $method['purchase_fee_percentage'] ?? 0,
                        'min_fee_amount' => $method['min_fee_amount'] ?? 0,
                        'max_fee_amount' => $method['max_fee_amount'] ?? null,
                        'currency' => $method['currency'] ?? 'CDF',
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
}
