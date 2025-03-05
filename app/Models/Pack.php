<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'status',
        'avantages',
        'formations',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'status' => 'boolean',
        'avantages' => 'array',
    ];

    // Relation avec les utilisateurs qui ont acheté ce pack
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_packs')
                    ->withTimestamps()
                    ->withPivot([
                        'status',
                        'purchase_date',
                        'referral_prefix',
                        'referral_pack_name',
                        'referral_letter',
                        'referral_number',
                        'referral_code',
                        'sponsor_id',
                        'payment_status'
                    ]);
    }

    // Relation avec les taux de commission
    public function commissionRates()
    {
        return $this->hasMany(CommissionRate::class);
    }


    /**
     * Obtient le taux de commission pour un niveau donné
     * @param int $level Niveau de la génération
     * @param bool $reverse Si true, utilise les taux réversifs
     * @return float
     */
    public function getCommissionRate(int $level): float
    {
        $rate = $this->commissionRates()
            ->where('level', $level)
            ->first();

        return $rate ? $rate->rate : 0.0;
    }

    /**
     * Calcule la commission pour un montant et un niveau donnés
     * @param float $amount Montant de base
     * @param int $level Niveau de la génération
     * @param bool $reverse Si true, utilise les taux réversifs
     * @return float
     */
    public function calculateCommission(float $amount, int $level): float
    {
        $rate = $this->getCommissionRate($level);
        return round($amount * ($rate / 100), 2);
    }

    // Accesseur pour s'assurer que les avantages sont toujours un tableau
    public function getAvantagesAttribute($value)
    {
        if (is_null($value)) {
            return [];
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return is_array($value) ? $value : [];
    }

    // Mutateur pour s'assurer que les avantages sont stockés en JSON
    public function setAvantagesAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['avantages'] = json_encode([]);
        } else if (is_string($value)) {
            // Vérifie si c'est déjà du JSON valide
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->attributes['avantages'] = $value;
            } else {
                $this->attributes['avantages'] = json_encode([$value]);
            }
        } else if (is_array($value)) {
            $this->attributes['avantages'] = json_encode($value);
        } else {
            $this->attributes['avantages'] = json_encode([$value]);
        }
    }

    // Scope pour les packs actifs
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
} 