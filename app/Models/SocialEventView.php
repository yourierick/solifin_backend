<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialEventView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'social_event_id',
        'viewed_at'
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    /**
     * Obtenir l'utilisateur qui a vu cet événement social.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtenir l'événement social qui a été vu.
     */
    public function socialEvent(): BelongsTo
    {
        return $this->belongsTo(SocialEvent::class);
    }
}
