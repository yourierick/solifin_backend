<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\Pack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FormationModuleController extends Controller
{
    /**
     * Afficher la liste des modules d'une formation.
     *
     * @param int $formationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($formationId)
    {
        $formation = Formation::findOrFail($formationId);
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
        $formation = Formation::findOrFail($formationId);
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
        $formation = Formation::findOrFail($formationId);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'content' => 'required|string',
            'type' => 'required|string|in:text,video,pdf,quiz',
            'video_url' => 'nullable|url|required_if:type,video',
            'file' => 'nullable|file|max:10240|required_if:type,pdf', // Max 10MB
            'duration' => 'nullable|integer|min:1'
        ]);
        
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
            // Le statut a été supprimé du modèle FormationModule
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
        $formation = Formation::findOrFail($formationId);
        $module = $formation->modules()->findOrFail($moduleId);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'content' => 'required|string',
            'type' => 'required|string|in:text,video,pdf,quiz',
            'video_url' => 'nullable|url|required_if:type,video',
            'file' => 'nullable|file|max:10240', // Max 10MB
            'duration' => 'nullable|integer|min:1',
            'packs' => 'nullable|array',
            'packs.*' => 'exists:packs,id',
        ]);
        
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
        $formation = Formation::findOrFail($formationId);
        $module = $formation->modules()->findOrFail($moduleId);
        
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
        $validator = Validator::make($request->all(), [
            'modules' => 'required|array',
            'modules.*.id' => 'required|exists:formation_modules,id',
            'modules.*.order' => 'required|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $formation = Formation::findOrFail($formationId);
        
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

    // La méthode reviewModule a été supprimée car nous ne gérons plus le statut au niveau des modules
}
