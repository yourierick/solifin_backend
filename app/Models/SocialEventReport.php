<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialEventReport extends Model
{
    use HasFactory;
    
    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'social_event_id',
        'user_id',
        'reason',
        'description',
        'status',
        'admin_notes',
    ];
    
    /**
     * Les raisons de signalement disponibles.
     *
     * @return array
     */
    public static function getReportReasons(): array
    {
        return [
            'inappropriate_content' => 'Contenu inapproprié',
            'harassment' => 'Harcèlement',
            'spam' => 'Spam ou contenu commercial',
            'false_information' => 'Fausses informations',
            'hate_speech' => 'Discours haineux',
            'violence' => 'Violence ou contenu choquant',
            'intellectual_property' => 'Violation de propriété intellectuelle',
            'other' => 'Autre raison',
        ];
    }
    
    /**
     * Obtenir le statut social associé au signalement.
     */
    public function socialEvent(): BelongsTo
    {
        return $this->belongsTo(SocialEvent::class);
    }
    
    /**
     * Obtenir l'utilisateur qui a fait le signalement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
