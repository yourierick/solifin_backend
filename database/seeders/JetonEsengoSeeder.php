<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Pack;
use App\Models\UserJetonEsengo;
use App\Models\UserJetonEsengoHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JetonEsengoSeeder extends Seeder
{
    /**
     * Exécute le seeding des données de test pour les jetons Esengo.
     * Ce seeder crée des jetons Esengo pour les utilisateurs existants
     * avec différents états (non utilisés, utilisés, expirés).
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Initialisation des jetons Esengo de test...');
        
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

        // Supprimer les jetons existants (optionnel, décommenter si nécessaire)
        // DB::table('user_jeton_esengos')->truncate();
        // DB::table('user_jeton_esengo_histories')->truncate();
        
        $jetonsCreated = 0;
        $historiesCreated = 0;
        
        DB::beginTransaction();
        
        try {
            // Pour chaque utilisateur, créer quelques jetons avec différents états
            foreach ($users->take(10) as $user) {
                $pack = $packs->random();
                
                // Métadonnées communes
                $commonMetadata = [
                    'frequency' => 'monthly',
                    'filleuls_count' => rand(30, 45),
                    'pack_id' => 1,
                    'pack_name' => "Pack Primaire",
                    'points_per_threshold' => 1,
                    'threshold' => 30,
                    'type' => 'esengo',
                    'is_test_data' => true
                ];
                
                // 1. Jetons actifs (non utilisés)
                $activeJetonsCount = rand(1, 5);
                for ($i = 0; $i < $activeJetonsCount; $i++) {
                    $jeton = UserJetonEsengo::create([
                        'user_id' => $user->id,
                        'pack_id' => 1,
                        'code_unique' => UserJetonEsengo::generateUniqueCode($user->id),
                        'is_used' => false,
                        'date_expiration' => Carbon::now()->addMonths(3),
                        'metadata' => $commonMetadata,
                    ]);
                    
                    // Enregistrer l'attribution dans l'historique
                    UserJetonEsengoHistory::logAttribution(
                        $jeton,
                        'Attribution de jeton Esengo (données de test)',
                        array_merge($commonMetadata, ['created_by' => 'seeder'])
                    );
                    
                    $jetonsCreated++;
                    $historiesCreated++;
                }
                
                // 2. Jetons utilisés
                $usedJetonsCount = rand(0, 3);
                for ($i = 0; $i < $usedJetonsCount; $i++) {
                    $jeton = UserJetonEsengo::create([
                        'user_id' => $user->id,
                        'pack_id' => 1,
                        'code_unique' => UserJetonEsengo::generateUniqueCode($user->id),
                        'is_used' => true,
                        'date_expiration' => Carbon::now()->addMonths(3),
                        'date_utilisation' => Carbon::now()->subDays(rand(1, 30)),
                        'metadata' => $commonMetadata,
                    ]);
                    
                    // Enregistrer l'attribution dans l'historique
                    UserJetonEsengoHistory::logAttribution(
                        $jeton,
                        'Attribution de jeton Esengo (données de test)',
                        array_merge($commonMetadata, ['created_by' => 'seeder'])
                    );
                    
                    // Enregistrer l'utilisation dans l'historique
                    UserJetonEsengoHistory::logUtilisation(
                        $jeton,
                        null, // Pas de cadeau réel associé pour les données de test
                        'Utilisation de jeton Esengo (données de test)',
                        array_merge($commonMetadata, [
                            'used_by' => 'seeder',
                            'used_at' => $jeton->date_utilisation->format('Y-m-d H:i:s')
                        ])
                    );
                    
                    $jetonsCreated++;
                    $historiesCreated += 2; // Attribution + Utilisation
                }
                
                // 3. Jetons expirés
                $expiredJetonsCount = rand(0, 2);
                for ($i = 0; $i < $expiredJetonsCount; $i++) {
                    $expirationDate = Carbon::now()->subDays(rand(1, 30));
                    
                    $jeton = UserJetonEsengo::create([
                        'user_id' => $user->id,
                        'pack_id' => 1,
                        'code_unique' => UserJetonEsengo::generateUniqueCode($user->id),
                        'is_used' => false,
                        'date_expiration' => $expirationDate,
                        'metadata' => $commonMetadata,
                    ]);
                    
                    // Enregistrer l'attribution dans l'historique
                    UserJetonEsengoHistory::logAttribution(
                        $jeton,
                        'Attribution de jeton Esengo (données de test)',
                        array_merge($commonMetadata, ['created_by' => 'seeder'])
                    );
                    
                    // Enregistrer l'expiration dans l'historique
                    UserJetonEsengoHistory::logExpiration(
                        $jeton,
                        'Expiration de jeton Esengo (données de test)',
                        array_merge($commonMetadata, [
                            'expired_at' => $expirationDate->format('Y-m-d H:i:s')
                        ])
                    );
                    
                    $jetonsCreated++;
                    $historiesCreated += 2; // Attribution + Expiration
                }
            }
            
            DB::commit();
            $this->command->info("Seeding terminé avec succès: $jetonsCreated jetons Esengo créés et $historiesCreated entrées d'historique.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors du seeding des jetons Esengo: ' . $e->getMessage());
            $this->command->error('Erreur lors du seeding: ' . $e->getMessage());
        }
    }
}
