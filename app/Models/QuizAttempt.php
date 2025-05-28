<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'module_id',
        'answers',
        'score',
        'total_questions',
        'completed_at'
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'answers' => 'array',
        'score' => 'integer',
        'total_questions' => 'integer',
        'completed_at' => 'datetime'
    ];

    /**
     * Obtenir l'utilisateur qui a fait cette tentative.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtenir le module (quiz) associé à cette tentative.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(FormationModule::class, 'module_id');
    }

    /**
     * Calculer le pourcentage de réussite.
     */
    public function getPercentageAttribute(): float
    {
        if ($this->total_questions === 0) {
            return 0;
        }
        
        return round(($this->score / $this->total_questions) * 100, 2);
    }
}
