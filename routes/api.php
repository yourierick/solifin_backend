<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerificationController;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdvertisementValidationController;
use App\Http\Controllers\Admin\BusinessOpportunityValidationController;
use App\Http\Controllers\Admin\JobOfferValidationController;
use App\Http\Controllers\PackPurchaseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\CommissionRateController;
use App\Http\Controllers\Admin\PackController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\User\WalletUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\Api\CurrencyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes publiques (chargement des packs dans la page d'accueil)
Route::get('/packs', [App\Http\Controllers\HomeController::class, 'index']);
// Route publique pour les publicités approuvées (carrousel)
Route::get('/ads/approved', [App\Http\Controllers\HomeController::class, 'approvedAds']);
// Routes publiques pour les témoignages
Route::get('/testimonials/featured', [App\Http\Controllers\TestimonialController::class, 'getFeatured']);
Route::get('/testimonials/approved', [App\Http\Controllers\TestimonialController::class, 'getApproved']);
// Route pour les statistiques publiques
Route::get('/stats/home', [App\Http\Controllers\StatsController::class, 'getHomeStats']);

// Routes pour le système de FAQ
Route::get('/faqs', [App\Http\Controllers\FaqController::class, 'index']);
Route::get('/faq/categories', [App\Http\Controllers\FaqController::class, 'getCategories']);
Route::post('/faqs/{id}/vote', [App\Http\Controllers\FaqController::class, 'vote']);
Route::get('/faqs/search', [App\Http\Controllers\FaqController::class, 'search']);
// Routes d'achat de pack (achat d'un pack lors de l'enregistrement)
Route::get('/purchases/{sponsor_code}', [PackPurchaseController::class, 'show']);
// Route::post('/purchases/initiate', [PackPurchaseController::class, 'initiate']);
// Route::post('/purchases/{id}/process', [PackPurchaseController::class, 'process']);

// Routes publiques pour les frais de transaction
//Route::post('/transaction-fees/calculate', [App\Http\Controllers\Api\TransactionFeeApiController::class, 'calculateTransferFee']);
//Route::get('/payment-method', [App\Http\Controllers\Api\TransactionFeeApiController::class, 'getFeesForPaymentMethod']);
Route::post('/transaction-fees/withdrawal', [App\Http\Controllers\Api\TransactionFeeApiController::class, 'calculateWithdrawalFee']);
Route::post('/transaction-fees/transfer', [App\Http\Controllers\Api\TransactionFeeApiController::class, 'calculateTransferFee']);


RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
});

Route::middleware('throttle:api')->group(function () {
    // Routes d'authentification
    Route::middleware('guest')->group(function () {
        Route::post('/login', [LoginController::class, 'login']);
        Route::post('/register/{packId}', [RegisterController::class, 'register']);
    });

    // Routes de réinitialisation de mot de passe
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
    Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);

    // Routes de conversion de devise
    Route::post('/currency/convert', [CurrencyController::class, 'convert']);
    
    // Routes de vérification d'email
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Email de vérification envoyé']);
    })->middleware(['auth:sanctum', 'throttle:6,1']);
});

// Route publique pour vérifier une invitation par son code
Route::post('/check-invitation', [\App\Http\Controllers\ReferralInvitationController::class, 'checkInvitation']);

// Route publique pour récupérer les packs administrateurs avec leurs codes de parrainage
Route::get('/admin-packs', [\App\Http\Controllers\Admin\PackController::class, 'getAdminPacks']);

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {
    // Route pour vérifier l'authentification
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->picture = $user->getProfilePictureUrlAttribute();
        
        // Vérifier si c'est une vérification automatique d'authentification
        // et non une activité utilisateur réelle
        if ($request->query('check_only') === 'true' || $request->header('X-No-Activity-Update')) {
            // Ne pas mettre à jour le timestamp de dernière activité de la session
            // Cela permettra à la session d'expirer après la durée d'inactivité configurée
            // même si des appels API automatiques sont effectués
            //\Log::debug('Vérification d\'authentification sans mise à jour d\'activité pour l\'utilisateur ' . $user->id);
        } else {
            // Pour les requêtes normales, mettre à jour le timestamp de dernière activité
            // Cela est géré automatiquement par Laravel, mais nous le journalisons pour débogage
            //\Log::debug('Mise à jour du timestamp d\'activité pour l\'utilisateur ' . $user->id);
        }
        
        return $user;
    });
    
    // Route pour rafraîchir la session
    Route::post('/refresh-session', function (Request $request) {
        $request->session()->regenerate();
        return response()->json(['message' => 'Session rafraîchie']);
    });
    
    Route::post('/logout', [LoginController::class, 'logout']);
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/network', [DashboardController::class, 'network']);
    Route::get('/dashboard/wallet', [DashboardController::class, 'wallet']);
    Route::get('/dashboard/packs', [DashboardController::class, 'packs']);
    Route::get('/dashboard/carousel', [DashboardController::class, 'carousel']);
    Route::get('/stats/global', [DashboardController::class, 'getGlobalStats']);

    // Routes de notification
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'delete']);
    Route::delete('/notifications', [NotificationController::class, 'deleteAll']);

    Route::get('/userwallet/data', [WalletUserController::class, 'getWalletData']);
    Route::get('/userwallet/balance', [WalletUserController::class, 'getWalletBalance']);
    
    // Déconnexion
    Route::middleware('auth:sanctum')->post('/logout', [LoginController::class, 'logout']);

    // Routes pour le profil utilisateur
    // Route::get('/user', [\App\Http\Controllers\UserController::class, 'show']);
    // Route::put('/user', [\App\Http\Controllers\UserController::class, 'update']);
    // Route::post('/user/profile-photo', [\App\Http\Controllers\UserController::class, 'updateProfilePhoto']);

    // Routes pour les packs utilisateur
    Route::get('/user/packs', [\App\Http\Controllers\User\PackController::class, 'getUserPacks']);
    Route::post('/packs/{pack}/renew', [\App\Http\Controllers\User\PackController::class, 'renewPack']);
    Route::get('/packs/{pack}/referrals', [\App\Http\Controllers\User\PackController::class, 'getPackReferrals']);
    Route::get('/packs/{pack}/detailed-stats', [\App\Http\Controllers\User\PackController::class, 'getDetailedPackStats']);
    Route::post('/packs/purchase_a_new_pack', [\App\Http\Controllers\User\PackController::class, 'purchase_a_new_pack']);
    
    // Routes pour les demandes de retrait
    Route::post('/withdrawal/request/{walletId}', [WithdrawalController::class, 'request']);
    Route::post('/withdrawal/request/{id}/cancel', [WithdrawalController::class, 'cancel']);
    Route::get('/withdrawal/referral-commission', [WithdrawalController::class, 'getReferralCommissionPercentage']);

    // Routes pour les finances de l'utilisateur
    Route::prefix('user/finances')->group(function () {
        Route::get('/transactions', [\App\Http\Controllers\User\FinanceController::class, 'getTransactions']);
        Route::get('/transaction-types', [\App\Http\Controllers\User\FinanceController::class, 'getTransactionTypes']);
        Route::get('/stats-by-type', [\App\Http\Controllers\User\FinanceController::class, 'getTransactionStatsByType']);
        Route::get('/wallet-balance', [\App\Http\Controllers\User\FinanceController::class, 'getWalletBalance']);
        Route::get('/summary', [\App\Http\Controllers\User\FinanceController::class, 'getSummary']);
        
        // Routes pour les points bonus
        Route::get('/bonus-points-history', [\App\Http\Controllers\User\FinanceController::class, 'getBonusPointsHistory']);
        Route::get('/bonus-points-stats', [\App\Http\Controllers\User\FinanceController::class, 'getBonusPointsStats']);
        Route::get('/bonus-points-types', [\App\Http\Controllers\User\FinanceController::class, 'getBonusPointsTypes']);
    });

    // Routes pour la gestion des pages et publications et files d'actualités
    Route::get('/my-page', [App\Http\Controllers\PageController::class, 'getMyPage']);
    Route::post('/my-page/update-cover-photo', [App\Http\Controllers\PageController::class, 'updateCoverPhoto']);
    Route::get('/feed', [App\Http\Controllers\FeedController::class, 'index']);
    
    //Route::get('/posts/{id}', [App\Http\Controllers\FeedController::class, 'show']);
    Route::get('/pages/subscribed', [App\Http\Controllers\FeedController::class, 'subscribedPages']);
    Route::get('/pages/recommended', [App\Http\Controllers\FeedController::class, 'recommendedPages']);
    Route::post('/pages/{id}/subscribe', [App\Http\Controllers\FeedController::class, 'subscribe']);
    Route::post('/pages/{id}/unsubscribe', [App\Http\Controllers\FeedController::class, 'unsubscribe']);
    Route::get('/pages/{id}/check-subscription', [App\Http\Controllers\PageController::class, 'checkSubscription']);
    Route::get('/pages/{id}', [App\Http\Controllers\PageController::class, 'getPage']);
    
    // Routes pour les publicités
    Route::get('/publicites', [App\Http\Controllers\PubliciteController::class, 'index']);
    Route::post('/publicites', [App\Http\Controllers\PubliciteController::class, 'store']);
    Route::get('/publicites/{id}', [App\Http\Controllers\PubliciteController::class, 'show']);
    Route::get('/publicites/{id}/details', [App\Http\Controllers\PubliciteController::class, 'details']);
    Route::put('/publicites/{id}', [App\Http\Controllers\PubliciteController::class, 'update']);
    Route::delete('/publicites/{id}', [App\Http\Controllers\PubliciteController::class, 'destroy']);
    Route::put('/ad/{id}/etat', [App\Http\Controllers\PubliciteController::class, 'changeEtat']);
    Route::put('/publicites/{id}/statut', [App\Http\Controllers\PubliciteController::class, 'changeStatut']);
    Route::post('/publicites/{id}/boost', [App\Http\Controllers\PubliciteController::class, 'boost']);
    Route::get('/admin/publicites/pending', [App\Http\Controllers\PubliciteController::class, 'getPendingAds']);
    
    // Routes pour les interactions avec les publicités
    Route::post('/publicites/{id}/like', [App\Http\Controllers\PubliciteController::class, 'like']);
    Route::get('/publicites/{id}/check-like', [App\Http\Controllers\PubliciteController::class, 'checkLike']);
    Route::post('/publicites/{id}/comment', [App\Http\Controllers\PubliciteController::class, 'comment']);
    Route::get('/publicites/{id}/comments', [App\Http\Controllers\PubliciteController::class, 'getComments']);
    Route::delete('/publicites/comments/{commentId}', [App\Http\Controllers\PubliciteController::class, 'deleteComment']);
    Route::post('/publicites/{id}/share', [App\Http\Controllers\PubliciteController::class, 'share']);
    Route::get('/publicites/{id}/shares', [App\Http\Controllers\PubliciteController::class, 'getShares']);
    
    // Routes pour les offres d'emploi
    Route::get('/offres-emploi', [App\Http\Controllers\OffreEmploiController::class, 'index']);
    Route::post('/offres-emploi', [App\Http\Controllers\OffreEmploiController::class, 'store']);
    
    //Route pour ajouter un module à une formation
    //Récupération des frais d'achat d'une formation
    Route::get('/purchase-fee-percentage', [App\Http\Controllers\User\UserFormationController::class, 'getPurchaseFeePercentage']);
    
    //Récupération des formations achetées par l'utilisateur
    Route::get('/formations/purchased', [App\Http\Controllers\User\UserFormationController::class, 'getPurchasedFormations']);
    
    // Routes pour les formations (utilisateur)
    Route::prefix('formations')->group(function () {
        //Route pour acheter une formation
        Route::post('/purchase/{id}', [App\Http\Controllers\User\UserFormationController::class, 'purchase']);
        
        // Routes alternatives pour le composant QuizPlayer
        Route::post('/modules/{moduleId}/quiz/submit', [\App\Http\Controllers\User\QuizController::class, 'submitAnswers']);
        Route::get('/modules/{moduleId}/quiz/results', [\App\Http\Controllers\User\QuizController::class, 'getResults']);
        Route::get('/modules/{moduleId}', [\App\Http\Controllers\User\UserFormationController::class, 'showModuleWithoutFormation']);
        
        // Accès aux formations disponibles
        Route::get('/', [\App\Http\Controllers\User\UserFormationController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\User\UserFormationController::class, 'show']);
        Route::get('/{formationId}/modules/{moduleId}', [\App\Http\Controllers\User\UserFormationController::class, 'showModule']);
        Route::post('/{formationId}/modules/{moduleId}/complete', [\App\Http\Controllers\User\UserFormationController::class, 'completeModule']);
        Route::post('/{id}/purchase', [\App\Http\Controllers\User\UserFormationController::class, 'purchase']);
        
        // Gestion des formations créées par l'utilisateur
        Route::get('/my/list', [\App\Http\Controllers\User\UserFormationController::class, 'myFormations']);
        Route::post('/create', [\App\Http\Controllers\User\UserFormationController::class, 'store']);
        Route::put('/{id}/update', [\App\Http\Controllers\User\UserFormationController::class, 'update']);
        Route::post('/{id}/submit', [\App\Http\Controllers\User\UserFormationController::class, 'submit']);
        
        // Gestion des modules des formations créées par l'utilisateur
        Route::get('/my/{formationId}/modules', [\App\Http\Controllers\User\UserModuleController::class, 'index']);
        Route::post('/my/{formationId}/modules', [\App\Http\Controllers\User\UserModuleController::class, 'store']);
        Route::get('/my/{formationId}/modules/{moduleId}', [\App\Http\Controllers\User\UserModuleController::class, 'show']);
        Route::put('/my/{formationId}/modules/{moduleId}', [\App\Http\Controllers\User\UserModuleController::class, 'update']);
        Route::delete('/my/{formationId}/modules/{moduleId}', [\App\Http\Controllers\User\UserModuleController::class, 'destroy']);
        Route::post('/my/{formationId}/modules/reorder', [\App\Http\Controllers\User\UserModuleController::class, 'reorder']);
        Route::post('/my/{formationId}/modules/{moduleId}/submit', [\App\Http\Controllers\User\UserModuleController::class, 'submit']);
        
        // Route pour les statistiques de formation
        Route::get('/my/{formationId}/stats', [\App\Http\Controllers\User\FormationStatsController::class, 'getFormationStats']);
    
    });
    
    // Routes pour le signalement des statuts sociaux
    Route::get('/social-events/report-reasons', [App\Http\Controllers\SocialEventController::class, 'getReportReasons']);
    Route::get('/social-events/liked', [App\Http\Controllers\SocialEventController::class, 'getLikedStatuses']);
    Route::post('/social-events/{id}/report', [App\Http\Controllers\SocialEventController::class, 'report']);
    Route::get('/social-events/{id}/check-reported', [App\Http\Controllers\SocialEventController::class, 'checkReported']);
    
    // Routes pour les statuts sociaux
    Route::get('/social-events', [App\Http\Controllers\SocialEventController::class, 'index']);
    Route::get('/social-events/my-page', [App\Http\Controllers\SocialEventController::class, 'myPageSocialEvents']);
    Route::get('/social-events/followed-pages', [App\Http\Controllers\SocialEventController::class, 'followedPagesEvents']);
    Route::post('/social-events', [App\Http\Controllers\SocialEventController::class, 'store']);
    Route::get('/social-events/{id}', [App\Http\Controllers\SocialEventController::class, 'show']);
    Route::put('/social-events/{id}', [App\Http\Controllers\SocialEventController::class, 'update']);
    Route::delete('/social-events/{id}', [App\Http\Controllers\SocialEventController::class, 'destroy']);
    Route::post('/social-events/{id}/like', [App\Http\Controllers\SocialEventController::class, 'like']);
    Route::post('/social-events/{id}/unlike', [App\Http\Controllers\SocialEventController::class, 'unlike']);
    Route::post('/social-events/{id}/view', [App\Http\Controllers\SocialEventController::class, 'recordView']);
    Route::get('/social-events/{id}/views', [App\Http\Controllers\SocialEventController::class, 'getViewsCount']);
    
    Route::get('/offres-emploi/{id}', [App\Http\Controllers\OffreEmploiController::class, 'show']);
    Route::get('/offres-emploi/{id}/details', [App\Http\Controllers\OffreEmploiController::class, 'details']);
    Route::put('/offres-emploi/{id}', [App\Http\Controllers\OffreEmploiController::class, 'update']);
    Route::delete('/offres-emploi/{id}', [App\Http\Controllers\OffreEmploiController::class, 'destroy']);
    Route::put('/offres-emploi/{id}/etat', [App\Http\Controllers\OffreEmploiController::class, 'changeEtat']);
    Route::put('/offres-emploi/{id}/statut', [App\Http\Controllers\OffreEmploiController::class, 'changeStatut']);
    Route::post('/offres-emploi/{id}/boost', [App\Http\Controllers\OffreEmploiController::class, 'boost']);
    Route::get('/admin/offres-emploi/pending', [App\Http\Controllers\OffreEmploiController::class, 'getPendingJobs']);
    
    // Routes pour les interactions avec les offres d'emploi
    Route::post('/offres-emploi/{id}/like', [App\Http\Controllers\OffreEmploiController::class, 'like']);
    Route::get('/offres-emploi/{id}/check-like', [App\Http\Controllers\OffreEmploiController::class, 'checkLike']);
    Route::post('/offres-emploi/{id}/comment', [App\Http\Controllers\OffreEmploiController::class, 'comment']);
    Route::get('/offres-emploi/{id}/comments', [App\Http\Controllers\OffreEmploiController::class, 'getComments']);
    Route::delete('/offres-emploi/comments/{commentId}', [App\Http\Controllers\OffreEmploiController::class, 'deleteComment']);
    Route::post('/offres-emploi/{id}/share', [App\Http\Controllers\OffreEmploiController::class, 'share']);
    Route::get('/offres-emploi/{id}/shares', [App\Http\Controllers\OffreEmploiController::class, 'getShares']);
    
    // Routes pour les opportunités d'affaires
    Route::get('/opportunites-affaires', [App\Http\Controllers\OpportuniteAffaireController::class, 'index']);
    Route::post('/opportunites-affaires', [App\Http\Controllers\OpportuniteAffaireController::class, 'store']);
    Route::get('/opportunites-affaires/{id}', [App\Http\Controllers\OpportuniteAffaireController::class, 'show']);
    Route::get('/opportunites-affaires/{id}/details', [App\Http\Controllers\OpportuniteAffaireController::class, 'details']);
    Route::put('/opportunites-affaires/{id}', [App\Http\Controllers\OpportuniteAffaireController::class, 'update']);
    Route::delete('/opportunites-affaires/{id}', [App\Http\Controllers\OpportuniteAffaireController::class, 'destroy']);
    Route::put('/opportunites-affaires/{id}/statut', [App\Http\Controllers\OpportuniteAffaireController::class, 'changeStatut']);
    Route::put('/opportunites-affaires/{id}/etat', [App\Http\Controllers\OpportuniteAffaireController::class, 'changeEtat']);
    Route::post('/opportunites-affaires/{id}/boost', [App\Http\Controllers\OpportuniteAffaireController::class, 'boost']);
    Route::get('/admin/opportunites-affaires/pending', [App\Http\Controllers\OpportuniteAffaireController::class, 'getPendingOpportunities']);
    
    // Routes pour les interactions avec les opportunités d'affaires
    Route::post('/opportunites-affaires/{id}/like', [App\Http\Controllers\OpportuniteAffaireController::class, 'like']);
    Route::get('/opportunites-affaires/{id}/check-like', [App\Http\Controllers\OpportuniteAffaireController::class, 'checkLike']);
    Route::post('/opportunites-affaires/{id}/comment', [App\Http\Controllers\OpportuniteAffaireController::class, 'comment']);
    Route::get('/opportunites-affaires/{id}/comments', [App\Http\Controllers\OpportuniteAffaireController::class, 'getComments']);
    Route::delete('/opportunites-affaires/comments/{commentId}', [App\Http\Controllers\OpportuniteAffaireController::class, 'deleteComment']);
    Route::post('/opportunites-affaires/{id}/share', [App\Http\Controllers\OpportuniteAffaireController::class, 'share']);
    Route::get('/opportunites-affaires/{id}/shares', [App\Http\Controllers\OpportuniteAffaireController::class, 'getShares']);

    // Route pour vérifier le statut du pack de publication
    Route::get('/user-pack/status', [App\Http\Controllers\UserPackController::class, 'checkPackStatus']);

    // Routes pour les points bonus des utilisateurs
    Route::prefix('user/bonus-points')->group(function () {
        Route::get('/', [App\Http\Controllers\UserBonusPointController::class, 'getUserPoints']);
        Route::get('/history', [App\Http\Controllers\UserBonusPointController::class, 'getPointsHistory']);
        Route::post('/convert', [App\Http\Controllers\UserBonusPointController::class, 'convertPoints']);
    });

    //Transfert de fonds entre wallets

    // Routes pour les statuts sociaux
    Route::prefix('social-events')->group(function () {
        Route::get('/', [App\Http\Controllers\SocialEventController::class, 'index']);
        Route::get('/my-page', [App\Http\Controllers\SocialEventController::class, 'myPageSocialEvents']);
        Route::post('/', [App\Http\Controllers\SocialEventController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\SocialEventController::class, 'show']);
        Route::post('/{id}', [App\Http\Controllers\SocialEventController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\SocialEventController::class, 'destroy']);
        Route::post('/{id}/like', [App\Http\Controllers\SocialEventController::class, 'like']);
        Route::delete('/{id}/like', [App\Http\Controllers\SocialEventController::class, 'unlike']);
        Route::post('/{id}/share', [App\Http\Controllers\SocialEventController::class, 'share']);
    });
    Route::post('/funds-transfer', [App\Http\Controllers\User\WalletUserController::class, 'funds_transfer']);
    Route::get('/recipient-info/{account_id}', [App\Http\Controllers\User\WalletUserController::class, 'getRecipientInfo']);
    Route::get('/sending-fee-percentage', [App\Http\Controllers\User\WalletUserController::class, 'getSendingFeePercentage']);

    // Route pour récupérer le prix du boost
    Route::get('/boost-price', [\App\Http\Controllers\BoostPriceController::class, 'getBoostPrice']);
    
    // Routes pour les invitations de parrainage
    Route::get('/invitations/statistics', [\App\Http\Controllers\ReferralInvitationController::class, 'statistics']);
    Route::apiResource('/referral-invitations', \App\Http\Controllers\ReferralInvitationController::class);
    Route::post('/referral-invitations/{id}/resend', [\App\Http\Controllers\ReferralInvitationController::class, 'resend']);
    
    // Routes pour les invitations à témoigner
    Route::prefix('testimonials')->group(function () {
        // Récupérer les invitations actives pour l'utilisateur connecté
        Route::get('/prompts/active', [\App\Http\Controllers\TestimonialPromptController::class, 'getActivePrompts']);
        
        // Marquer une invitation comme affichée
        Route::post('/prompts/{id}/display', [\App\Http\Controllers\TestimonialPromptController::class, 'markAsDisplayed']);
        
        // Marquer une invitation comme cliquée
        Route::post('/prompts/{id}/click', [\App\Http\Controllers\TestimonialPromptController::class, 'markAsClicked']);
        
        // Soumettre un témoignage en réponse à une invitation
        Route::post('/prompts/{id}/submit', [\App\Http\Controllers\TestimonialPromptController::class, 'submitTestimonial']);
        
        // Décliner une invitation
        Route::post('/prompts/{id}/decline', [\App\Http\Controllers\TestimonialPromptController::class, 'declinePrompt']);
    });
});

// Routes admin
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Routes pour la modération des témoignages
    Route::prefix('testimonials')->group(function () {
        // Récupérer tous les témoignages avec pagination et filtres
        Route::get('/', [\App\Http\Controllers\Admin\TestimonialController::class, 'index']);
        
        // Compter les témoignages en attente
        Route::get('/count-pending', [\App\Http\Controllers\Admin\TestimonialController::class, 'countPending']);
        
        // Récupérer un témoignage spécifique
        Route::get('/{id}', [\App\Http\Controllers\Admin\TestimonialController::class, 'show']);
        
        // Approuver un témoignage
        Route::post('/{id}/approve', [\App\Http\Controllers\Admin\TestimonialController::class, 'approve']);
        
        // Rejeter un témoignage
        Route::post('/{id}/reject', [\App\Http\Controllers\Admin\TestimonialController::class, 'reject']);
        
        // Mettre en avant un témoignage
        Route::post('/{id}/feature', [\App\Http\Controllers\Admin\TestimonialController::class, 'feature']);
        
        // Retirer la mise en avant d'un témoignage
        Route::post('/{id}/unfeature', [\App\Http\Controllers\Admin\TestimonialController::class, 'unfeature']);
        
        // Supprimer un témoignage
        Route::delete('/{id}', [\App\Http\Controllers\Admin\TestimonialController::class, 'destroy']);
    });
    
    // Routes pour la gestion des frais de transaction
    Route::get('/transaction-fees', [\App\Http\Controllers\Admin\TransactionFeeController::class, 'index']);
    Route::post('/transaction-fees', [\App\Http\Controllers\Admin\TransactionFeeController::class, 'store']);
    Route::get('/transaction-fees/{id}', [\App\Http\Controllers\Admin\TransactionFeeController::class, 'show']);
    Route::put('/transaction-fees/{id}', [\App\Http\Controllers\Admin\TransactionFeeController::class, 'update']);
    Route::delete('/transaction-fees/{id}', [\App\Http\Controllers\Admin\TransactionFeeController::class, 'destroy']);
    Route::post('/transaction-fees/{id}/toggle-active', [\App\Http\Controllers\Admin\TransactionFeeController::class, 'toggleActive']);
    Route::post('/transaction-fees/update-from-api', [\App\Http\Controllers\Admin\TransactionFeeController::class, 'updateFromApi']);
    
    // Gestion des packs
    Route::apiResource('packs', \App\Http\Controllers\Admin\PackController::class);
    Route::patch('packs/{pack}/toggle-status', [\App\Http\Controllers\Admin\PackController::class, 'toggleStatus']);
    Route::post('packs/add', [\App\Http\Controllers\Admin\PackController::class, 'store']);

    //Routes pour la gestion des utilisateurs
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::patch('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);
    Route::patch('users/toggle-status/{userId}', [UserController::class, 'toggleStatus']);
    Route::get('users/{user}/referrals', [UserController::class, 'referrals']);
    Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    Route::get('/users/{id}/wallet', [UserController::class, 'getWalletData']);
    Route::get('/users/{id}/packs', [UserController::class, 'getUserPacks']);
    Route::get('/users/packs/{id}/stats', [UserController::class, 'getDetailedPackStats']);
    Route::get('/users/packs/{id}/referrals', [UserController::class, 'getPackReferrals']);
    Route::patch('/users/packs/{id}/toggle-status', [UserController::class, 'togglePackStatus']);

    // Routes pour la gestion des retraits (admin)
    Route::get('/withdrawal/requests', [WithdrawalController::class, 'getRequests']);
    Route::post('/withdrawal/requests/{id}/approve', [WithdrawalController::class, 'approve']);
    Route::post('/withdrawal/requests/{id}/reject', [WithdrawalController::class, 'reject']);
    Route::delete('/withdrawal/requests/{id}', [WithdrawalController::class, 'delete']);
    //Route::get('withdrawal-requests', [WithdrawalRequestController::class, 'index']);
    //Route::get('withdrawal-requests/{withdrawalRequest}', [WithdrawalRequestController::class, 'show']);
    //Route::post('withdrawal-requests/{withdrawalRequest}/process', [WithdrawalRequestController::class, 'process']);

    // Routes de gestion des commissions
    Route::get('/packs/{pack}/commission-rates', [PackController::class, 'getCommissionRates']);
    Route::post('/packs/{pack}/commission-rate', [PackController::class, 'updateCommissionRate']);

    // Routes de gestion des bonus sur délais
    Route::get('/packs/{packId}/bonus-rates', [PackController::class, 'getBonusRates']);
    Route::post('/packs/{packId}/bonus-rates', [PackController::class, 'storeBonusRate']);
    Route::put('/bonus-rates/{id}', [PackController::class, 'updateBonusRate']);
    Route::delete('/bonus-rates/{id}', [PackController::class, 'deleteBonusRate']);
    
    // Routes pour la gestion des wallets
    Route::get('/wallets/data', [WalletController::class, 'getWalletData']);
    Route::post('/admin/wallets/withdraw', [WalletController::class, 'withdraw']);

    // Routes pour les publicités
    Route::get('/advertisements', [App\Http\Controllers\Admin\AdvertisementValidationController::class, 'index']);
    Route::post('/advertisements/{id}/approve', [App\Http\Controllers\Admin\AdvertisementValidationController::class, 'approve']);
    Route::post('/advertisements/{id}/reject', [App\Http\Controllers\Admin\AdvertisementValidationController::class, 'reject']);
    Route::patch('/advertisements/{id}/status', [App\Http\Controllers\Admin\AdvertisementValidationController::class, 'updateStatus']);
    Route::patch('/advertisements/{id}/etat', [App\Http\Controllers\Admin\AdvertisementValidationController::class, 'updateEtat']);
    Route::delete('/advertisements/{id}', [App\Http\Controllers\Admin\AdvertisementValidationController::class, 'destroy']);
    
    // Routes pour les offres d'emploi
    Route::get('/job-offers', [App\Http\Controllers\Admin\JobOfferValidationController::class, 'index']);
    Route::post('/job-offers/{id}/approve', [App\Http\Controllers\Admin\JobOfferValidationController::class, 'approve']);
    Route::post('/job-offers/{id}/reject', [App\Http\Controllers\Admin\JobOfferValidationController::class, 'reject']);
    Route::patch('/job-offers/{id}/status', [App\Http\Controllers\Admin\JobOfferValidationController::class, 'updateStatus']);
    Route::patch('/job-offers/{id}/etat', [App\Http\Controllers\Admin\JobOfferValidationController::class, 'updateEtat']);
    Route::delete('/job-offers/{id}', [App\Http\Controllers\Admin\JobOfferValidationController::class, 'destroy']);
    
    // Routes pour les opportunités d'affaires
    Route::get('/business-opportunities', [App\Http\Controllers\Admin\BusinessOpportunityValidationController::class, 'index']);
    Route::post('/business-opportunities/{id}/approve', [App\Http\Controllers\Admin\BusinessOpportunityValidationController::class, 'approve']);
    
    // Routes pour les statuts sociaux
    Route::get('/social-events', [App\Http\Controllers\Admin\SocialEventAdminController::class, 'index']);
    Route::post('/social-events/{id}/approve', [App\Http\Controllers\Admin\SocialEventAdminController::class, 'approve']);
    Route::post('/social-events/{id}/reject', [App\Http\Controllers\Admin\SocialEventAdminController::class, 'reject']);
    Route::patch('/social-events/{id}/status', [App\Http\Controllers\Admin\SocialEventAdminController::class, 'updateStatus']);
    Route::delete('/social-events/{id}', [App\Http\Controllers\Admin\SocialEventAdminController::class, 'destroy']);
    Route::post('/business-opportunities/{id}/reject', [App\Http\Controllers\Admin\BusinessOpportunityValidationController::class, 'reject']);
    Route::patch('/business-opportunities/{id}/status', [App\Http\Controllers\Admin\BusinessOpportunityValidationController::class, 'updateStatus']);
    Route::patch('/business-opportunities/{id}/etat', [App\Http\Controllers\Admin\BusinessOpportunityValidationController::class, 'updateEtat']);
    Route::delete('/business-opportunities/{id}', [App\Http\Controllers\Admin\BusinessOpportunityValidationController::class, 'destroy']);

    Route::post('/wallet/funds-transfer', [App\Http\Controllers\Admin\WalletController::class, 'funds_transfer']);
    
    // Routes pour le tableau de bord administratif
    Route::get('/dashboard/cards', [App\Http\Controllers\Admin\AdminDashboardController::class, 'getCards']);
    Route::get('/dashboard/invitations', [App\Http\Controllers\Admin\AdminDashboardController::class, 'getInvitationsData']);
    Route::get('/dashboard/network', [App\Http\Controllers\Admin\AdminDashboardController::class, 'getNetworkOverview']);
    Route::get('/dashboard/members', [App\Http\Controllers\Admin\AdminDashboardController::class, 'getMembersManagement']);
    Route::get('/dashboard/referrals', [App\Http\Controllers\Admin\AdminDashboardController::class, 'getReferralSystem']);
    Route::get('/dashboard/transactions', [App\Http\Controllers\Admin\AdminDashboardController::class, 'getTransactions']);
    Route::get('/dashboard/pack-stats', [App\Http\Controllers\Admin\AdminDashboardController::class, 'getPackStats']);
    Route::get('/dashboard/data', [App\Http\Controllers\Admin\AdminDashboardController::class, 'getDashboardData']);
    
    // Routes pour la gestion des pays autorisés
    Route::get('/settings/countries', [App\Http\Controllers\Admin\CountrySettingsController::class, 'index']);
    Route::post('/settings/countries', [App\Http\Controllers\Admin\CountrySettingsController::class, 'update']);
    Route::put('/settings/countries/{countryCode}/toggle-status', [App\Http\Controllers\Admin\CountrySettingsController::class, 'toggleStatus']);
    Route::post('/settings/countries/toggle-restriction', [App\Http\Controllers\Admin\CountrySettingsController::class, 'toggleGlobalRestriction']);
    
    // Routes pour la gestion des taux de change
    Route::get('/exchange-rates', [App\Http\Controllers\Admin\ExchangeRatesController::class, 'index']);
    Route::post('/exchange-rates/update', [App\Http\Controllers\Admin\ExchangeRatesController::class, 'update']);

    // Routes pour la gestion des paramètres système
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index']);
    Route::get('/settings/key/{key}', [\App\Http\Controllers\Admin\SettingsController::class, 'getByKey']);
    Route::put('/settings/key/{key}', [\App\Http\Controllers\Admin\SettingsController::class, 'updateByKey']);
    
    // Routes pour la gestion des commissions
    Route::get('/commissions', [\App\Http\Controllers\Admin\CommissionController::class, 'index']);
    Route::get('/commissions/statistics', [\App\Http\Controllers\Admin\CommissionController::class, 'statistics']);
    Route::get('/commissions/packs', [\App\Http\Controllers\Admin\CommissionController::class, 'getPacks']);
    Route::get('/commissions/common-errors', [\App\Http\Controllers\Admin\CommissionController::class, 'commonErrors']);
    Route::get('/commissions/{id}', [\App\Http\Controllers\Admin\CommissionController::class, 'show']);
    Route::post('/commissions/{id}/retry', [\App\Http\Controllers\Admin\CommissionController::class, 'retry']);
    
    // Routes pour la gestion des finances
    Route::get('/finances', [\App\Http\Controllers\Admin\FinanceController::class, 'index']);
    Route::get('/finances/stats-by-type', [\App\Http\Controllers\Admin\FinanceController::class, 'getStatsByType']);
    Route::get('/finances/stats-by-period', [\App\Http\Controllers\Admin\FinanceController::class, 'getStatsByPeriod']);
    Route::get('/finances/transaction-types', [\App\Http\Controllers\Admin\FinanceController::class, 'getTransactionTypes']);
    Route::get('/finances/system-balance', [\App\Http\Controllers\Admin\FinanceController::class, 'getSystemBalance']);
    Route::get('/finances/summary', [\App\Http\Controllers\Admin\FinanceController::class, 'getSummary']);
    
    // Routes pour la gestion des points bonus
    Route::get('/finances/bonus-points-history', [\App\Http\Controllers\Admin\FinanceController::class, 'getBonusPointsHistory']);
    Route::get('/finances/bonus-points-stats', [\App\Http\Controllers\Admin\FinanceController::class, 'getBonusPointsStats']);
    Route::get('/finances/bonus-points-types', [\App\Http\Controllers\Admin\FinanceController::class, 'getBonusPointsTypes']);
    
    // Routes pour la gestion administrative des FAQ
    Route::get('/faqs', [\App\Http\Controllers\FaqController::class, 'index']);
    Route::post('/faqs', [\App\Http\Controllers\FaqController::class, 'store']);
    Route::get('/faqs/stats/views', [\App\Http\Controllers\FaqController::class, 'getViewStats']);
    Route::put('/faqs/{id}/order', [\App\Http\Controllers\FaqController::class, 'updateOrder']);
    Route::get('/faqs/{id}/related', [\App\Http\Controllers\FaqController::class, 'getRelatedFaqs']);
    Route::post('/faqs/{id}/related', [\App\Http\Controllers\FaqController::class, 'addRelatedFaq']);
    Route::delete('/faqs/{id}/related/{relatedId}', [\App\Http\Controllers\FaqController::class, 'removeRelatedFaq']);
    Route::put('/faqs/{id}', [\App\Http\Controllers\FaqController::class, 'update']);
    Route::delete('/faqs/{id}', [\App\Http\Controllers\FaqController::class, 'destroy']);
    
    // Routes pour la gestion des catégories de FAQ
    Route::post('/faq/categories', [\App\Http\Controllers\FaqController::class, 'storeCategory']);
    Route::put('/faq/categories/{id}', [\App\Http\Controllers\FaqController::class, 'updateCategory']);
    Route::delete('/faq/categories/{id}', [\App\Http\Controllers\FaqController::class, 'destroyCategory']);
    
    // Routes pour la gestion des formations (admin)
    Route::prefix('/formations')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\FormationController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\FormationController::class, 'store']);
        Route::get('/packs', [\App\Http\Controllers\Admin\FormationController::class, 'getPacks']);
        Route::get('/pending/count', [\App\Http\Controllers\Admin\FormationController::class, 'pendingCount']);
        Route::get('/{id}', [\App\Http\Controllers\Admin\FormationController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Admin\FormationController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Admin\FormationController::class, 'destroy']);
        Route::post('/{id}/review', [\App\Http\Controllers\Admin\FormationController::class, 'reviewFormation']);
        Route::post('/{id}/publish', [\App\Http\Controllers\Admin\FormationController::class, 'publish']);
        
        // Routes pour la gestion des modules (admin)
        Route::post('/{formationId}/modules', [\App\Http\Controllers\Admin\FormationModuleController::class, 'store']);
        Route::get('/{formationId}/modules', [\App\Http\Controllers\Admin\FormationModuleController::class, 'index']);
        Route::get('/{formationId}/modules/{moduleId}', [\App\Http\Controllers\Admin\FormationModuleController::class, 'show']);
        Route::delete('/{formationId}/modules/{moduleId}', [\App\Http\Controllers\Admin\FormationModuleController::class, 'destroy']);
        Route::post('/{formationId}/modules/reorder', [\App\Http\Controllers\Admin\FormationModuleController::class, 'reorder']);
        Route::post('/{formationId}/modules/{moduleId}/review', [\App\Http\Controllers\Admin\FormationModuleController::class, 'reviewModule']);
    });
});