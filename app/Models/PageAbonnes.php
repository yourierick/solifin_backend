<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageAbonnes extends Model
{
    use HasFactory;

    protected $table = 'page_abonnes';

    protected $fillable = [
        'page_id',
        'user_id',
    ];

    /**
     * Récupérer la page associée
     */
    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Récupérer l'utilisateur abonné
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
