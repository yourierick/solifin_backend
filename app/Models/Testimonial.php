<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content',
        'rating',
        'position',
        'company',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'rating' => 'integer',
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope pour les témoignages actifs/approuvés
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    // Scope pour les témoignages en attente de modération
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', false);
    }

    // Scope pour filtrer par note minimale
    public function scopeMinRating(Builder $query, int $rating): Builder
    {
        return $query->where('rating', '>=', $rating);
    }

    // Scope pour les témoignages avec une note
    public function scopeRated(Builder $query): Builder
    {
        return $query->whereNotNull('rating');
    }

    // Obtenir le nom de l'utilisateur
    public function getNameAttribute(): string
    {
        return $this->user ? $this->user->name : 'Anonyme';
    }

    // Obtenir l'avatar de l'utilisateur
    public function getAvatarAttribute(): string
    {
        if ($this->user && $this->user->avatar) {
            return $this->user->avatar;
        }
        
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name);
    }

    // Formater la note en étoiles
    public function getRatingStarsAttribute(): string
    {
        if (!$this->rating) {
            return '';
        }

        $stars = str_repeat('★', $this->rating);
        $emptyStars = str_repeat('☆', 5 - $this->rating);
        
        return $stars . $emptyStars;
    }

    // Obtenir le titre complet (position @ entreprise)
    public function getTitleAttribute(): string
    {
        if (!$this->position && !$this->company) {
            return '';
        }

        if (!$this->company) {
            return $this->position;
        }

        if (!$this->position) {
            return $this->company;
        }

        return $this->position . ' @ ' . $this->company;
    }
} 