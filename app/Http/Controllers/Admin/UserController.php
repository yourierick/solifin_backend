<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Pack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class UserController extends BaseController
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Display a listing of the users.
     */
    public function index(Request $request)
    {
        try {
            \Log::info('Début de UserController@index');
            
            $query = User::query()
                ->select('users.*') // Sélectionner explicitement les colonnes de la table users
                ->withCount('referrals')
                ->with(['packs' => function ($query) {
                    $query->select('user_packs.id', 'user_packs.user_id', 'user_packs.pack_id');
                }]);

            //\Log::info('Nombre total d\'utilisateurs avant filtres: ' . $query->count());

            // Appliquer les filtres
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%");
                });
                //\Log::info('Filtre de recherche appliqué: ' . $search);
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                $query->where('users.status', $status);
                \Log::info('Filtre de statut appliqué: ' . $status);
            }

            if ($request->filled('has_pack')) {
                $hasPack = $request->input('has_pack');
                if ($hasPack == '1') {
                    $query->has('packs');
                } elseif ($hasPack == '0') {
                    $query->doesntHave('packs');
                }
                //\Log::info('Filtre de pack appliqué: ' . $hasPack);
            }

            //\Log::info('Requête SQL avant pagination: ' . $query->toSql());
            //\Log::info('Paramètres de la requête: ' . json_encode($query->getBindings()));

            $users = $query->paginate(10);
            
            //\Log::info('Nombre d\'utilisateurs après filtres: ' . $users->total());
            //\Log::info('Données des utilisateurs: ' . json_encode($users));

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur dans UserController@index: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des utilisateurs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        try {
            $user = User::with(['packs', 'referrals'])
                ->withCount('referrals')
                ->findOrFail($id);

            //$referralStats = $user->getReferralCounts();
            $referralStats = $user->getFilleulsStats();


            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'stats' => $referralStats
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur dans UserController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des détails de l\'utilisateur'
            ], 500);
        }
    }

    /**
     * Récupère la liste des filleuls d'un utilisateur
     */
    public function referrals(User $user, Request $request)
    {
        try {
            $packId = $request->input('pack_id');
            $referrals = $user->getReferrals($packId);
            
            return response()->json([
                'success' => true,
                'data' => $referrals,
                'message' => 'Liste des filleuls récupérée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans UserController@referrals: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des filleuls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit(User $user)
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
                'phone' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:255'],
                'status' => ['required', 'string', 'in:active,inactive,suspended'],
                'password' => ['nullable', 'min:8', 'confirmed'],
                'is_admin' => ['boolean'],
            ]);

            // Mettre à jour les informations de base
            $user->fill($validated);

            // Mettre à jour le mot de passe si fourni
            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            // Gérer explicitement is_admin car il peut être false
            if ($request->has('is_admin')) {
                // Empêcher la désactivation du dernier admin
                if (!$request->boolean('is_admin') && $user->is_admin && User::where('is_admin', true)->count() === 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Impossible de retirer les droits du dernier administrateur'
                    ], 422);
                }
                $user->is_admin = $request->boolean('is_admin');
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès',
                'data' => $user
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Erreur dans UserController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour de l\'utilisateur'
            ], 500);
        }
    }

    public function destroy(User $user)
    {
        try {
            DB::beginTransaction();
            
            $user->delete(); // Cette méthode appellera notre méthode delete() personnalisée

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user status between active and inactive
     */
    public function toggleStatus($userId)
    {
        try {
            $user = User::find($userId);
            
            // Empêcher la désactivation du dernier administrateur
            if ($user->is_admin && User::where('is_admin', true)->count() == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de désactiver le dernier administrateur'
                ], 422);
            }

            // Toggle le statut
            $user->status = $user->status === 'active' ? 'inactive' : 'active';
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Statut de l\'utilisateur mis à jour avec succès',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur dans UserController@toggleStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du statut'
            ], 500);
        }
    }

    public function network(User $user)
    {
        try {
            $referrals = $user->referrals()
                ->with(['packs', 'wallet'])
                ->paginate(15);

            $networkStats = [
                'direct_referrals' => $user->referrals()->count(),
                'total_network' => $user->getAllDownlines()->count(),
                'active_referrals' => $user->referrals()->where('status', 'active')->count(),
                'total_commissions' => $user->wallet->transactions()
                    ->where('type', 'credit')
                    ->sum('amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'referrals' => $referrals,
                    'networkStats' => $networkStats
                ],
                'message' => 'Réseau de l\'utilisateur récupéré avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans UserController@network: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du réseau',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function transactions(User $user)
    {
        try {
            $transactions = $user->wallet->transactions()
                ->latest()
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'message' => 'Transactions de l\'utilisateur récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans UserController@transactions: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur
     */
    public function resetPassword($id, Request $request)
    {
        try {
            $request->validate([
                'new_password' => 'required|string|min:8',
                'admin_password' => 'required|string',
            ]);

            // Vérifier le mot de passe de l'administrateur
            $admin = auth()->user();
            if (!Hash::check($request->admin_password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe administrateur incorrect'
                ], 401);
            }

            // Trouver l'utilisateur et réinitialiser son mot de passe
            $user = User::findOrFail($id);
            $user->password = Hash::make($request->new_password);
            $user->save();

            // Journaliser l'action
            Log::info("Mot de passe réinitialisé pour l'utilisateur ID: {$id} par l'administrateur ID: {$admin->id}");

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans UserController@resetPassword: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}