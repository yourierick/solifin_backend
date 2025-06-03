<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TicketGagnant extends Model
{
    use HasFactory;

    /**
     * Table associée au modèle.
     *
     * @var string
     */
    protected $table = 'tickets_gagnants';

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'cadeau_id',
        'code_jeton',
        'date_expiration',
        'consomme',
        'date_consommation',
        'code_verification'
    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'date_expiration' => 'datetime',
        'date_consommation' => 'datetime',
        'consomme' => 'boolean',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec le cadeau
     */
    public function cadeau()
    {
        return $this->belongsTo(Cadeau::class);
    }

    /**
     * Vérifie si le ticket est expiré
     *
     * @return bool
     */
    public function estExpire()
    {
        return $this->date_expiration->isPast();
    }

    /**
     * Vérifie si le ticket est valide (non expiré et non consommé)
     *
     * @return bool
     */
    public function estValide()
    {
        return !$this->consomme && !$this->estExpire();
    }

    /**
     * Marque le ticket comme consommé
     *
     * @return bool
     */
    public function marquerCommeConsomme()
    {
        $this->consomme = true;
        $this->date_consommation = Carbon::now();
        return $this->save();
    }

    /**
     * Génère un code de vérification unique
     *
     * @return string
     */
    public static function genererCodeVerification()
    {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }
}
