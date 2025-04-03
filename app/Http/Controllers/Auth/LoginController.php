<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required'
        ]);

        // Déterminer si l'identifiant est un email ou un account_id
        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'account_id';
        
        // Rechercher l'utilisateur
        $user = User::where($loginField, $request->login)->first();
        
        // Vérifier si l'utilisateur existe et si le mot de passe est correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            // throw ValidationException::withMessages([
            //     'login' => ['Les identifiants fournis sont incorrects.'],
            // ]);
            return response()->json([
                'message' => 'Les identifiants fournis sont incorrects.'
            ]);
        }
        
        // Vérifier si le compte est actif
        if ($user->status !== 'active') {
            // throw ValidationException::withMessages([
            //     'login' => ['Ce compte a été désactivé, veuillez contacter l\'administrateur pour sa réactivation.'],
            // ]);
            return response()->json([
                'message' => 'Ce compte a été désactivé, veuillez contacter l\'administrateur pour sa réactivation.'
            ]);
        }
        
        // Authentifier l'utilisateur manuellement
        Auth::login($user, true);
        
        if (!$request->session()->has('_token')) {
            $request->session()->regenerate();
        }
        
        $user->picture = $user->getProfilePictureUrlAttribute();
        
        return response()->json([
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        // Déconnecter l'utilisateur avec le guard web
        Auth::guard('web')->logout();
        
        // Invalider la session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }
}