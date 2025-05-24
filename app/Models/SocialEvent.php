<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'user_id',
        'image',
        'video',
        'description',
        'statut',
        'raison_rejet'
    ];

    /**
     * Obtenir la page associée à cet événement social.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Obtenir les likes associés à cet événement social.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(SocialEventLike::class);
    }

    /**
     * Obtenir les vues associées à cet événement social.
     */
    public function views(): HasMany
    {
        return $this->hasMany(SocialEventView::class);
    }
    
    /**
     * Obtenir le nombre de vues uniques pour cet événement social.
     */
    public function getViewsCountAttribute()
    {
        return $this->views()->count();
    }
    
    /**
     * Obtenir les signalements associés à cet événement social.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(SocialEventReport::class);
    }

    /**
     * Vérifier si un utilisateur a aimé cet événement social.
     */
    public function isLikedByUser($userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    /**
     * Obtenir le nombre de likes pour cet événement social.
     */
    public function getLikesCount(): int
    {
        return $this->likes()->count();
    }

    /**
     * Obtenir le nombre de partages pour cet événement social.
     */
    public function getSharesCount(): int
    {
        return $this->shares()->count();
    }
}
