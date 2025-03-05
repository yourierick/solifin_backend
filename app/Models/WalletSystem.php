<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    public function addFunds(float $amount, string $description, ?array $metadata = null)
    {
        return DB::transaction(function () use ($amount, $description, $metadata) {
            $this->balance += $amount;
            $this->total_in += $amount;
            $this->save();

            return WalletSystemTransaction::create([
                'system_wallet_id' => $this->id,
                'amount' => $amount,
                'type' => 'credit',
                'description' => $description,
                'metadata' => $metadata
            ]);
        });
    }

    /**
     * Retire des fonds du wallet système
     */
    public function deductFunds(float $amount, string $description, ?array $metadata = null)
    {
        return DB::transaction(function () use ($amount, $description, $metadata) {
            if ($this->balance < $amount) {
                throw new \Exception('Fonds insuffisants dans le wallet système');
            }

            $this->balance -= $amount;
            $this->total_out += $amount;
            $this->save();

            return WalletSystemsTransaction::create([
                'system_wallet_id' => $this->id,
                'amount' => $amount,
                'type' => 'debit',
                'description' => $description,
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
