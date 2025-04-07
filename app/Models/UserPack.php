<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPack extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pack_id',
        'sponsor_id',
        'referral_prefix',
        'referral_pack_name',
        'referral_letter',
        'referral_number',
        'referral_code',
        'link_referral',
        'payment_status',
        'status',
        'purchase_date',
        'expiry_date'
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relation avec le pack
    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }

    // Relation avec le parrain
    public function sponsor()
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    // Relation avec les filleuls (utilisateurs parrainés)
    public function referrals()
    {
        return $this->hasMany(UserPack::class, 'sponsor_id', 'user_id')
            ->where('pack_id', $this->pack_id);
    }

    // Scope pour les packs payés
    public function scopeCompleted($query)
    {
        return $query->where('payment_status', 'completed');
    }

    // Scope pour les packs en attente de paiement
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    // Scope pour les packs dont le paiement a échoué
    public function scopeFailed($query)
    {
        return $query->where('payment_status', 'failed');
    }
}
