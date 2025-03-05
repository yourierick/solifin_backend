<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\User;

// Mettre la route de vérification d'email en dehors des groupes de middleware
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id) {
    $user = User::findOrFail($id);
    
    if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
        throw new \Illuminate\Auth\Access\AuthorizationException('Le lien de vérification est invalide');
    }

    if ($user->hasVerifiedEmail()) {
        return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?already_verified=1');
    }

    $user->markEmailAsVerified();
    
    \Log::info('Email vérifié pour l\'utilisateur ' . $user->email);
    $redirectPath = $user->is_admin ? '/admin' : '/dashboard';
    \Log::info('Redirection vers ' . env('FRONTEND_URL') . $redirectPath);
    return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?verified=1');
})->middleware(['throttle:6,1'])->name('verification.verify');

// Route pour renvoyer l'email de vérification
Route::get('/email/resend-verification/{email}', function ($email) {
    $user = \App\Models\User::where('email', $email)->firstOrFail();
    $user->sendEmailVerificationNotification();
    
    return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?verification=sent');
})->middleware(['throttle:6,1'])->name('verification.resend');