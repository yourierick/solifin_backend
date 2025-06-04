<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Récupère les permissions de l'utilisateur connecté
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPermissions()
    {
        $user = Auth::user();
        
        // Si l'utilisateur n'est pas connecté
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non authentifié'], 401);
        }
        
        // Si l'utilisateur est un super admin ou un admin sans rôle spécifique
        if ($user->isSuperAdmin() || ($user->is_admin && !$user->role_id)) {
            // Retourner toutes les permissions
            $permissions = Permission::all();
            return response()->json([
                'isSuperAdmin' => true,
                'permissions' => $permissions
            ]);
        }
        
        // Pour les autres utilisateurs, récupérer les permissions via leur rôle
        if ($user->role_id) {
            $permissions = $user->roleRelation->permissions;
            return response()->json([
                'isSuperAdmin' => false,
                'permissions' => $permissions
            ]);
        }
        
        // Utilisateur sans permissions
        return response()->json([
            'isSuperAdmin' => false,
            'permissions' => []
        ]);
    }
}
