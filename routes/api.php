<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerificationController;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WithdrawalRequestController;
use App\Http\Controllers\Admin\AdvertisementValidationController;
use App\Http\Controllers\Admin\BusinessOpportunityValidationController;
use App\Http\Controllers\Admin\JobOfferValidationController;
use App\Http\Controllers\PackPurchaseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\CommissionRateController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\PackController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes publiques
Route::get('/packs', [App\Http\Controllers\HomeController::class, 'index']);
// Routes d'achat de pack
Route::get('/purchases/{sponsor_code}', [PackPurchaseController::class, 'show']);
// Route::post('/purchases/initiate', [PackPurchaseController::class, 'initiate']);
// Route::post('/purchases/{id}/process', [PackPurchaseController::class, 'process']);
//Route::post('/login', [LoginController::class, 'login']);


RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
});

Route::middleware('throttle:api')->group(function () {
    // Routes d'authentification
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/register/{packId}', [RegisterController::class, 'register']);

    // Routes de réinitialisation de mot de passe
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
    Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);

    // Routes de vérification d'email
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Email de vérification envoyé']);
    })->middleware(['auth:sanctum', 'throttle:6,1']);
});

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/network', [DashboardController::class, 'network']);
    Route::get('/dashboard/wallet', [DashboardController::class, 'wallet']);
    Route::get('/dashboard/packs', [DashboardController::class, 'packs']);

    // Routes de notification
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // Déconnexion
    Route::middleware('auth:sanctum')->post('/logout', [LoginController::class, 'logout']);
});

// Routes protégées par l'authentification
Route::middleware('auth:sanctum')->group(function () {
    // Routes pour les packs utilisateur
    Route::get('/user/packs', [\App\Http\Controllers\User\PackController::class, 'getUserPacks']);
    Route::post('/packs/{pack}/renew', [\App\Http\Controllers\User\PackController::class, 'renewPack']);
    Route::get('/packs/{pack}/download', [\App\Http\Controllers\User\PackController::class, 'downloadPack']);
    Route::get('/packs/{pack}/stats', [\App\Http\Controllers\User\PackController::class, 'getPackStats']);
    Route::get('/packs/{pack}/referrals', [\App\Http\Controllers\User\PackController::class, 'getPackReferrals']);
});

// Routes admin
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Gestion des packs
    Route::apiResource('packs', \App\Http\Controllers\Admin\PackController::class);
    Route::patch('packs/{pack}/toggle-status', [\App\Http\Controllers\Admin\PackController::class, 'toggleStatus']);
    Route::post('packs/add', [\App\Http\Controllers\Admin\PackController::class, 'store']);

    // Gestion des utilisateurs
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::patch('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);
    Route::patch('users/toggle-status/{userId}', [UserController::class, 'toggleStatus']);
    Route::get('users/{user}/referrals', [UserController::class, 'referrals']);

    // Routes pour la gestion des retraits (admin)
    Route::get('withdrawal-requests', [WithdrawalRequestController::class, 'index']);
    Route::get('withdrawal-requests/{withdrawalRequest}', [WithdrawalRequestController::class, 'show']);
    Route::post('withdrawal-requests/{withdrawalRequest}/process', [WithdrawalRequestController::class, 'process']);

    // Routes pour la validation des publicités
    Route::get('advertisements/pending', [AdvertisementValidationController::class, 'index']);
    Route::post('advertisements/{advertisement}/approve', [AdvertisementValidationController::class, 'approve']);
    Route::post('advertisements/{advertisement}/reject', [AdvertisementValidationController::class, 'reject']);
    Route::post('advertisements/{advertisement}/publish', [AdvertisementValidationController::class, 'publish']);
    Route::post('advertisements/{advertisement}/unpublish', [AdvertisementValidationController::class, 'unpublish']);
    Route::get('advertisements/{advertisement}/history', [AdvertisementValidationController::class, 'history']);

    // Business Opportunity Validations
    Route::get('business-opportunities/validations', [BusinessOpportunityValidationController::class, 'index']);
    Route::get('business-opportunities/validations/pending', [BusinessOpportunityValidationController::class, 'pending']);
    Route::post('business-opportunities/{opportunity}/validate', [BusinessOpportunityValidationController::class, 'validate']);

    // Job Offer Validations
    Route::get('job-offers/validations', [JobOfferValidationController::class, 'index']);
    Route::get('job-offers/validations/pending', [JobOfferValidationController::class, 'pending']);
    Route::post('job-offers/{offer}/validate', [JobOfferValidationController::class, 'validate']);

    // Routes de gestion des commissions
    Route::get('/packs/{pack}/commission-rates', [PackController::class, 'getCommissionRates']);
    Route::post('/packs/{pack}/commission-rate', [PackController::class, 'updateCommissionRate']);

    // Routes pour la gestion des wallets
    Route::get('/wallets/data', [WalletController::class, 'getWalletData']);
    Route::post('/admin/wallets/withdraw', [WalletController::class, 'withdraw']);
});
