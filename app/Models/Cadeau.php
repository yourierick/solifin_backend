<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cadeau extends Model
{
    use HasFactory;

    /**
     * Table associée au modèle.
     *
     * @var string
     */
    protected $table = 'cadeaux';

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pack_id',
        'nom',          // Nom du cadeau (chaîne de caractères, ex: "Smartphone", "Bon d'achat")
        'description',  // Description détaillée du cadeau (texte, peut contenir du HTML basique)
        'image_url',    // URL de l'image du cadeau (chaîne de caractères, chemin relatif ou URL complète)
        'valeur',       // Valeur monétaire du cadeau en devise locale (nombre décimal, ex: 50.00)
        'probabilite',  // Probabilité d'obtention du cadeau lors de l'utilisation d'un jeton (nombre décimal entre 0 et 100)
        'stock',        // Quantité disponible en stock (nombre entier, 0 = rupture de stock)
        'actif'         // État d'activation du cadeau (booléen: true = disponible, false = désactivé)
    ];
    
    /**
     * Les règles de validation pour les attributs du modèle.
     *
     * @var array
     */
    public static $rules = [
        'pack_id' => 'required|exists:packs,id',
        'nom' => 'required|string|max:255',
        'description' => 'nullable|string',
        'image_url' => 'nullable|string|max:2048',
        'valeur' => 'required|numeric|min:0',
        'probabilite' => 'required|numeric|min:0|max:100',
        'stock' => 'required|integer|min:0',
        'actif' => 'boolean'
    ];

    /**
     * Les attributs à caster vers des types natifs.
     * 
     * Ces conversions permettent de garantir le type de données lors de l'accès aux attributs,
     * indépendamment de leur stockage en base de données.
     *
     * @var array
     */
    protected $casts = [
        'valeur' => 'decimal:2',      // Conversion en décimal avec 2 chiffres après la virgule (ex: 49.99)
        'probabilite' => 'decimal:2',  // Pourcentage avec 2 décimales (ex: 12.50 pour 12,5%)
        'stock' => 'integer',          // Nombre entier de cadeaux disponibles
        'actif' => 'boolean',          // true = cadeau disponible, false = cadeau désactivé
    ];

    /**
     * Relation avec les tickets gagnants.
     * 
     * Un cadeau peut être associé à plusieurs tickets gagnants (relation one-to-many).
     * Cette relation permet de récupérer tous les tickets gagnants liés à ce cadeau,
     * par exemple pour suivre combien de fois ce cadeau a été gagné ou pour vérifier
     * les utilisateurs qui l'ont obtenu.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ticketsGagnants()
    {
        return $this->hasMany(TicketGagnant::class);
    }

    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }
}
