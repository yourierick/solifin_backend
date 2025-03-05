<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class BusinessOpportunitie extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'sector',
        'investment_min',
        'investment_max',
        'requirements',
        'benefits',
        'location',
        'deadline',
        'contact_email',
        'url',
        'contact_phone',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'deadline' => 'date',
        'investment_min' => 'decimal:2',
        'investment_max' => 'decimal:2',
        'requirements' => 'array',
        'benefits' => 'array',
    ];

    // Secteurs d'activité disponibles
    const SECTORS = [
        'TECH' => 'Technologies',
        'RETAIL' => 'Commerce',
        'SERVICES' => 'Services',
        'FOOD' => 'Restauration',
        'HEALTH' => 'Santé',
        'EDUCATION' => 'Éducation',
        'REAL_ESTATE' => 'Immobilier',
        'OTHER' => 'Autre',
    ];

    // Scope pour les opportunités actives
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true)
            ->where(function ($query) {
                $query->whereNull('deadline')
                    ->orWhere('deadline', '>=', now());
            });
    }

    // Scope pour les opportunités expirées
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('deadline', '<', now());
    }

    // Scope pour filtrer par secteur
    public function scopeBySector(Builder $query, string $sector): Builder
    {
        return $query->where('sector', $sector);
    }

    // Scope pour filtrer par fourchette d'investissement
    public function scopeByInvestmentRange(Builder $query, float $min, ?float $max = null): Builder
    {
        $query->where('investment_min', '>=', $min);
        
        if ($max) {
            $query->where('investment_max', '<=', $max);
        }

        return $query;
    }

    // Vérifie si l'opportunité est active
    public function isActive(): bool
    {
        return $this->status && ($this->deadline === null || $this->deadline >= now());
    }

    // Obtenir la fourchette d'investissement formatée
    public function getInvestmentRangeAttribute(): string
    {
        if (!$this->investment_min && !$this->investment_max) {
            return 'Non précisé';
        }

        if (!$this->investment_max) {
            return 'À partir de ' . number_format($this->investment_min, 2) . ' $';
        }

        if ($this->investment_min === $this->investment_max) {
            return number_format($this->investment_min, 2) . ' $';
        }

        return 'De ' . number_format($this->investment_min, 2) . ' $ à ' . number_format($this->investment_max, 2) . ' $';
    }

    // Obtenir le nom du secteur
    public function getSectorNameAttribute(): string
    {
        return self::SECTORS[$this->sector] ?? $this->sector;
    }
} 