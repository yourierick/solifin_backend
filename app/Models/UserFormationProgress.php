<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFormationProgress extends Model
{
    use HasFactory;

    /**
     * Le nom de la table associée au modèle.
     *
     * @var string
     */
    protected $table = 'user_formation_progress';

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'formation_id',
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
     * Obtenir la formation associée à cette progression.
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * Mettre à jour le pourcentage de progression en fonction des modules complétés.
     */
    public function updateProgressPercentage(): void
    {
        $formation = $this->formation;
        $totalModules = $formation->modules()->count();
        
        if ($totalModules === 0) {
            $this->progress_percentage = 0;
            $this->save();
            return;
        }

        $completedModules = UserModuleProgress::where('user_id', $this->user_id)
            ->whereIn('formation_module_id', $formation->modules()->pluck('id'))
            ->where('is_completed', true)
            ->count();

        $this->progress_percentage = ($completedModules / $totalModules) * 100;
        
        // Si tous les modules sont complétés, marquer la formation comme complétée
        if ($this->progress_percentage >= 100) {
            $this->is_completed = true;
            $this->completed_at = now();
            $this->progress_percentage = 100; // S'assurer que le pourcentage ne dépasse pas 100
        }

        $this->save();
    }
}
