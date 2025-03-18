<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Si c'est une requête API, retourner une réponse JSON
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Déjà authentifié',
                        'user' => Auth::user()
                    ]);
                }
                // Sinon, rediriger vers la page d'accueil
                return redirect('/');
            }
        }

        return $next($request);
    }
} 