<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cadeau;
use Illuminate\Support\Facades\Log;

/**
 * Seeder pour initialiser les cadeaux pour les jetons Esengo
 */
class SeedCadeaux extends Seeder
{

    /**
     * Exécute la commande console.
     *
     * @return int
     */
    public function run()
    {
        try {
            // Liste des cadeaux par défaut
            $cadeaux = [
                [
                    'pack_id' => 1,
                    'nom' => 'Carte cadeau 50$',
                    'description' => 'Carte cadeau d\'une valeur de 50$ à utiliser dans les magasins partenaires',
                    'image_url' => '/images/cadeaux/carte-cadeau-50.jpg',
                    'valeur' => 50,
                    'probabilite' => 5,
                    'stock' => 10,
                    'actif' => true
                ],
                [
                    'pack_id' => 1,
                    'nom' => 'Carte cadeau 20$',
                    'description' => 'Carte cadeau d\'une valeur de 20$ à utiliser dans les magasins partenaires',
                    'image_url' => '/images/cadeaux/carte-cadeau-20.jpg',
                    'valeur' => 20,
                    'probabilite' => 15,
                    'stock' => 25,
                    'actif' => true
                ],
                [
                    'pack_id' => 1,
                    'nom' => 'Carte cadeau 10$',
                    'description' => 'Carte cadeau d\'une valeur de 10$ à utiliser dans les magasins partenaires',
                    'image_url' => '/images/cadeaux/carte-cadeau-10.jpg',
                    'valeur' => 10,
                    'probabilite' => 30,
                    'stock' => 50,
                    'actif' => true
                ],
                [
                    'pack_id' => 1,
                    'nom' => 'T-shirt SOLIFIN',
                    'description' => 'T-shirt officiel de la plateforme SOLIFIN',
                    'image_url' => '/images/cadeaux/tshirt-solifin.jpg',
                    'valeur' => 15,
                    'probabilite' => 20,
                    'stock' => 30,
                    'actif' => true
                ],
                [
                    'pack_id' => 1,
                    'nom' => 'Casquette SOLIFIN',
                    'description' => 'Casquette officielle de la plateforme SOLIFIN',
                    'image_url' => '/images/cadeaux/casquette-solifin.jpg',
                    'valeur' => 12,
                    'probabilite' => 30,
                    'stock' => 40,
                    'actif' => true
                ]
            ];
            
            $count = 0;
            
            foreach ($cadeaux as $cadeau) {
                // Vérifier si le cadeau existe déjà
                $existing = Cadeau::where('nom', $cadeau['nom'])->first();
                
                if (!$existing) {
                    Cadeau::create($cadeau);
                    $count++;
                } else {
                    Log::info("Cadeau existant ignoré : {$cadeau['nom']}");
                }
            }
            
            Log::info("$count nouveaux cadeaux ont été créés avec succès.");
            return 0;
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'initialisation des cadeaux : " . $e->getMessage());
            return 1;
        }
    }
}
