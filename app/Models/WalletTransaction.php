<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'amount',
        'type',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relation avec le wallet
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    // Scope pour les crédits
    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    // Scope pour les débits
    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }
} 