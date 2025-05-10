<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReferralInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_pack_id',
        'email',
        'name',
        'invitation_code',
        'channel',
        'status',
        'sent_at',
        'opened_at',
        'registered_at',
        'expires_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'registered_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Relation avec l'utilisateur qui a envoyé l'invitation
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec le pack utilisé pour l'invitation
     */
    public function userPack()
    {
        return $this->belongsTo(UserPack::class, 'user_pack_id');
    }

    /**
     * Génère un code d'invitation unique
     *
     * @return string
     */
    public static function generateInvitationCode(): string
    {
        $code = 'INV-' . strtoupper(Str::random(8));
        
        // Vérifier que le code est unique
        while (self::where('invitation_code', $code)->exists()) {
            $code = 'INV-' . strtoupper(Str::random(8));
        }
        
        return $code;
    }

    /**
     * Scope pour les invitations en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope pour les invitations envoyées
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope pour les invitations ouvertes
     */
    public function scopeOpened($query)
    {
        return $query->where('status', 'opened');
    }

    /**
     * Scope pour les invitations qui ont abouti à une inscription
     */
    public function scopeRegistered($query)
    {
        return $query->where('status', 'registered');
    }

    /**
     * Scope pour les invitations expirées
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }
}
