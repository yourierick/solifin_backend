<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TestimonialPrompt extends Model
{
    use HasFactory;
    
    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'trigger_type',
        'trigger_data',
        'message',
        'status',
        'expires_at',
        'testimonial_id',
        'displayed_at',
        'clicked_at',
        'responded_at',
    ];
    
    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'trigger_data' => 'array',
        'expires_at' => 'datetime',
        'displayed_at' => 'datetime',
        'clicked_at' => 'datetime',
        'responded_at' => 'datetime',
    ];
    
    /**
     * Types de déclencheurs disponibles pour les invitations à témoigner.
     */
    const TRIGGER_EARNINGS = 'earnings';
    const TRIGGER_REFERRALS = 'referrals';
    const TRIGGER_MEMBERSHIP = 'membership_duration';
    const TRIGGER_PACK_UPGRADE = 'pack_upgrade';
    const TRIGGER_WITHDRAWAL = 'first_withdrawal';
    const TRIGGER_BONUS = 'bonus_received';
    
    /**
     * Relation avec l'utilisateur qui a reçu l'invitation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Relation avec le témoignage soumis suite à cette invitation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function testimonial(): BelongsTo
    {
        return $this->belongsTo(Testimonial::class);
    }
    
    /**
     * Scope pour les invitations en attente (non affichées).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
    
    /**
     * Scope pour les invitations affichées mais sans réponse.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDisplayed(Builder $query): Builder
    {
        return $query->where('status', 'displayed');
    }
    
    /**
     * Scope pour les invitations sur lesquelles l'utilisateur a cliqué.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeClicked(Builder $query): Builder
    {
        return $query->where('status', 'clicked');
    }
    
    /**
     * Scope pour les invitations qui ont abouti à un témoignage.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }
    
    /**
     * Scope pour les invitations déclinées.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDeclined(Builder $query): Builder
    {
        return $query->where('status', 'declined');
    }
    
    /**
     * Scope pour les invitations expirées.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired')
                     ->orWhere('expires_at', '<', now());
    }
    
    /**
     * Scope pour les invitations actives (non expirées et non traitées).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['submitted', 'declined', 'expired'])
                     ->where(function ($query) {
                         $query->whereNull('expires_at')
                               ->orWhere('expires_at', '>', now());
                     });
    }
    
    /**
     * Scope pour les invitations d'un type spécifique.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('trigger_type', $type);
    }
    
    /**
     * Marquer l'invitation comme affichée.
     *
     * @return bool
     */
    public function markAsDisplayed(): bool
    {
        if ($this->status === 'pending') {
            $this->status = 'displayed';
            $this->displayed_at = now();
            return $this->save();
        }
        
        return false;
    }
    
    /**
     * Marquer l'invitation comme cliquée.
     *
     * @return bool
     */
    public function markAsClicked(): bool
    {
        if (in_array($this->status, ['pending', 'displayed'])) {
            $this->status = 'clicked';
            $this->clicked_at = now();
            return $this->save();
        }
        
        return false;
    }
    
    /**
     * Marquer l'invitation comme ayant abouti à un témoignage.
     *
     * @param int $testimonialId
     * @return bool
     */
    public function markAsSubmitted(int $testimonialId): bool
    {
        $this->status = 'submitted';
        $this->testimonial_id = $testimonialId;
        $this->responded_at = now();
        return $this->save();
    }
    
    /**
     * Marquer l'invitation comme déclinée.
     *
     * @return bool
     */
    public function markAsDeclined(): bool
    {
        $this->status = 'declined';
        $this->responded_at = now();
        return $this->save();
    }
    
    /**
     * Vérifier si l'invitation est expirée.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' || 
               ($this->expires_at && $this->expires_at->isPast());
    }
    
    /**
     * Générer un message personnalisé en fonction du type de déclencheur.
     *
     * @return string
     */
    public function generateMessage(): string
    {
        $userName = $this->user->name ?? 'Membre';
        $triggerData = $this->trigger_data ?? [];
        
        switch ($this->trigger_type) {
            case self::TRIGGER_EARNINGS:
                $amount = $triggerData['amount'] ?? 0;
                return "Félicitations {$userName} ! Vous avez gagné {$amount}$ avec SOLIFIN. Partagez votre expérience pour inspirer d'autres membres !";
                
            case self::TRIGGER_REFERRALS:
                $count = $triggerData['count'] ?? 0;
                return "Bravo {$userName} ! Vous avez parrainé {$count} filleuls. Partagez votre stratégie de parrainage avec la communauté !";
                
            case self::TRIGGER_MEMBERSHIP:
                $months = $triggerData['months'] ?? 0;
                return "Merci pour votre fidélité {$userName} ! Cela fait {$months} mois que vous êtes avec nous. Partagez votre parcours SOLIFIN !";
                
            case self::TRIGGER_PACK_UPGRADE:
                $packName = $triggerData['pack_name'] ?? 'supérieur';
                return "Félicitations pour votre passage au pack {$packName}, {$userName} ! Comment cette évolution a-t-elle transformé votre expérience ?";
                
            case self::TRIGGER_WITHDRAWAL:
                return "Votre premier retrait a été traité avec succès, {$userName} ! Partagez votre satisfaction avec notre communauté !";
                
            case self::TRIGGER_BONUS:
                $bonusType = $triggerData['bonus_type'] ?? 'spécial';
                return "Vous venez de recevoir un bonus {$bonusType}, {$userName} ! Racontez comment vous avez atteint cet objectif !";
                
            default:
                return "Votre expérience est précieuse, {$userName} ! Partagez votre témoignage pour aider d'autres membres à réussir sur SOLIFIN.";
        }
    }
}
