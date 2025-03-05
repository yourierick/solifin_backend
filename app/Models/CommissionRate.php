<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'pack_id',
        'level',
        'rate',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
    ];

    // Relation avec le pack
    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }
} 