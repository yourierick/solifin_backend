<?php

namespace App\Services;

use App\Models\User;
use App\Models\Testimonial;
use App\Models\TestimonialPrompt;
use App\Models\Wallet;
use App\Models\UserPack;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service pour gérer les invitations à témoigner
 */
class TestimonialPromptService
{
    /**
     * Durée de validité par défaut d'une invitation (en jours)
     */
    const DEFAULT_EXPIRATION_DAYS = 14;
    
    /**
     * Seuil minimal de gains pour déclencher une invitation basée sur les revenus
     */
    const MIN_EARNINGS_THRESHOLD = 100;
    
    /**
     * Seuil minimal de filleuls pour déclencher une invitation basée sur le parrainage
     */
    const MIN_REFERRALS_THRESHOLD = 5;
    
    /**
     * Vérifie si un utilisateur est éligible pour recevoir une invitation à témoigner
     * et crée l'invitation si c'est le cas.
     *
     * @param User $user L'utilisateur à vérifier
     * @return TestimonialPrompt|null L'invitation créée ou null si aucune invitation n'a été créée
     */
    public function checkEligibilityAndCreatePrompt(User $user): ?TestimonialPrompt
    {
        // Vérifier si l'utilisateur est éligible pour recevoir une invitation
        if (!$user->isEligibleForTestimonialPrompt()) {
            return null;
        }
        
        // Vérifier les différents déclencheurs possibles
        $trigger = $this->findBestTrigger($user);
        
        // Si aucun déclencheur n'est trouvé, ne pas créer d'invitation
        if (!$trigger) {
            return null;
        }
        
        // Créer l'invitation
        return $this->createPrompt($user, $trigger['type'], $trigger['data']);
    }
    
    /**
     * Trouve le meilleur déclencheur pour inviter l'utilisateur à témoigner.
     *
     * @param User $user L'utilisateur à vérifier
     * @return array|null Le déclencheur trouvé ou null si aucun déclencheur n'est trouvé
     */
    private function findBestTrigger(User $user): ?array
    {
        // Vérifier les déclencheurs dans l'ordre de priorité
        $triggers = [
            $this->checkFirstWithdrawalTrigger($user),
            $this->checkEarningsTrigger($user),
            $this->checkReferralsTrigger($user),
            $this->checkPackUpgradeTrigger($user),
            $this->checkBonusReceivedTrigger($user),
            $this->checkMembershipDurationTrigger($user),
        ];
        
        // Retourner le premier déclencheur non-null trouvé
        foreach ($triggers as $trigger) {
            if ($trigger !== null) {
                return $trigger;
            }
        }
        
        return null;
    }
    
    /**
     * Vérifie si l'utilisateur a effectué son premier retrait avec succès.
     *
     * @param User $user L'utilisateur à vérifier
     * @return array|null Le déclencheur ou null
     */
    private function checkFirstWithdrawalTrigger(User $user): ?array
    {
        // Vérifier si l'utilisateur a un wallet
        if (!$user->wallet) {
            return null;
        }
        
        // Vérifier si l'utilisateur a effectué exactement un retrait avec succès
        $successfulWithdrawals = $user->wallet->transactions
            ->where('type', 'withdrawal')
            ->where('status', 'completed')
            ->count();
            
        if ($successfulWithdrawals === 1) {
            // Récupérer le montant du premier retrait
            $firstWithdrawal = $user->wallet->transactions
                ->where('type', 'withdrawal')
                ->where('status', 'completed')
                ->first();
                
            if ($firstWithdrawal && $firstWithdrawal->created_at->diffInDays(now()) <= 7) {
                return [
                    'type' => TestimonialPrompt::TRIGGER_WITHDRAWAL,
                    'data' => [
                        'amount' => $firstWithdrawal->amount,
                        'currency' => $firstWithdrawal->currency,
                        'date' => $firstWithdrawal->created_at->toDateString(),
                    ],
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Vérifie si l'utilisateur a atteint un seuil significatif de gains.
     *
     * @param User $user L'utilisateur à vérifier
     * @return array|null Le déclencheur ou null
     */
    private function checkEarningsTrigger(User $user): ?array
    {
        // Vérifier si l'utilisateur a un wallet
        if (!$user->wallet) {
            return null;
        }
        
        // Vérifier si l'utilisateur a gagné un montant significatif
        if ($user->wallet->total_earned >= self::MIN_EARNINGS_THRESHOLD) {
            // Vérifier si l'utilisateur a atteint un palier significatif
            $significantThresholds = [100, 500, 1000, 5000, 10000];
            $currentEarnings = $user->wallet->total_earned;
            
            foreach ($significantThresholds as $threshold) {
                // Si les gains sont juste au-dessus d'un seuil (marge de 10%)
                if ($currentEarnings >= $threshold && $currentEarnings <= $threshold * 1.1) {
                    return [
                        'type' => TestimonialPrompt::TRIGGER_EARNINGS,
                        'data' => [
                            'amount' => $threshold,
                            'currency' => 'USD',
                            'total_earnings' => $currentEarnings,
                        ],
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Vérifie si l'utilisateur a atteint un nombre significatif de filleuls.
     *
     * @param User $user L'utilisateur à vérifier
     * @return array|null Le déclencheur ou null
     */
    private function checkReferralsTrigger(User $user): ?array
    {
        // Vérifier le nombre de filleuls de l'utilisateur
        $referrals = $user->referrals()->count();
        
        if ($referrals >= self::MIN_REFERRALS_THRESHOLD) {
            // Vérifier si l'utilisateur a atteint un palier significatif
            $significantThresholds = [5, 10, 20, 50, 100];
            
            foreach ($significantThresholds as $threshold) {
                // Si le nombre de filleuls est exactement égal à un seuil
                if ($referrals === $threshold) {
                    return [
                        'type' => TestimonialPrompt::TRIGGER_REFERRALS,
                        'data' => [
                            'count' => $referrals,
                            'milestone' => $threshold,
                        ],
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Vérifie si l'utilisateur a récemment acheté un pack supérieur.
     *
     * @param User $user L'utilisateur à vérifier
     * @return array|null Le déclencheur ou null
     */
    private function checkPackUpgradeTrigger(User $user): ?array
    {
        // Récupérer le dernier pack acheté par l'utilisateur
        $latestPack = $user->packs()
            ->orderBy('user_packs.created_at', 'desc')
            ->first();
            
        if (!$latestPack) {
            return null;
        }
        
        // Vérifier si le pack a été acheté récemment (dans les 7 derniers jours)
        $purchaseDate = $latestPack->pivot->created_at;
        
        if ($purchaseDate && $purchaseDate->diffInDays(now()) <= 7) {
            // Vérifier si c'est un upgrade (l'utilisateur avait déjà d'autres packs)
            $previousPacks = $user->packs()
                ->where('user_packs.created_at', '<', $purchaseDate)
                ->count();
                
            if ($previousPacks > 0) {
                return [
                    'type' => TestimonialPrompt::TRIGGER_PACK_UPGRADE,
                    'data' => [
                        'pack_id' => $latestPack->id,
                        'pack_name' => $latestPack->name,
                        'purchase_date' => $purchaseDate->toDateString(),
                    ],
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Vérifie si l'utilisateur a reçu un bonus récemment.
     *
     * @param User $user L'utilisateur à vérifier
     * @return array|null Le déclencheur ou null
     */
    private function checkBonusReceivedTrigger(User $user): ?array
    {
        // Vérifier si l'utilisateur a un wallet
        if (!$user->wallet) {
            return null;
        }
        
        // Vérifier si l'utilisateur a reçu un bonus récemment (dans les 7 derniers jours)
        $recentBonus = $user->wallet->transactions()
            ->where('type', 'bonus')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($recentBonus) {
            return [
                'type' => TestimonialPrompt::TRIGGER_BONUS,
                'data' => [
                    'bonus_type' => $recentBonus->description ?? 'parrainage',
                    'amount' => $recentBonus->amount,
                    'date' => $recentBonus->created_at->toDateString(),
                ],
            ];
        }
        
        return null;
    }
    
    /**
     * Vérifie si l'utilisateur a atteint une durée d'adhésion significative.
     *
     * @param User $user L'utilisateur à vérifier
     * @return array|null Le déclencheur ou null
     */
    private function checkMembershipDurationTrigger(User $user): ?array
    {
        // Calculer la durée d'adhésion en mois
        $membershipMonths = $user->created_at->diffInMonths(now());
        
        // Vérifier si l'utilisateur a atteint un jalon significatif (3, 6, 12, 24, 36 mois)
        $significantMilestones = [3, 6, 12, 24, 36];
        
        foreach ($significantMilestones as $milestone) {
            if ($membershipMonths === $milestone) {
                return [
                    'type' => TestimonialPrompt::TRIGGER_MEMBERSHIP,
                    'data' => [
                        'months' => $milestone,
                        'joined_date' => $user->created_at->toDateString(),
                    ],
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Crée une invitation à témoigner pour un utilisateur.
     *
     * @param User $user L'utilisateur pour lequel créer l'invitation
     * @param string $triggerType Le type de déclencheur
     * @param array $triggerData Les données contextuelles du déclencheur
     * @param int $expirationDays Nombre de jours avant expiration de l'invitation
     * @return TestimonialPrompt L'invitation créée
     */
    public function createPrompt(User $user, string $triggerType, array $triggerData, int $expirationDays = self::DEFAULT_EXPIRATION_DAYS): TestimonialPrompt
    {
        // Générer un message personnalisé en fonction du type de déclencheur
        $prompt = new TestimonialPrompt([
            'user_id' => $user->id,
            'trigger_type' => $triggerType,
            'trigger_data' => $triggerData,
            'status' => 'pending',
            'expires_at' => now()->addDays($expirationDays),
        ]);
        
        // Générer un message personnalisé
        $prompt->message = $prompt->generateMessage();
        
        // Sauvegarder l'invitation
        $prompt->save();
        
        // Journaliser la création de l'invitation
        Log::info('Invitation à témoigner créée', [
            'user_id' => $user->id,
            'trigger_type' => $triggerType,
            'prompt_id' => $prompt->id,
        ]);
        
        return $prompt;
    }
    
    /**
     * Vérifie et crée des invitations à témoigner pour tous les utilisateurs éligibles.
     * Cette méthode est destinée à être exécutée par une tâche planifiée.
     *
     * @param int $batchSize Nombre d'utilisateurs à traiter par lot
     * @return array Statistiques sur les invitations créées
     */
    public function processEligibleUsers(int $batchSize = 100): array
    {
        $stats = [
            'processed' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        
        // Récupérer les utilisateurs actifs qui n'ont pas reçu d'invitation récemment
        User::where('status', 'active')
            ->where('created_at', '<', now()->subDays(30)) // Utilisateurs inscrits depuis au moins 30 jours
            ->chunk($batchSize, function ($users) use (&$stats) {
                foreach ($users as $user) {
                    $stats['processed']++;
                    
                    try {
                        // Vérifier l'éligibilité et créer une invitation si nécessaire
                        $prompt = $this->checkEligibilityAndCreatePrompt($user);
                        
                        if ($prompt) {
                            $stats['created']++;
                        } else {
                            $stats['skipped']++;
                        }
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        Log::error('Erreur lors de la création d\'une invitation à témoigner', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            });
        
        return $stats;
    }
    
    /**
     * Récupère les invitations actives pour un utilisateur spécifique.
     *
     * @param User $user L'utilisateur pour lequel récupérer les invitations
     * @return \Illuminate\Database\Eloquent\Collection Les invitations actives
     */
    public function getActivePromptsForUser(User $user)
    {
        return $user->testimonialPrompts()
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Marque comme expirées toutes les invitations qui ont dépassé leur date d'expiration.
     * Cette méthode est destinée à être exécutée par une tâche planifiée.
     *
     * @return int Nombre d'invitations expirées
     */
    public function expireOldPrompts(): int
    {
        $count = TestimonialPrompt::whereNotIn('status', ['submitted', 'declined', 'expired'])
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
            
        Log::info("{$count} invitations à témoigner ont été marquées comme expirées");
        
        return $count;
    }
}
