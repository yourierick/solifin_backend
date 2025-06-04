<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Afficher la liste des rôles
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $roles
        ]);
    }

    /**
     * Afficher les détails d'un rôle
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $role = Role::with('permissions', 'users')->findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $role
        ]);
    }

    /**
     * Créer un nouveau rôle
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::create([
            'nom' => $request->nom,
            'slug' => $request->slug,
            'description' => $request->description,
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->attach($request->permissions);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rôle créé avec succès',
            'data' => $role->load('permissions')
        ], 201);
    }

    /**
     * Mettre à jour un rôle
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug,' . $id,
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $role->update([
            'nom' => $request->nom,
            'slug' => $request->slug,
            'description' => $request->description,
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rôle mis à jour avec succès',
            'data' => $role->load('permissions')
        ]);
    }

    /**
     * Supprimer un rôle
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        
        // Vérifier si des utilisateurs sont associés à ce rôle
        $usersCount = User::where('role_id', $id)->count();
        
        if ($usersCount > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de supprimer ce rôle car il est attribué à ' . $usersCount . ' utilisateur(s).'
            ], 422);
        }
        
        $role->permissions()->detach();
        $role->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Rôle supprimé avec succès'
        ]);
    }

    /**
     * Lister toutes les permissions disponibles
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissions()
    {
        $permissions = Permission::all();
        
        return response()->json([
            'status' => 'success',
            'data' => $permissions
        ]);
    }

    /**
     * Attribuer un rôle à un utilisateur
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRoleToUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $role = Role::findOrFail($request->role_id);
        
        // Mettre à jour le rôle de l'utilisateur
        $user->role_id = $role->id;
        
        // Si le rôle est gestionnaire, admin ou super-admin, définir is_admin à true
        if (in_array($role->slug, ['gestionnaire', 'admin', 'super-admin'])) {
            $user->is_admin = true;
        } else {
            $user->is_admin = false;
        }
        
        $user->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Rôle attribué avec succès',
            'data' => $user->load('roleRelation')
        ]);
    }
}
