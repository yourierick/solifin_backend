<?php

namespace App\Http\Controllers;

use App\Models\SocialEvent;
use App\Models\SocialEventLike;
use App\Models\SocialEventReport;
use App\Models\SocialEventView;
use App\Models\Page;
use App\Models\PageAbonnes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SocialEventController extends Controller
{
    /**
     * Afficher tous les statuts sociaux.
     */
    public function index()
    {
        $socialEvents = SocialEvent::with(['page', 'likes', 'views'])
            ->where('statut', 'approuvé')
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($socialEvents as $socialEvent) {
            $socialEvent->image_url = asset('storage/' . $socialEvent->image);
            $socialEvent->video_url = asset('storage/' . $socialEvent->video);
        }

        return response()->json($socialEvents);
    }
    
    /**
     * Afficher les statuts sociaux des pages auxquelles l'utilisateur courant est abonné.
     */
    public function followedPagesEvents()
    {
        $user = Auth::user();
        
        // Récupérer les IDs des pages auxquelles l'utilisateur est abonné
        $followedPagesIds = PageAbonnes::where('user_id', $user->id)
            ->pluck('page_id');

        $socialEvents = SocialEvent::with(['page', 'likes', 'views', 'user'])
            ->whereIn('page_id', $followedPagesIds)
            ->where('statut', 'approuvé')
            ->orderBy('created_at', 'desc')
            ->get();
            
        // Générer les URLs correctes pour les images et vidéos
        foreach ($socialEvents as $socialEvent) {
            if ($socialEvent->image) {
                $socialEvent->image_url = asset('storage/' . $socialEvent->image);
            }
            if ($socialEvent->video) {
                $socialEvent->video_url = asset('storage/' . $socialEvent->video);
            }

            if ($socialEvent->user->picture) {
                $socialEvent->user->picture_url = asset('storage/' . $socialEvent->user->picture);
            }
        }
            
        return response()->json($socialEvents);
    }

    /**
     * Afficher les statuts sociaux de la page de l'utilisateur connecté.
     */
    public function myPageSocialEvents()
    {
        $user = Auth::user();
        $page = $user->page;

        if (!$page) {
            return response()->json(['message' => 'Vous n\'avez pas de page'], 404);
        }

        $socialEvents = SocialEvent::with(['likes'])
            ->where('page_id', $page->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Générer les URLs correctes pour les images et vidéos
        foreach ($socialEvents as $socialEvent) {
            if ($socialEvent->image) {
                $socialEvent->image_url = asset('storage/' . $socialEvent->image);
            }
            if ($socialEvent->video) {
                $socialEvent->video_url = asset('storage/' . $socialEvent->video);
            }
        }
        
        // Préparer les données de l'utilisateur
        if ($user->picture) {
            $user->picture_url = asset('storage/' . $user->picture);
        }

        return response()->json([
            'statuses' => $socialEvents,
            'user' => $user
        ]);
    }

    /**
     * Créer un nouveau statut social.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'description' => 'nullable|string|max:1000',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'video' => 'nullable|mimes:mp4,mov,ogg,qt|max:5120', // 5 Mo maximum
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = Auth::user();
            $page = $user->page;

            if (!$page) {
                return response()->json(['message' => 'Vous n\'avez pas de page'], 404);
            }

            $socialEvent = new SocialEvent();
            $socialEvent->page_id = $page->id;
            $socialEvent->user_id = $user->id;
            $socialEvent->description = $request->description;
            $socialEvent->statut = 'approuvé';

            // Traitement de l'image
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('social_events/images', 'public');
                $socialEvent->image = $imagePath;
            }

            // Traitement de la vidéo
            if ($request->hasFile('video')) {
                $videoPath = $request->file('video')->store('social_events/videos', 'public');
                $socialEvent->video = $videoPath;
            }

            $socialEvent->save();

            return response()->json(['message' => 'Statut social créé avec succès', 'social_event' => $socialEvent], 201);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la création du statut social: ' . $e->getMessage());
            \Log::error('Erreur lors de la création du statut social: ' . $e->getTraceAsString());
            return response()->json(['message' => 'Erreur lors de la création du statut social', 'error' => $e->getMessage()], 500);
        }
    }   

    /**
     * Afficher un statut social spécifique.
     */
    public function show($id)
    {
        $socialEvent = SocialEvent::with(['page', 'likes', 'views'])->findOrFail($id);
        
        // Ajouter des informations supplémentaires pour l'utilisateur connecté
        $user = Auth::user();
        $socialEvent->is_liked_by_user = $socialEvent->isLikedByUser($user->id);
        $socialEvent->likes_count = $socialEvent->getLikesCount();
        $socialEvent->views_count = $socialEvent->views()->count(); // Ajouter le nombre de vues

        return response()->json($socialEvent);
    }

    /**
     * Mettre à jour un statut social.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'video' => 'nullable|mimes:mp4,mov,ogg,qt|max:5120', // 5 Mo maximum
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $socialEvent = SocialEvent::findOrFail($id);
        
        // Vérifier que l'utilisateur est propriétaire de la page
        $user = Auth::user();
        if ($socialEvent->page->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Mise à jour des champs
        if ($request->has('description')) {
            $socialEvent->description = $request->description;
        }

        // Traitement de l'image
        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image si elle existe
            if ($socialEvent->image) {
                Storage::disk('public')->delete($socialEvent->image);
            }
            
            $imagePath = $request->file('image')->store('social_events/images', 'public');
            $socialEvent->image = $imagePath;
        }

        // Traitement de la vidéo
        if ($request->hasFile('video')) {
            // Supprimer l'ancienne vidéo si elle existe
            if ($socialEvent->video) {
                Storage::disk('public')->delete($socialEvent->video);
            }
            
            $videoPath = $request->file('video')->store('social_events/videos', 'public');
            $socialEvent->video = $videoPath;
        }

        // Remettre le statut à "en_attente" si le contenu a été modifié
        $socialEvent->statut = 'en_attente';
        $socialEvent->save();

        return response()->json(['message' => 'Statut social mis à jour avec succès', 'social_event' => $socialEvent]);
    }

    /**
     * Supprimer un statut social.
     */
    public function destroy($id)
    {
        $socialEvent = SocialEvent::findOrFail($id);
        
        // Vérifier que l'utilisateur est propriétaire de la page
        $user = Auth::user();
        if ($socialEvent->page->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Supprimer les fichiers associés
        if ($socialEvent->image) {
            Storage::disk('public')->delete($socialEvent->image);
        }
        
        if ($socialEvent->video) {
            Storage::disk('public')->delete($socialEvent->video);
        }

        $socialEvent->delete();

        return response()->json(['message' => 'Statut social supprimé avec succès']);
    }
    
    /**
     * Signaler un statut social.
     */
    public function report(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $socialEvent = SocialEvent::findOrFail($id);
        
        // Vérifier si l'utilisateur a déjà signalé ce statut
        $existingReport = SocialEventReport::where('social_event_id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if ($existingReport) {
            return response()->json([
                'message' => 'Vous avez déjà signalé ce statut',
                'report' => $existingReport
            ], 422);
        }
        
        // Créer un nouveau signalement
        $report = new SocialEventReport();
        $report->social_event_id = $id;
        $report->user_id = $user->id;
        $report->reason = $request->reason;
        $report->description = $request->description;
        $report->status = 'pending';
        $report->save();
        
        return response()->json([
            'message' => 'Statut social signalé avec succès',
            'report' => $report
        ], 201);
    }
    
    /**
     * Obtenir les raisons de signalement disponibles.
     */
    public function getReportReasons()
    {
        $reasons = [
            'inappropriate_content' => 'Contenu inapproprié',
            'harassment' => 'Harcèlement',
            'spam' => 'Spam',
            'false_information' => 'Fausse information',
            'violence' => 'Violence',
            'hate_speech' => 'Discours haineux',
            'other' => 'Autre raison',
        ];

        return response()->json($reasons);
    }
    
    /**
     * Vérifier si l'utilisateur a déjà signalé un statut social.
     */
    public function checkReported($id)
    {
        $user = Auth::user();
        
        $report = SocialEventReport::where('social_event_id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        return response()->json([
            'reported' => $report ? true : false,
            'report' => $report
        ]);
    }

    /**
     * Aimer un statut social.
     */
    public function like($id)
    {
        $socialEvent = SocialEvent::findOrFail($id);
        $user = Auth::user();

        // Vérifier si l'utilisateur a déjà aimé ce statut
        $existingLike = SocialEventLike::where('user_id', $user->id)
            ->where('social_event_id', $socialEvent->id)
            ->first();

        if ($existingLike) {
            return response()->json(['message' => 'Vous avez déjà aimé ce statut'], 422);
        }

        // Créer un nouveau like
        $like = new SocialEventLike();
        $like->user_id = $user->id;
        $like->social_event_id = $socialEvent->id;
        $like->save();

        return response()->json([
            'message' => 'Statut aimé avec succès',
            'likes_count' => $socialEvent->getLikesCount()
        ]);
    }

    /**
     * Ne plus aimer un statut social.
     */
    public function unlike($id)
    {
        $socialEvent = SocialEvent::findOrFail($id);
        $user = Auth::user();

        // Supprimer le like existant
        $deleted = SocialEventLike::where('user_id', $user->id)
            ->where('social_event_id', $socialEvent->id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Vous n\'avez pas aimé ce statut'], 422);
        }

        return response()->json([
            'message' => 'Like retiré avec succès',
            'likes_count' => $socialEvent->getLikesCount()
        ]);
    }

    /**
     * Enregistrer une vue sur un statut social.
     */
    public function recordView($id)
    {
        $user = Auth::user();
        $socialEvent = SocialEvent::findOrFail($id);
        
        // Vérifier si l'utilisateur a déjà vu ce statut
        $existingView = SocialEventView::where('user_id', $user->id)
            ->where('social_event_id', $socialEvent->id)
            ->first();
            
        if (!$existingView) {
            // Créer une nouvelle vue
            SocialEventView::create([
                'user_id' => $user->id,
                'social_event_id' => $socialEvent->id,
                'viewed_at' => now(),
            ]);
        } else {
            // Mettre à jour la date de vue
            $existingView->viewed_at = now();
            $existingView->save();
        }
        
        // Retourner le nombre total de vues
        $viewsCount = $socialEvent->views()->count();
        
        return response()->json([
            'message' => 'Vue enregistrée avec succès',
            'views_count' => $viewsCount
        ]);
    }
    
    /**
     * Obtenir le nombre de vues pour un statut social.
     */
    public function getViewsCount($id)
    {
        $socialEvent = SocialEvent::findOrFail($id);
        $viewsCount = $socialEvent->views()->count();
        
        return response()->json([
            'views_count' => $viewsCount
        ]);
    }
    
    /**
     * Obtenir les statuts aimés par l'utilisateur courant.
     */
    public function getLikedStatuses()
    {
        $user = Auth::user();
        
        $likedStatuses = SocialEventLike::where('user_id', $user->id)
            ->with('socialEvent')
            ->get()
            ->pluck('socialEvent');
        
        return response()->json($likedStatuses);
    }
}
