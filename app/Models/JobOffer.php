<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class JobOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'company',
        'location',
        'contract_type',
        'salary_min',
        'salary_max',
        'requirements',
        'deadline',
        'contact_email',
        'contact_phone',
        'url',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'deadline' => 'date',
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
        'requirements' => 'array',
    ];

    // Types de contrats disponibles
    const CONTRACT_TYPES = [
        'CDI' => 'Contrat à Durée Indéterminée',
        'CDD' => 'Contrat à Durée Déterminée',
        'STAGE' => 'Stage',
        'ALTERNANCE' => 'Alternance',
        'FREELANCE' => 'Freelance',
    ];

    // Scope pour les offres actives
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true)
            ->where(function ($query) {
                $query->whereNull('deadline')
                    ->orWhere('deadline', '>=', now());
            });
    }

    // Scope pour les offres expirées
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('deadline', '<', now());
    }

    // Scope pour filtrer par type de contrat
    public function scopeByContractType(Builder $query, string $type): Builder
    {
        return $query->where('contract_type', $type);
    }

    // Scope pour filtrer par fourchette de salaire
    public function scopeBySalaryRange(Builder $query, float $min, ?float $max = null): Builder
    {
        $query->where('salary_min', '>=', $min);
        
        if ($max) {
            $query->where('salary_max', '<=', $max);
        }

        return $query;
    }

    // Vérifie si l'offre est active
    public function isActive(): bool
    {
        return $this->status && ($this->deadline === null || $this->deadline >= now());
    }

    // Obtenir la fourchette de salaire formatée
    public function getSalaryRangeAttribute(): string
    {
        if (!$this->salary_min && !$this->salary_max) {
            return 'Non précisé';
        }

        if (!$this->salary_max) {
            return 'À partir de ' . number_format($this->salary_min, 2) . ' $';
        }

        if ($this->salary_min === $this->salary_max) {
            return number_format($this->salary_min, 2) . ' $';
        }

        return 'De ' . number_format($this->salary_min, 2) . ' $ à ' . number_format($this->salary_max, 2) . ' $';
    }
} 