<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserJetonEsengoHistory extends Model
{
    use HasFactory;

    /**
     * Les types d'actions possibles sur un jeton Esengo
     */
    const ACTION_ATTRIBUTION = 'attribution';
    const ACTION_UTILISATION = 'utilisation';
    const ACTION_EXPIRATION = 'expiration';

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'jeton_id',
        'cadeau_id',
        'code_unique',
        'action_type',
        'description',
        'metadata',
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Relation avec l'utilisateur concerné par l'action.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec le jeton concerné par l'action.
     *
     * @return BelongsTo
     */
    public function jeton(): BelongsTo
    {
        return $this->belongsTo(UserJetonEsengo::class, 'jeton_id');
    }

    /**
     * Relation avec le cadeau obtenu (si applicable).
     *
     * @return BelongsTo
     */
    public function cadeau(): BelongsTo
    {
        return $this->belongsTo(Cadeau::class);
    }

    /**
     * Enregistre une nouvelle entrée d'historique pour l'attribution d'un jeton.
     *
     * @param UserJetonEsengo $jeton Le jeton attribué
     * @param string $description Description de l'attribution
     * @param array $metadata Métadonnées additionnelles
     * @return UserJetonEsengoHistory
     */
    public static function logAttribution(UserJetonEsengo $jeton, string $description, array $metadata = []): self
    {
        return self::create([
            'user_id' => $jeton->user_id,
            'jeton_id' => $jeton->id,
            'code_unique' => $jeton->code_unique,
            'action_type' => self::ACTION_ATTRIBUTION,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Enregistre une nouvelle entrée d'historique pour l'utilisation d'un jeton.
     *
     * @param UserJetonEsengo $jeton Le jeton utilisé
     * @param Cadeau|null $cadeau Le cadeau obtenu (si applicable)
     * @param string $description Description de l'utilisation
     * @param array $metadata Métadonnées additionnelles
     * @return UserJetonEsengoHistory
     */
    public static function logUtilisation(UserJetonEsengo $jeton, ?Cadeau $cadeau, string $description, array $metadata = []): self
    {
        return self::create([
            'user_id' => $jeton->user_id,
            'jeton_id' => $jeton->id,
            'cadeau_id' => $cadeau ? $cadeau->id : null,
            'code_unique' => $jeton->code_unique,
            'action_type' => self::ACTION_UTILISATION,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Enregistre une nouvelle entrée d'historique pour l'expiration d'un jeton.
     *
     * @param UserJetonEsengo $jeton Le jeton expiré
     * @param string $description Description de l'expiration
     * @param array $metadata Métadonnées additionnelles
     * @return UserJetonEsengoHistory
     */
    public static function logExpiration(UserJetonEsengo $jeton, string $description, array $metadata = []): self
    {
        return self::create([
            'user_id' => $jeton->user_id,
            'jeton_id' => $jeton->id,
            'code_unique' => $jeton->code_unique,
            'action_type' => self::ACTION_EXPIRATION,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
