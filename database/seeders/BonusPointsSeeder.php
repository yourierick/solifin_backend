<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Pack;
use App\Models\BonusRates;
use App\Models\UserBonusPoint;
use App\Models\UserBonusPointHistory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BonusPointsSeeder extends Seeder
{
    /**
     * Exécute le seeding des données de test pour les points bonus.
     *
     * @return void
     */
    public function run()
    {
        // Vérifier si des packs existent
        $packs = Pack::all();
        if ($packs->isEmpty()) {
            $this->command->error('Aucun pack trouvé. Veuillez d\'abord créer des packs.');
            return;
        }

        // Vérifier si des utilisateurs existent
        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->error('Aucun utilisateur trouvé. Veuillez d\'abord créer des utilisateurs.');
            return;
        }

        // 1. Créer des taux de bonus pour chaque pack
        $this->createBonusRates($packs);

        // 2. Créer des points bonus pour certains utilisateurs
        $this->createUserBonusPoints($users, $packs);

        // 3. Créer un historique des points bonus
        $this->createBonusPointsHistory($users, $packs);

        $this->command->info('Données de test pour les points bonus générées avec succès!');
    }

    /**
     * Crée des taux de bonus pour chaque pack.
     *
     * @param \Illuminate\Database\Eloquent\Collection $packs
     * @return void
     */
    private function createBonusRates($packs)
    {
        // Supprimer les taux existants
        BonusRates::truncate();

        $frequencies = ['daily', 'weekly', 'monthly', 'yearly'];
        $pointValues = [0.1, 0.2, 0.5, 1, 2];

        foreach ($packs as $pack) {
            foreach ($frequencies as $frequency) {
                // Valeurs différentes selon la fréquence
                switch ($frequency) {
                    case 'daily':
                        $filleulsThreshold = rand(1, 3);
                        $pointsAwarded = 1;
                        $pointValue = $pointValues[array_rand($pointValues)];
                        break;
                    case 'weekly':
                        $filleulsThreshold = rand(3, 7);
                        $pointsAwarded = 2;
                        $pointValue = $pointValues[array_rand($pointValues)] * 2;
                        break;
                    case 'monthly':
                        $filleulsThreshold = rand(10, 15);
                        $pointsAwarded = 5;
                        $pointValue = $pointValues[array_rand($pointValues)] * 5;
                        break;
                    case 'yearly':
                        $filleulsThreshold = rand(50, 100);
                        $pointsAwarded = 20;
                        $pointValue = $pointValues[array_rand($pointValues)] * 10;
                        break;
                    default:
                        $filleulsThreshold = 5;
                        $pointsAwarded = 1;
                        $pointValue = 0.5;
                }

                BonusRates::create([
                    'pack_id' => $pack->id,
                    'frequence' => $frequency,
                    'nombre_filleuls' => $filleulsThreshold,
                    'points_attribues' => $pointsAwarded,
                    'valeur_point' => $pointValue
                ]);
            }
        }

        $this->command->info('Taux de bonus créés pour ' . count($packs) . ' packs.');
    }

    /**
     * Crée des points bonus pour certains utilisateurs.
     *
     * @param \Illuminate\Database\Eloquent\Collection $users
     * @param \Illuminate\Database\Eloquent\Collection $packs
     * @return void
     */
    private function createUserBonusPoints($users, $packs)
    {
        // Supprimer les points existants
        UserBonusPoint::truncate();

        $selectedUsers = $users->random(min(count($users), 10));
        $count = 0;

        foreach ($selectedUsers as $user) {
            // Attribuer des points pour 1 à 3 packs aléatoires
            $selectedPacks = $packs->random(rand(1, min(3, count($packs))));
            
            foreach ($selectedPacks as $pack) {
                $availablePoints = rand(5, 50);
                $usedPoints = rand(0, 10);
                
                UserBonusPoint::create([
                    'user_id' => $user->id,
                    'pack_id' => $pack->id,
                    'points_disponibles' => $availablePoints,
                    'points_utilises' => $usedPoints
                ]);
                
                $count++;
            }
        }

        $this->command->info($count . ' enregistrements de points bonus créés pour les utilisateurs.');
    }

    /**
     * Crée un historique des points bonus.
     *
     * @param \Illuminate\Database\Eloquent\Collection $users
     * @param \Illuminate\Database\Eloquent\Collection $packs
     * @return void
     */
    private function createBonusPointsHistory($users, $packs)
    {
        // Supprimer l'historique existant
        UserBonusPointHistory::truncate();

        $userBonusPoints = UserBonusPoint::with(['user', 'pack'])->get();
        $count = 0;
        
        foreach ($userBonusPoints as $userBonusPoint) {
            // Générer entre 3 et 10 entrées d'historique par utilisateur et pack
            $entriesCount = rand(3, 10);
            
            for ($i = 0; $i < $entriesCount; $i++) {
                $type = rand(0, 3) > 0 ? 'gain' : 'conversion'; // 75% gain, 25% conversion
                $points = $type === 'gain' ? rand(1, 10) : -rand(1, 5);
                $date = Carbon::now()->subDays(rand(1, 60));
                
                // Descriptions différentes selon le type
                if ($type === 'gain') {
                    $frequencies = ['daily', 'weekly', 'monthly', 'yearly'];
                    $frequency = $frequencies[array_rand($frequencies)];
                    $filleulsCount = rand(5, 20);
                    $description = "Bonus $frequency pour $filleulsCount filleuls parrainés";
                    $metadata = json_encode([
                        'frequency' => $frequency,
                        'filleuls_count' => $filleulsCount,
                        'pack_name' => $userBonusPoint->pack->name
                    ]);
                } else {
                    $bonusRate = BonusRates::where('pack_id', $userBonusPoint->pack_id)
                        ->where('frequence', 'weekly')
                        ->first();
                    
                    $valuePerPoint = $bonusRate ? $bonusRate->valeur_point : 0.5;
                    $amount = abs($points) * $valuePerPoint;
                    
                    $description = "Conversion de " . abs($points) . " points en $amount devise";
                    $metadata = json_encode([
                        'value_per_point' => $valuePerPoint,
                        'amount' => $amount,
                        'pack_name' => $userBonusPoint->pack->name
                    ]);
                }
                
                UserBonusPointHistory::create([
                    'user_id' => $userBonusPoint->user_id,
                    'pack_id' => $userBonusPoint->pack_id,
                    'points' => $points,
                    'type' => $type,
                    'description' => $description,
                    'metadata' => $metadata,
                    'created_at' => $date,
                    'updated_at' => $date
                ]);
                
                $count++;
            }
        }

        $this->command->info($count . ' entrées d\'historique de points bonus créées.');
    }
}
