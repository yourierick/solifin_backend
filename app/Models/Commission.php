<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'user_id',
        'source_user_id',
        'pack_id',
        'amount',
        'level',
        'status',
    ];

    public function sponsor_user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function source_user()
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function pack()
    {
        return $this->belongsTo(Pack::class, 'pack_id');
    }
}
