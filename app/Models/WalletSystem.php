<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WalletSystem extends Model
{
    protected $fillable = [
        'balance',
        'total_in',
        'total_out',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_in' => 'decimal:2',
        'total_out' => 'decimal:2',
    ];

    /**
     * Ajouter des fonds au wallet système
     */
    public function addFunds(float $amount, string $type, string $status, array $metadata)
    {
        return DB::transaction(function () use ($amount, $type, $status, $metadata) {
            $this->balance += $amount;
            $this->total_in += $amount;
            $this->save();

            //\Log::info($this->id);

            return WalletSystemTransaction::create([
                'wallet_system_id' => $this->id,
                'amount' => $amount,
                'type' => $type,
                'status' => $status,
                'metadata' => $metadata
            ]);
        });
    }

    /**
     * Retire des fonds du wallet système
     */
    public function deductFunds(float $amount, string $type, $status, ?array $metadata = null)
    {
        return DB::transaction(function () use ($amount, $type, $status, $metadata) {
            if ($this->balance < $amount) {
                throw new \Exception('Fonds insuffisants dans le portefeuille système');
            }

            $this->balance -= $amount;
            $this->total_out += $amount;
            $this->save();

            return WalletSystemTransaction::create([
                'wallet_system_id' => $this->id,
                'amount' => $amount,
                'type' => $type,
                'status' => $status,
                'metadata' => $metadata
            ]);
        });
    }

    /**
     * Relation avec les transactions
     */
    public function transactions()
    {
        return $this->hasMany(WalletSystemsTransaction::class);
    }
}
