<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class UserJetonEsengo extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'pack_id',
        'code_unique',
        'is_used',
        'date_expiration',
        'date_utilisation',
        'metadata',
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array
     */
    protected $casts = [
        'is_used' => 'boolean',
        'date_expiration' => 'datetime',
        'date_utilisation' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Relation avec l'utilisateur propriétaire du jeton.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec le pack associé au jeton.
     *
     * @return BelongsTo
     */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }

    /**
     * Relation avec l'historique des actions sur ce jeton.
     *
     * @return HasMany
     */
    public function history(): HasMany
    {
        return $this->hasMany(UserJetonEsengoHistory::class, 'jeton_id');
    }

    /**
     * Vérifie si le jeton est expiré.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->date_expiration) {
            return false;
        }

        return $this->date_expiration->isPast();
    }

    /**
     * Vérifie si le jeton est utilisable (non utilisé et non expiré).
     *
     * @return bool
     */
    public function isUsable(): bool
    {
        return !$this->is_used && !$this->isExpired();
    }

    /**
     * Marque le jeton comme utilisé.
     *
     * @param int|null $cadeauId ID du cadeau obtenu avec ce jeton
     * @return bool
     */
    public function markAsUsed(?int $cadeauId = null): bool
    {
        if ($this->is_used || $this->isExpired()) {
            return false;
        }

        $this->is_used = true;
        $this->date_utilisation = Carbon::now();
        
        // Ajouter l'ID du cadeau aux métadonnées si fourni
        if ($cadeauId) {
            $metadata = $this->metadata ?: [];
            $metadata['cadeau_id'] = $cadeauId;
            $this->metadata = $metadata;
        }

        return $this->save();
    }

    /**
     * Génère un code unique pour un jeton.
     *
     * @param int $userId ID de l'utilisateur
     * @return string
     */
    public static function generateUniqueCode(int $userId): string
    {
        $prefix = 'ESG';
        $userPart = str_pad($userId, 5, '0', STR_PAD_LEFT);
        $randomPart = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $timestamp = Carbon::now()->format('ymdHi');
        
        return $prefix . $userPart . $randomPart . $timestamp;
    }
}
