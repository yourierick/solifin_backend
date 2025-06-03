<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserPack;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formation extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'category',
        'description',
        'thumbnail',
        'status',
        'type',
        'created_by',
        'is_paid',
        'price',
        'currency',
        'rejection_reason',
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_paid' => 'boolean',
        'price' => 'decimal:2',
    ];

    /**
     * Obtenir l'utilisateur qui a créé la formation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Obtenir les modules de la formation.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(FormationModule::class)->orderBy('order');
    }

    /**
     * Obtenir les packs qui ont accès à cette formation.
     */
    public function packs(): BelongsToMany
    {
        return $this->belongsToMany(Pack::class, 'formation_pack')
                    ->withTimestamps();
    }

    /**
     * Obtenir les utilisateurs qui ont acheté cette formation.
     */
    public function purchasers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'formation_purchases')
                    ->withPivot(['amount_paid', 'currency', 'payment_status', 'purchased_at'])
                    ->withTimestamps();
    }

    /**
     * Obtenir les progressions des utilisateurs pour cette formation.
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserFormationProgress::class);
    }

    /**
     * Scope pour les formations publiées.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope pour les formations en attente de validation.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope pour les formations créées par les administrateurs.
     */
    public function scopeAdmin($query)
    {
        return $query->where('type', 'admin');
    }

    /**
     * Scope pour les formations créées par les utilisateurs.
     */
    public function scopeUser($query)
    {
        return $query->where('type', 'user');
    }

    /**
     * Vérifier si un utilisateur a accès à cette formation via ses packs.
     */
    public function isAccessibleByUser(User $user): bool
    {
        // Si la formation est créée par l'utilisateur, il y a accès
        if ($this->created_by === $user->id) {
            return true;
        }

        // Si la formation est payante et l'utilisateur l'a achetée
        if ($this->is_paid && $this->purchasers()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if (!$this->is_paid) {
            return true;
        }

        // Si l'utilisateur a un pack qui donne accès à cette formation
        $userpack = UserPack::where('user_id', $user->id)->get();
        $userPackIds = $userpack->where('status', 'active')
                            ->where('payment_status', 'completed')
                            ->pluck('pack_id')
                            ->toArray();

        return $this->packs()->whereIn('packs.id', $userPackIds)->exists();
    }
}
