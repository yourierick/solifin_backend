<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\UserPack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormationModule extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'formation_id',
        'title',
        'description',
        'content',
        'type',
        'video_url',
        'file_url',
        'duration',
        'order',
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'duration' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Obtenir la formation à laquelle appartient ce module.
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * Obtenir les packs qui ont accès à ce module.
     */
    public function packs(): BelongsToMany
    {
        return $this->belongsToMany(Pack::class, 'module_pack')
                    ->withTimestamps();
    }

    /**
     * Obtenir les progressions des utilisateurs pour ce module.
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserModuleProgress::class);
    }

    // Les scopes pour les statuts des modules ont été supprimés car le statut est maintenant géré uniquement au niveau de la formation

    /**
     * Vérifier si un utilisateur a accès à ce module via ses packs.
     */
    // public function isAccessibleByUser(User $user): bool
    // {
    //     // Si la formation est créée par l'utilisateur, il a accès à tous ses modules
    //     if ($this->formation->created_by === $user->id) {
    //         return true;
    //     }

    //     // Si la formation est payante et l'utilisateur l'a achetée
    //     if ($this->formation->is_paid && $this->formation->purchasers()->where('user_id', $user->id)->exists()) {
    //         return true;
    //     }

    //     // Si l'utilisateur a un pack qui donne accès à ce module spécifique
    //     $userpack = UserPack::where('user_id', $user->id)->get();
    //     $userPackIds = $userpack->where('status', "active")
    //                         ->where('payment_status', 'completed')
    //                         ->pluck('pack_id')
    //                         ->toArray();

    //     // Vérifier l'accès via la formation (si aucune restriction spécifique au module)
    //     if (!$this->packs()->exists() && $this->formation->packs()->whereIn('packs.id', $userPackIds)->exists()) {
    //         return true;
    //     }

    //     return false;
    // }
}
