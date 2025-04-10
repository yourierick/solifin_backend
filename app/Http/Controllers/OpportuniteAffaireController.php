<?php

namespace App\Http\Controllers;

use App\Models\OpportuniteAffaire;
use App\Models\OpportuniteAffaireLike;
use App\Models\OpportuniteAffaireComment;
use App\Models\OpportuniteAffaireShare;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OpportuniteAffaireController extends Controller
{
    /**
     * Récupérer toutes les opportunités d'affaires
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = OpportuniteAffaire::with('page.user');
        
        // Filtres
        if ($request->has('secteur')) {
            $query->where('secteur', 'like', '%' . $request->secteur . '%');
        }
        
        if ($request->has('localisation')) {
            $query->where('localisation', 'like', '%' . $request->localisation . '%');
        }
        
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }
        
        if ($request->has('etat')) {
            $query->where('etat', $request->etat);
        }
        
        $opportunites = $query->orderBy('created_at', 'desc')->paginate(10);
        
        return response()->json([
            'success' => true,
            'opportunites' => $opportunites
        ]);
    }

    /**
     * Récupérer les opportunités d'affaires en attente (pour admin)
     *
     * @return \Illuminate\Http\Response
     */
    public function getPendingOpportunities()
    {
        $opportunites = OpportuniteAffaire::with('page.user')
            ->where('statut', 'en_attente')
            ->orderBy('created_at', 'asc')
            ->paginate(10);
        
        return response()->json([
            'success' => true,
            'opportunites' => $opportunites
        ]);
    }

    /**
     * Créer une nouvelle opportunité d'affaire
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'secteur' => 'required|string|max:255',
            'description' => 'required|string',
            'benefices_attendus' => 'required|string',
            'investissement_requis' => 'nullable|numeric',
            'devise' => 'nullable|string|max:5',
            'duree_retour_investissement' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:5120', // 5MB max
            'localisation' => 'nullable|string|max:255',
            'contacts' => 'required|string',
            'email' => 'nullable|email',
            'conditions_participation' => 'nullable|string',
            'date_limite' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        // Vérifier si l'utilisateur a un pack de publication actif
        $packActif = false;
        if ($user->pack_de_publication_id) {
            $userPack = \App\Models\UserPack::where('user_id', $user->id)
                ->where('pack_id', $user->pack_de_publication_id)
                ->where('status', 'active')
                ->first();
            $packActif = (bool) $userPack;
        }
        
        if (!$packActif) {
            return response()->json([
                'success' => false,
                'message' => 'Votre pack de publication n\'est pas actif. Veuillez le réactiver pour publier.'
            ], 403);
        }
        
        // Récupérer ou créer la page de l'utilisateur
        $page = $user->page;
        if (!$page) {
            $page = Page::create([
                'user_id' => $user->id,
                'nombre_abonnes' => 0,
                'nombre_likes' => 0
            ]);
        }

        $data = $request->except(['image']);
        $data['page_id'] = $page->id;
        $data['statut'] = 'en_attente';
        $data['etat'] = 'disponible';
        
        // Traitement de l'image
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('opportunites/images', 'public');
            $data['image'] = $path;
        }

        $opportunite = OpportuniteAffaire::create($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Opportunité d\'affaire créée avec succès. Elle est en attente de validation.',
            'opportunite' => $opportunite
        ], 201);
    }

    /**
     * Récupérer une opportunité d'affaire spécifique
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $opportunite = OpportuniteAffaire::with('page.user')->findOrFail($id);
        
        // Ajouter l'URL complète de l'image si elle existe
        if ($opportunite->image) {
            $opportunite->image_url = asset('storage/' . $opportunite->image);
        }
        
        // Ajouter l'URL complète de la vidéo si elle existe
        if ($opportunite->video) {
            $opportunite->video_url = asset('storage/' . $opportunite->video);
        }
        
        return response()->json([
            'success' => true,
            'opportunite' => $opportunite
        ]);
    }

    /**
     * Mettre à jour une opportunité d'affaire
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $opportunite = OpportuniteAffaire::findOrFail($id);
        $user = Auth::user();
        
        // Vérifier si l'utilisateur est autorisé
        if (!$user->is_admin && $opportunite->page->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette opportunité d\'affaire.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'titre' => 'nullable|string|max:255',
            'secteur' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'benefices_attendus' => 'nullable|string',
            'investissement_requis' => 'nullable|numeric',
            'devise' => 'nullable|string|max:5',
            'duree_retour_investissement' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:5120',
            'localisation' => 'nullable|string|max:255',
            'contacts' => 'nullable|string',
            'email' => 'nullable|email',
            'conditions_participation' => 'nullable|string',
            'date_limite' => 'nullable|date',
            'statut' => 'nullable|in:en_attente,approuvé,rejeté,expiré',
            'etat' => 'nullable|in:disponible,terminé',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['image']);
        
        // Si l'utilisateur n'est pas admin, l'opportunité revient en attente si certains champs sont modifiés
        if (!$user->is_admin && $request->has(['titre', 'description', 'investissement_requis'])) {
            $data['statut'] = 'en_attente';
        }
        
        // Traitement de l'image
        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image si elle existe
            if ($opportunite->image) {
                Storage::disk('public')->delete($opportunite->image);
            }
            
            $path = $request->file('image')->store('opportunites/images', 'public');
            $data['image'] = $path;
        } else if ($request->has('remove_image') && $request->input('remove_image') == '1') {
            // Supprimer l'image sans la remplacer
            if ($opportunite->image) {
                Storage::disk('public')->delete($opportunite->image);
                $data['image'] = null;
            }
        }
        
        // Traitement de la vidéo
        if ($request->hasFile('video')) {
            // Supprimer l'ancienne vidéo si elle existe
            if ($opportunite->video) {
                Storage::disk('public')->delete($opportunite->video);
            }
            
            $path = $request->file('video')->store('opportunites/videos', 'public');
            $data['video'] = $path;
        } else if ($request->has('remove_video') && $request->input('remove_video') == '1') {
            // Supprimer la vidéo sans la remplacer
            if ($opportunite->video) {
                Storage::disk('public')->delete($opportunite->video);
                $data['video'] = null;
            }
        }
        
        $opportunite->update($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Opportunité d\'affaire mise à jour avec succès.',
            'opportunite' => $opportunite
        ]);
    }

    /**
     * Changer l'état d'une opportunité d'affaire (disponible/terminé)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function changeEtat(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'etat' => 'required|in:disponible,terminé',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $opportunite = OpportuniteAffaire::findOrFail($id);
        $user = Auth::user();
        
        // Vérifier si l'utilisateur est autorisé
        if (!$user->is_admin && $opportunite->page->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette opportunité d\'affaire.'
            ], 403);
        }
        
        $opportunite->update([
            'etat' => $request->etat
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'État de l\'opportunité d\'affaire mis à jour avec succès.',
            'opportunite' => $opportunite
        ]);
    }

    /**
     * Changer le statut d'une opportunité d'affaire (admin)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function changeStatut(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'statut' => 'required|in:en_attente,approuvé,rejeté,expiré',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        // Vérifier si l'utilisateur est admin
        if (!$user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les administrateurs peuvent changer le statut des opportunités d\'affaire.'
            ], 403);
        }
        
        $opportunite = OpportuniteAffaire::findOrFail($id);
        $opportunite->update([
            'statut' => $request->statut
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Statut de l\'opportunité d\'affaire mis à jour avec succès.',
            'opportunite' => $opportunite
        ]);
    }

    /**
     * Supprimer une opportunité d'affaire
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $opportunite = OpportuniteAffaire::findOrFail($id);
        $user = Auth::user();
        
        // Vérifier si l'utilisateur est autorisé
        if (!$user->is_admin && $opportunite->page->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette opportunité d\'affaire.'
            ], 403);
        }
        
        // Supprimer l'image associée
        if ($opportunite->image) {
            Storage::disk('public')->delete($opportunite->image);
        }
        
        $opportunite->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Opportunité d\'affaire supprimée avec succès.'
        ]);
    }
    
    /**
     * Liker une opportunité d'affaire
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function like($id)
    {
        $user = Auth::user();
        $opportunite = OpportuniteAffaire::findOrFail($id);
        
        // Vérifier si l'utilisateur a déjà liké cette opportunité
        $existingLike = OpportuniteAffaireLike::where('user_id', $user->id)
            ->where('opportunite_affaire_id', $id)
            ->first();
            
        if ($existingLike) {
            // Si l'utilisateur a déjà liké, on supprime le like (unlike)
            $existingLike->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Like retiré avec succès.',
                'liked' => false,
                'likes_count' => $opportunite->likes()->count()
            ]);
        }
        
        // Créer un nouveau like
        OpportuniteAffaireLike::create([
            'user_id' => $user->id,
            'opportunite_affaire_id' => $id
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Opportunité d\'affaire likée avec succès.',
            'liked' => true,
            'likes_count' => $opportunite->likes()->count()
        ]);
    }
    
    /**
     * Vérifier si l'utilisateur a liké une opportunité d'affaire
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function checkLike($id)
    {
        $user = Auth::user();
        $opportunite = OpportuniteAffaire::findOrFail($id);
        
        $liked = OpportuniteAffaireLike::where('user_id', $user->id)
            ->where('opportunite_affaire_id', $id)
            ->exists();
            
        return response()->json([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $opportunite->likes()->count()
        ]);
    }
    
    /**
     * Commenter une opportunité d'affaire
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function comment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:opportunite_affaire_comments,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = Auth::user();
        $opportunite = OpportuniteAffaire::findOrFail($id);
        
        $comment = OpportuniteAffaireComment::create([
            'user_id' => $user->id,
            'opportunite_affaire_id' => $id,
            'content' => $request->content,
            'parent_id' => $request->parent_id
        ]);
        
        // Charger les relations pour la réponse
        $comment->load('user');
        
        return response()->json([
            'success' => true,
            'message' => 'Commentaire ajouté avec succès.',
            'comment' => $comment,
            'comments_count' => $opportunite->comments()->count()
        ]);
    }
    
    /**
     * Récupérer les commentaires d'une opportunité d'affaire
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getComments($id)
    {
        $opportunite = OpportuniteAffaire::findOrFail($id);
        
        // Récupérer uniquement les commentaires parents (pas les réponses)
        $comments = OpportuniteAffaireComment::with(['user', 'replies.user'])
            ->where('opportunite_affaire_id', $id)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'comments' => $comments,
            'comments_count' => $opportunite->comments()->count()
        ]);
    }
    
    /**
     * Supprimer un commentaire
     *
     * @param  int  $commentId
     * @return \Illuminate\Http\Response
     */
    public function deleteComment($commentId)
    {
        $user = Auth::user();
        $comment = OpportuniteAffaireComment::findOrFail($commentId);
        
        // Vérifier si l'utilisateur est autorisé à supprimer ce commentaire
        if (!$user->is_admin && $comment->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer ce commentaire.'
            ], 403);
        }
        
        $opportuniteId = $comment->opportunite_affaire_id;
        $opportunite = OpportuniteAffaire::findOrFail($opportuniteId);
        
        // Supprimer également toutes les réponses à ce commentaire
        $comment->replies()->delete();
        $comment->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Commentaire supprimé avec succès.',
            'comments_count' => $opportunite->comments()->count()
        ]);
    }
    
    /**
     * Partager une opportunité d'affaire
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function share(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'nullable|string|max:500'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = Auth::user();
        $opportunite = OpportuniteAffaire::findOrFail($id);
        
        $share = OpportuniteAffaireShare::create([
            'user_id' => $user->id,
            'opportunite_affaire_id' => $id,
            'comment' => $request->comment
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Opportunité d\'affaire partagée avec succès.',
            'share' => $share,
            'shares_count' => $opportunite->shares()->count()
        ]);
    }
    
    /**
     * Récupérer les partages d'une opportunité d'affaire
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getShares($id)
    {
        $opportunite = OpportuniteAffaire::findOrFail($id);
        
        $shares = OpportuniteAffaireShare::with('user')
            ->where('opportunite_affaire_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'shares' => $shares,
            'shares_count' => $shares->count()
        ]);
    }
}
