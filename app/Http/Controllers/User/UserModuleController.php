<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\FormationModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserModuleController extends Controller
{
    /**
     * Afficher la liste des modules d'une formation créée par l'utilisateur.
     *
     * @param int $formationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($formationId)
    {
        $user = Auth::user();
        $formation = Formation::where('created_by', $user->id)->findOrFail($formationId);
        $modules = $formation->modules()->orderBy('order')->get();
        
        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }

    /**
     * Afficher les détails d'un module.
     *
     * @param int $formationId
     * @param int $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($formationId, $moduleId)
    {
        $user = Auth::user();
        $formation = Formation::where('created_by', $user->id)->findOrFail($formationId);
        $module = $formation->modules()->findOrFail($moduleId);
        
        return response()->json([
            'success' => true,
            'data' => $module
        ]);
    }

    /**
     * Créer un nouveau module pour une formation.
     *
     * @param Request $request
     * @param int $formationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $formationId)
    {
        $user = Auth::user();
        $formation = Formation::where('created_by', $user->id)->findOrFail($formationId);
        
        // Vérifier si la formation peut être modifiée (seulement si elle est en brouillon ou rejetée)
        if (!in_array($formation->status, ['draft', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas ajouter de modules à cette formation dans son état actuel'
            ], 400);
        }
        
        // Préparer les règles de validation
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|string|in:text,video,pdf,quiz',
            'video_url' => 'nullable|url|required_if:type,video',
            'file' => 'nullable|file|max:10240|required_if:type,pdf', // Max 10MB
            'duration' => 'nullable|integer|min:1',
        ];
        
        // Ajouter la règle pour le contenu uniquement si le type est text ou quiz
        if ($request->type === 'text' || $request->type === 'quiz') {
            $rules['content'] = 'required|string';
        } else {
            $rules['content'] = 'nullable';
        }
        
        $validator = Validator::make($request->all(), $rules);

        \Log::info($validator->errors());
        \Log::info($request->all());
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Traitement du fichier si présent
        $fileUrl = null;
        if ($request->hasFile('file')) {
            $fileUrl = $request->file('file')->store('formations/modules/files', 'public');
            $fileUrl = asset('storage/' . $fileUrl);
        }
        
        // Déterminer l'ordre du nouveau module
        $lastOrder = $formation->modules()->max('order') ?? 0;
        
        // Création du module
        $module = new FormationModule([
            'title' => $request->title,
            'description' => $request->description,
            'content' => $request->content,
            'type' => $request->type,
            'video_url' => $request->video_url,
            'file_url' => $fileUrl,
            'duration' => $request->duration,
            'order' => $lastOrder + 1,
        ]);
        
        $formation->modules()->save($module);
        
        return response()->json([
            'success' => true,
            'message' => 'Module créé avec succès',
            'data' => $module
        ], 201);
    }

    /**
     * Mettre à jour un module.
     *
     * @param Request $request
     * @param int $formationId
     * @param int $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $formationId, $moduleId)
    {
        $user = Auth::user();
        $formation = Formation::where('created_by', $user->id)->findOrFail($formationId);
        $module = $formation->modules()->findOrFail($moduleId);
        
        // Vérifier si le module peut être modifié (seulement si la formation est en brouillon ou rejetée)
        if (!in_array($formation->status, ['draft', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas modifier ce module dans l\'état actuel de la formation'
            ], 400);
        }
        
        // Préparer les règles de validation
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|string|in:text,video,pdf,quiz',
            'video_url' => 'nullable|url|required_if:type,video',
            'file' => 'nullable|file|max:10240', // Max 10MB
            'duration' => 'nullable|integer|min:1',
        ];
        
        // Ajouter la règle pour le contenu uniquement si le type est text ou quiz
        if ($request->type === 'text' || $request->type === 'quiz') {
            $rules['content'] = 'required|string';
        } else {
            $rules['content'] = 'nullable';
        }
        
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Traitement du fichier si présent
        if ($request->hasFile('file')) {
            // Supprimer l'ancien fichier si existant
            if ($module->file_url) {
                $oldPath = str_replace(asset('storage/'), '', $module->file_url);
                Storage::disk('public')->delete($oldPath);
            }
            
            $fileUrl = $request->file('file')->store('formations/modules/files', 'public');
            $module->file_url = asset('storage/' . $fileUrl);
        }
        
        // Mise à jour du module
        $module->title = $request->title;
        $module->description = $request->description;
        $module->content = $request->content;
        $module->type = $request->type;
        
        if ($request->type === 'video') {
            $module->video_url = $request->video_url;
        }
        
        if ($request->has('duration')) {
            $module->duration = $request->duration;
        }
        
        $module->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Module mis à jour avec succès',
            'data' => $module
        ]);
    }

    /**
     * Supprimer un module.
     *
     * @param int $formationId
     * @param int $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($formationId, $moduleId)
    {
        $user = Auth::user();
        $formation = Formation::where('created_by', $user->id)->findOrFail($formationId);
        $module = $formation->modules()->findOrFail($moduleId);
        
        // Vérifier si le module peut être supprimé (seulement si la formation est en brouillon ou rejetée)
        if (!in_array($formation->status, ['draft', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer ce module dans l\'état actuel de la formation'
            ], 400);
        }
        
        // Supprimer le fichier associé si existant
        if ($module->file_url) {
            $path = str_replace(asset('storage/'), '', $module->file_url);
            Storage::disk('public')->delete($path);
        }
        
        // Réorganiser les ordres des modules restants
        $formation->modules()
            ->where('order', '>', $module->order)
            ->decrement('order');
        
        $module->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Module supprimé avec succès'
        ]);
    }

    /**
     * Réorganiser l'ordre des modules.
     *
     * @param Request $request
     * @param int $formationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function reorder(Request $request, $formationId)
    {
        $user = Auth::user();
        $formation = Formation::where('created_by', $user->id)->findOrFail($formationId);
        
        // Vérifier si la formation peut être modifiée (seulement si elle est en brouillon ou rejetée)
        if (!in_array($formation->status, ['draft', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas réorganiser les modules dans l\'état actuel de la formation'
            ], 400);
        }
        
        \Log::info($request->all());
        $validator = Validator::make($request->all(), [
            'modules' => 'required|array',
            'modules.*.id' => 'required|exists:formation_modules,id',
            'modules.*.order' => 'required|integer|min:0',
        ]);

        \Log::info($validator->errors());
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        foreach ($request->modules as $moduleData) {
            $module = $formation->modules()->findOrFail($moduleData['id']);
            $module->order = $moduleData['order'];
            $module->save();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Ordre des modules mis à jour avec succès'
        ]);
    }

    /**
     * Soumettre un module pour validation.
     *
     * @param int $formationId
     * @param int $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit($formationId, $moduleId)
    {
        $user = Auth::user();
        $formation = Formation::where('created_by', $user->id)->findOrFail($formationId);
        $module = $formation->modules()->findOrFail($moduleId);
        
        // Vérifier si le module peut être soumis (seulement s'il est en brouillon ou rejeté)
        if (!in_array($module->status, ['draft', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Ce module ne peut pas être soumis dans son état actuel'
            ], 400);
        }
        $module->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Module soumis avec succès et en attente de validation',
            'data' => $module
        ]);
    }
}
