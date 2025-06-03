<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\Pack;
use App\Models\UserPack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FormationController extends Controller
{
    /**
     * Afficher la liste des formations.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Formation::with(['creator:id,name,email', 'packs:id,name'])
            ->where(function($query) {
                // Exclure les formations qui sont à la fois en statut "draft" ET de type "user"
                $query->whereNot(function($q) {
                    $q->where('status', 'draft')
                      ->where('type', 'user');
                });
            });
        
        // Filtrer par statut si spécifié
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filtrer par type si spécifié
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        // Recherche par titre ou description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $formations = $query->orderBy('created_at', 'desc')->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => $formations
        ]);
    }

    /**
     * Afficher les détails d'une formation.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $formation = Formation::with([
            'creator:id,name,email', 
            'packs:id,name', 
            'modules' => function($query) {
                $query->orderBy('order');
            }
        ])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $formation
        ]);
    }

    /**
     * Créer une nouvelle formation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail' => 'nullable|image|max:2048', // Max 2MB
            'packs' => 'required|array',
            'packs.*' => 'exists:packs,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Traitement de l'image de couverture
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('formations/thumbnails', 'public');
        }
        
        // Création de la formation
        $formation = Formation::create([
            'title' => $request->title,
            'category' => $request->category,
            'description' => $request->description,
            'thumbnail' => $thumbnailPath ? asset('storage/' . $thumbnailPath) : null,
            'status' => 'draft', // Les formations créées par l'admin sont publiées directement
            'type' => 'admin',
            'created_by' => auth()->id(),
        ]);
        
        // Association des packs
        $formation->packs()->attach($request->packs);
        
        return response()->json([
            'success' => true,
            'message' => 'Formation créée avec succès',
            'data' => $formation
        ], 201);
    }

    /**
     * Mettre à jour une formation.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $formation = Formation::findOrFail($id);

        $validationRules = [
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail' => 'nullable|image|max:2048', // Max 2MB
            'status' => 'nullable|in:draft,pending,published,rejected',
        ];
        
        // Ajouter la règle pour les packs si c'est une formation admin
        if ($formation->type === 'admin') {
            $validationRules['packs'] = 'required|array';
            $validationRules['packs.*'] = 'exists:packs,id';
        }
        
        $validator = Validator::make($request->all(), $validationRules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Formulaire invalide'
            ], 422);
        }
        
        // Traitement de l'image de couverture
        if ($request->hasFile('thumbnail')) {
            // Supprimer l'ancienne image si elle existe
            if ($formation->thumbnail) {
                $oldPath = str_replace(asset('storage/'), '', $formation->thumbnail);
                Storage::disk('public')->delete($oldPath);
            }
            
            $thumbnailPath = $request->file('thumbnail')->store('formations/thumbnails', 'public');
            $formation->thumbnail = asset('storage/' . $thumbnailPath);
        }
        
        // Mise à jour de la formation
        $formation->title = $request->title;
        $formation->category = $request->category;
        $formation->description = $request->description;
        
        if ($request->has('status')) {
            $formation->status = $request->status;
        }
        
        $formation->save();
        
        // Mise à jour des packs associés
        $formation->packs()->sync($request->packs);
        
        return response()->json([
            'success' => true,
            'message' => 'Formation mise à jour avec succès',
            'data' => $formation
        ]);
    }

    /**
     * Supprimer une formation.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $formation = Formation::findOrFail($id);
        
        // Supprimer l'image de couverture si elle existe
        if ($formation->thumbnail) {
            $path = str_replace(asset('storage/'), '', $formation->thumbnail);
            Storage::disk('public')->delete($path);
        }
        
        // Supprimer tous les fichiers associés aux modules
        foreach ($formation->modules as $module) {
            if ($module->file_url) {
                $path = str_replace(asset('storage/'), '', $module->file_url);
                Storage::disk('public')->delete($path);
            }
        }
        
        $formation->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Formation supprimée avec succès'
        ]);
    }

    /**
     * Approuver ou rejeter une formation créée par un utilisateur.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reviewFormation(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:published,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $formation = Formation::findOrFail($id);
        
        // Vérifier que la formation est bien en attente de validation
        if ($formation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cette formation n\'est pas en attente de validation'
            ], 400);
        }
        
        $formation->status = $request->status;
        
        if ($request->status === 'rejected' && $request->has('rejection_reason')) {
            $formation->rejection_reason = $request->rejection_reason;
        } else if ($request->status === 'published') {
            // Si la formation est approuvée, effacer toute raison de rejet précédente
            $formation->rejection_reason = null;
        }
        
        $formation->save();
        
        // TODO: Envoyer une notification à l'utilisateur
        
        return response()->json([
            'success' => true,
            'message' => $request->status === 'published' 
                ? 'Formation approuvée avec succès' 
                : 'Formation rejetée avec succès',
            'data' => $formation
        ]);
    }

    /**
     * Obtenir la liste des packs pour le formulaire de création/édition.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPacks()
    {
        $packs = Pack::select('id', 'name')->where('status', true)->get();
        
        return response()->json([
            'success' => true,
            'data' => $packs
        ]);
    }

    /**
     * Publier une formation créée par un administrateur.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function publish($id)
    {
        try {
            $formation = Formation::where('type', 'admin')->findOrFail($id);
            
            // Vérifier que la formation est en brouillon
            if ($formation->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette formation ne peut pas être publiée car elle n\'est pas en brouillon'
                ], 400);
            }
            
            // Vérifier que la formation a au moins un module
            if ($formation->modules()->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de publier une formation sans module'
                ], 400);
            }
            
            // Mettre à jour le statut de la formation
            $formation->status = 'published';
            $formation->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Formation publiée avec succès',
                'data' => $formation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la publication de la formation: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Compter le nombre de formations en attente de validation.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pendingCount()
    {
        try {
            // Compter les formations en attente (status = pending)
            $count = Formation::where('status', 'pending')->count();
            
            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du comptage des formations en attente: ' . $e->getMessage()
            ], 500);
        }
    }
}
