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
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials, true)) {
            if (!$request->session()->has('_token')) {
                $request->session()->regenerate();
            }
            
            $user = Auth::user();
            $user->picture = $user->getProfilePictureUrlAttribute();
            
            return response()->json([
                'user' => $user
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['Les identifiants fournis sont incorrects.'],
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