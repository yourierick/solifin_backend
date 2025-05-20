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
        'status',
        'featured', //Pour mettre en avant un témoignage
    ];

    protected $casts = [
        'rating' => 'integer',
        'featured' => 'boolean',
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope pour les témoignages actifs/approuvés
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    // Scope pour les témoignages en attente de modération
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
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
} 