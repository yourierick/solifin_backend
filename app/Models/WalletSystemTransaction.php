<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletSystemTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_system_id',
        'amount',
        'type',
        'status',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function walletSystem()
    {
        return $this->belongsTo(WalletSystem::class);
    }
}
