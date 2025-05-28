<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserModuleProgress extends Model
{
    use HasFactory;

    /**
     * Le nom de la table associée au modèle.
     *
     * @var string
     */
    protected $table = 'user_module_progress';

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'formation_module_id',
        'is_completed',
        'progress_percentage',
        'started_at',
        'completed_at',
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_completed' => 'boolean',
        'progress_percentage' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Obtenir l'utilisateur associé à cette progression.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtenir le module associé à cette progression.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(FormationModule::class, 'formation_module_id');
    }

    /**
     * Marquer ce module comme complété et mettre à jour la progression de la formation.
     */
    public function markAsCompleted(): void
    {
        $this->is_completed = true;
        $this->progress_percentage = 100;
        $this->completed_at = now();
        $this->save();

        // Mettre à jour la progression de la formation
        $module = $this->module;
        $formationId = $module->formation_id;
        
        $formationProgress = UserFormationProgress::firstOrCreate(
            [
                'user_id' => $this->user_id,
                'formation_id' => $formationId,
            ],
            [
                'started_at' => now(),
            ]
        );

        $formationProgress->updateProgressPercentage();
    }
}
