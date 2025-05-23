<?php

namespace App\Http\Controllers;

use App\Models\Publicite;
use App\Models\PubliciteLike;
use App\Models\PubliciteComment;
use App\Models\PubliciteShare;
use App\Models\PageAbonnes;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\TransactionFee;
use App\Models\ExchangeRates;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet;
use App\Models\WalletSystem;
use App\Models\Setting;

class PubliciteController extends Controller
{
    /**
     * Récupérer toutes les publicités
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Publicite::with('page.user');
        
        // Filtres
        if ($request->has('categorie')) {
            $query->where('categorie', $request->categorie);
        }
        
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }
        
        if ($request->has('etat')) {
            $query->where('etat', $request->etat);
        }
        
        $publicites = $query->orderBy('created_at', 'desc')->paginate(10);
        
        return response()->json([
            'success' => true,
            'publicites' => $publicites
        ]);
    }

    /**
     * Récupérer les publicités en attente (pour admin)
     *
     * @return \Illuminate\Http\Response
     */
    public function getPendingAds()
    {
        $publicites = Publicite::with('page.user')
            ->where('statut', 'en_attente')
            ->orderBy('created_at', 'asc')
            ->paginate(10);
        
        return response()->json([
            'success' => true,
            'publicites' => $publicites
        ]);
    }

    /**
     * Créer une nouvelle publicité
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->all();
        
        // Convertir conditions_livraison de JSON à tableau si nécessaire
        if (isset($data['conditions_livraison']) && is_string($data['conditions_livraison'])) {
            try {
                $data['conditions_livraison'] = json_decode($data['conditions_livraison'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $data['conditions_livraison'] = [$data['conditions_livraison']];
                }
            } catch (\Exception $e) {
                $data['conditions_livraison'] = [$data['conditions_livraison']];
            }
        }
        
        try {
            // Si l'image est un tableau vide, la supprimer des données
            if (isset($data['image']) && is_array($data['image']) && empty($data['image'])) {
                unset($data['image']);
            }
            
            // Si la vidéo est un tableau vide, la supprimer des données
            if (isset($data['video']) && is_array($data['video']) && empty($data['video'])) {
                unset($data['video']);
            }
            
            $validator = Validator::make($data, [
                'pays' => 'required|string|max:255',
                'ville' => 'required|string|max:255',
                'type' => 'required|in:publicité,annonce',
                'categorie' => 'required|in:produit,service',
                'sous_categorie' => 'required|in:location de véhicule,location de maison,réservation,livraison,vente,sous-traitance,autre à préciser',
                'autre_sous_categorie' => 'nullable|string|required_if:sous_categorie,autre à préciser',
                'titre' => 'required|string|max:255',
                'description' => 'required|string',
                'image' => 'nullable|image|max:2048', // 2MB max, optionnel
                'video' => 'nullable|file|mimes:mp4,mov,avi|max:10240', // 10MB max
                'contacts' => 'required|string',
                'email' => 'nullable|email',
                'adresse' => 'nullable|string',
                'besoin_livreurs' => 'nullable|in:OUI,NON',
                'conditions_livraison' => 'nullable|array',
                'point_vente' => 'nullable|string',
                'quantite_disponible' => 'nullable|integer',
                'prix_unitaire_vente' => 'required|numeric',
                'devise' => 'nullable|string|max:5',
                'commission_livraison' => 'nullable|in:OUI,NON',
                'prix_unitaire_livraison' => 'nullable|numeric',
                'lien' => 'nullable|url',
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
    
            // Utiliser les données déjà traitées pour conditions_livraison
            $requestData = $request->except(['image', 'video', 'conditions_livraison']);
            
            // Ajouter les conditions de livraison traitées
            if (isset($data['conditions_livraison'])) {
                $requestData['conditions_livraison'] = $data['conditions_livraison'];
            }
            
            // Utiliser $requestData pour la suite
            $data = $requestData;
            $data['page_id'] = $page->id;
            $data['statut'] = 'en_attente';
            $data['etat'] = 'disponible';
            
            // Définir la durée d'affichage basée sur le pack de publication de l'utilisateur
            if ($user->pack_de_publication) {
                $data['duree_affichage'] = $user->pack_de_publication->duree_publication_en_jour;
            } else {
                // Valeur par défaut si le pack n'est pas disponible
                $data['duree_affichage'] = 1; // 1 jour par défaut
            }
            
            // Traitement des fichiers
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('publicites/images', 'public');
                $data['image'] = $path;
            }
            
            if ($request->hasFile('video')) {
                $path = $request->file('video')->store('publicites/videos', 'public');
                $data['video'] = $path;
            }
    
            $publicite = Publicite::create($data);

            // Créer une notification pour l'administrateur
            $admins = \App\Models\User::where('is_admin', true)->get();
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\PublicationSubmitted([
                    'type' => $publicite->type === "publicité" ? "Publicité" : "Annonce",
                    'id' => $publicite->id,
                    'titre' => "Publicité, titre: " . $publicite->titre,
                    'message' => 'est en attente d\'approbation.',
                    'user_id' => $user->id,
                    'user_name' => $user->name
                ]));
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Publicité créée avec succès. Elle est en attente de validation.',
                'publicite' => $publicite
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer une publicité spécifique
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $publicite = Publicite::with('page.user')->findOrFail($id);
        
        // Ajouter l'URL complète de l'image si elle existe
        if ($publicite->image) {
            $publicite->image_url = asset('storage/' . $publicite->image);
        }
        
        // Ajouter l'URL complète de la vidéo si elle existe
        if ($publicite->video) {
            $publicite->video_url = asset('storage/' . $publicite->video);
        } 

        $publicite->post_type = $publicite->type;
        
        return response()->json([
            'success' => true,
            'publicite' => $publicite,
        ]);
    }

    /**
     * Mettre à jour une publicité
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = $request->all();

        \Log::info($data);
        
        // Traitement du champ conditions_livraison
        if (isset($data['conditions_livraison'])) {
            if (is_string($data['conditions_livraison'])) {
                if (empty($data['conditions_livraison']) || $data['conditions_livraison'] === '[]') {
                    $data['conditions_livraison'] = [];
                } else {
                    try {
                        $decoded = json_decode($data['conditions_livraison'], true);
                        
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $data['conditions_livraison'] = $decoded;
                        } else {
                            // Si ce n'est pas un JSON valide, essayer de traiter comme une chaîne simple
                            if (!empty(trim($data['conditions_livraison']))) {
                                $data['conditions_livraison'] = [$data['conditions_livraison']];
                            } else {
                                $data['conditions_livraison'] = [];
                            }
                        }
                    } catch (\Exception $e) {
                        $data['conditions_livraison'] = [];
                    }
                }
            } elseif (is_array($data['conditions_livraison'])) {
                // Déjà un tableau, ne rien faire
            } else {
                // Si ce n'est ni une chaîne ni un tableau, initialiser comme tableau vide
                $data['conditions_livraison'] = [];
            }
        } else {
            // Si non défini, initialiser comme tableau vide
            $data['conditions_livraison'] = [];
        }
        
        try {
            $publicite = Publicite::findOrFail($id);
            $user = Auth::user();
            
            // Vérifier si l'utilisateur est autorisé
            // if (!$user->is_admin && $publicite->page->user_id !== $user->id) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Vous n\'êtes pas autorisé à modifier cette publicité.'
            //     ], 403);
            // }

            $validator = Validator::make($request->all(), [
                'pays' => 'nullable|string|max:255',
                'ville' => 'nullable|string|max:255',
                'type' => 'nullable|in:publicité,annonce',
                'categorie' => 'nullable|in:produit,service',
                'sous_categorie' => 'nullable|in:location de véhicule,location de maison,réservation,livraison,vente,sous-traitance,autre à préciser',
                'autre_sous_categorie' => 'nullable|string|required_if:sous_categorie,autre à préciser',
                'titre' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|max:5120',
                'video' => 'nullable|file|mimes:mp4,mov,avi|max:20480',
                'contacts' => 'nullable|string',
                'email' => 'nullable|email',
                'adresse' => 'nullable|string',
                'besoin_livreurs' => 'nullable|in:OUI,NON',
                'conditions_livraison' => 'nullable',
                'point_vente' => 'nullable|string',
                'quantite_disponible' => 'nullable|integer',
                'prix_unitaire_vente' => 'required|numeric',
                'devise' => 'nullable|string|max:5',
                'commission_livraison' => 'nullable|in:OUI,NON',
                'prix_unitaire_livraison' => 'nullable|numeric',
                'lien' => 'nullable|url',
            ]);
            
            \Log::info($request->all());
            if ($validator->fails()) {
                \Log::error($validator->errors());
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->except(['image', 'video']);
            
            // Si l'utilisateur n'est pas admin, la publicité revient en attente si certains champs sont modifiés
            if (!$user->is_admin && $request->has(['titre', 'description', 'prix_unitaire_vente'])) {
                $data['statut'] = 'en_attente';
            }
            
            // Traitement des fichiers
            if ($request->hasFile('image')) {
                // Supprimer l'ancienne image si elle existe
                if ($publicite->image) {
                    Storage::disk('public')->delete($publicite->image);
                }
                
                $path = $request->file('image')->store('publicites/images', 'public');
                $data['image'] = $path;
            } else if ($request->has('remove_image') && $request->remove_image == '1') {
                // Supprimer l'image sans la remplacer
                if ($publicite->image) {
                    Storage::disk('public')->delete($publicite->image);
                    $data['image'] = null;
                }
            }
            
            if ($request->hasFile('video')) {
                // Supprimer l'ancienne vidéo si elle existe
                if ($publicite->video) {
                    Storage::disk('public')->delete($publicite->video);
                }
                
                $path = $request->file('video')->store('publicites/videos', 'public');
                $data['video'] = $path;
            } else if ($request->has('remove_video') && $request->remove_video == '1') {
                // Supprimer la vidéo sans la remplacer
                if ($publicite->video) {
                    Storage::disk('public')->delete($publicite->video);
                    $data['video'] = null;
                }
            }
            
            $publicite->update($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Publicité mise à jour avec succès.',
                'publicite' => $publicite
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "une erreur s'est produite lors de la mise à jour des données"
            ], 500);
        }
    }

    /**
     * Changer l'état d'une publicité (disponible/terminé)
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

        $publicite = Publicite::findOrFail($id);
        $user = Auth::user();
        
        // Vérifier si l'utilisateur est autorisé
        if (!$user->is_admin && $publicite->page->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette publicité.'
            ], 403);
        }
        
        $publicite->update([
            'etat' => $request->etat
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'État de la publicité mis à jour avec succès.',
            'publicite' => $publicite
        ]);
    }

    /**
     * Changer le statut d'une publicité (admin)
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
                'message' => 'Seuls les administrateurs peuvent changer le statut des publicités.'
            ], 403);
        }
        
        $publicite = Publicite::findOrFail($id);
        $publicite->update([
            'statut' => $request->statut
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Statut de la publicité mis à jour avec succès.',
            'publicite' => $publicite
        ]);
    }

    /**
     * Supprimer une publicité
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $publicite = Publicite::findOrFail($id);
        $user = Auth::user();
        
        // Vérifier si l'utilisateur est autorisé
        if (!$user->is_admin && $publicite->page->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette publicité.'
            ], 403);
        }
        
        // Supprimer les fichiers associés
        if ($publicite->image) {
            Storage::disk('public')->delete($publicite->image);
        }
        
        if ($publicite->video) {
            Storage::disk('public')->delete($publicite->video);
        }
        
        $publicite->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Publicité supprimée avec succès.'
        ]);
    }
    
    /**
     * Liker une publicité
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function like($id)
    {
        $user = Auth::user();
        $publicite = Publicite::findOrFail($id);
        
        // Vérifier si l'utilisateur a déjà liké cette publicité
        $existingLike = PubliciteLike::where('user_id', $user->id)
            ->where('publicite_id', $id)
            ->first();
            
        if ($existingLike) {
            // Si l'utilisateur a déjà liké, on supprime le like (unlike)
            $existingLike->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Like retiré avec succès.',
                'liked' => false,
                'likes_count' => $publicite->likes()->count()
            ]);
        }
        
        // Créer un nouveau like
        PubliciteLike::create([
            'user_id' => $user->id,
            'publicite_id' => $id
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Publicité likée avec succès.',
            'liked' => true,
            'likes_count' => $publicite->likes()->count()
        ]);
    }
    
    /**
     * Vérifier si l'utilisateur a liké une publicité
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function checkLike($id)
    {
        $user = Auth::user();
        $publicite = Publicite::findOrFail($id);
        
        $liked = PubliciteLike::where('user_id', $user->id)
            ->where('publicite_id', $id)
            ->exists();
            
        return response()->json([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $publicite->likes()->count()
        ]);
    }
    
    /**
     * Commenter une publicité
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function comment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:publicite_comments,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = Auth::user();
        $publicite = Publicite::findOrFail($id);
        
        $comment = PubliciteComment::create([
            'user_id' => $user->id,
            'publicite_id' => $id,
            'content' => $request->content,
            'parent_id' => $request->parent_id
        ]);
        
        // Charger les relations pour la réponse
        $comment->load('user');
        
        return response()->json([
            'success' => true,
            'message' => 'Commentaire ajouté avec succès.',
            'comment' => $comment,
            'comments_count' => $publicite->comments()->count()
        ]);
    }
    
    /**
     * Récupérer les commentaires d'une publicité
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getComments($id)
    {
        $publicite = Publicite::findOrFail($id);
        
        // Récupérer uniquement les commentaires parents (pas les réponses)
        $comments = PubliciteComment::with(['user', 'replies.user'])
            ->where('publicite_id', $id)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'comments' => $comments,
            'comments_count' => $publicite->comments()->count()
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
        $comment = PubliciteComment::findOrFail($commentId);
        
        // Vérifier si l'utilisateur est autorisé à supprimer ce commentaire
        if (!$user->is_admin && $comment->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer ce commentaire.'
            ], 403);
        }
        
        $publiciteId = $comment->publicite_id;
        $publicite = Publicite::findOrFail($publiciteId);
        
        // Supprimer également toutes les réponses à ce commentaire
        $comment->replies()->delete();
        $comment->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Commentaire supprimé avec succès.',
            'comments_count' => $publicite->comments()->count()
        ]);
    }
    
    /**
     * Partager une publicité
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
        $publicite = Publicite::findOrFail($id);
        
        $share = PubliciteShare::create([
            'user_id' => $user->id,
            'publicite_id' => $id,
            'comment' => $request->comment
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Publicité partagée avec succès.',
            'share' => $share,
            'shares_count' => $publicite->shares()->count()
        ]);
    }
    
    /**
     * Récupérer les partages d'une publicité
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getShares($id)
    {
        $publicite = Publicite::findOrFail($id);
        
        $shares = PubliciteShare::with('user')
            ->where('publicite_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'shares' => $shares,
            'shares_count' => $shares->count()
        ]);
    }

    public function details($id)
    {
        $userId = Auth::id();
        $post = Publicite::with(['page', 'page.user'])
            ->findOrFail($id);

        $post->user->picture = asset('storage/' . $post->user->picture);

        // Vérifier si l'utilisateur est abonné à cette page
        $post->is_subscribed = PageAbonnes::where('user_id', $userId)
        ->where('page_id', $post->page_id)
        ->exists();

        // Compter les likes pour cette publication
        $post->likes_count = PubliciteLike::where('publicite_id', $post->id)->count();

        // Type de publication
        $post->post_type = $post->type;
        $post->type = "publicites";

                    
        // Vérifier si l'utilisateur a aimé cette publication
        $post->is_liked = PubliciteLike::where('publicite_id', $post->id)
            ->where('user_id', $userId)
            ->exists();

        // Ajouter l'URL complète de l'image si elle existe
        if ($post->image) {
            $post->image_url = asset('storage/' . $post->image);
        }
        
        // Ajouter l'URL complète de la vidéo si elle existe
        if ($post->video) {
            $post->video_url = asset('storage/' . $post->video);
        }
        
        // Compter les commentaires pour cette publication
        $post->comments_count = PubliciteComment::where('publicite_id', $post->id)->count();
        
        // Récupérer les 3 derniers commentaires
        $post->comments = PubliciteComment::where('publicite_id', $post->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get()
            ->map(function($comment) use ($userId) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at,
                    'created_at_formatted' => $comment->created_at->diffForHumans(),
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'profile_picture' => $comment->user->picture ? asset('storage/' . $comment->user->picture) : null
                    ],
                    'likes_count' => 0, // À implémenter si les commentaires ont des likes
                    'is_liked' => false // À implémenter si les commentaires ont des likes
                ];
            });
        
        // Compter les partages pour cette publication
        $post->shares_count = PubliciteShare::where('publicite_id', $post->id)->count();
        
        return response()->json([
            'success' => true,
            'post' => $post
        ]);
    }

    /**
     * Booster une publicité (augmenter sa durée d'affichage)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function boost(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'days' => 'required|integer|min:1',
                'paymentMethod' => 'required|string',
                'paymentType' => 'required|string',
                'paymentOption' => 'required|string',
                'currency' => 'required|string',
                'fees' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Récupérer la publicité
            $publicite = Publicite::findOrFail($id);
            
            // Vérifier que la publicité est approuvée et disponible
            if ($publicite->statut !== 'approuvé' || $publicite->etat !== 'disponible') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette publicité ne peut pas être boostée car elle n\'est pas approuvée ou disponible.'
                ], 400);
            }
            
            // Vérifier que l'utilisateur est propriétaire de la publicité
            $user = Auth::user();
            $page = Page::findOrFail($publicite->page_id);
            
            if ($page->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à booster cette publicité.'
                ], 403);
            }
            
            // Récupérer les données de paiement
            $paymentMethod = $request->paymentMethod;
            $paymentOption = $request->paymentOption;
            $paymentType = $request->paymentType;
            $days = $request->days;
            $currency = $request->currency;
            
            // Calculer le montant en fonction du nombre de jours
            // Récupérer le paramètre de prix du boost
            $setting = Setting::where('key', 'boost_price')->first();
            
            // Valeur par défaut si le paramètre n'est pas défini
            $defaultPrice = 1;
            
            // Si le paramètre existe, utiliser sa valeur, sinon utiliser la valeur par défaut
            $pricePerDay = $setting ? $setting->value : $defaultPrice;
            $amount = $pricePerDay * $days;

            // Recalculer les frais globaux de transaction
            $globalFeePercentage = (float) Setting::getValue('transfer_fee_percentage', 0);
            $globalFees = ((float)$amount) * ($globalFeePercentage / 100);
            
            // Récupérer les frais de transaction depuis la base de données
            $transactionFeeModel = TransactionFee::where('payment_method', $paymentMethod)
                ->where('is_active', true);
            
            $transactionFee = $transactionFeeModel->first();
            
            // Calculer les frais de transaction
            $specificFees = 0;
            if ($transactionFee) {
                $specificFees = $transactionFee->calculateTransferFee((float) $amount, $currency);
            }
            
            // Montant total incluant les frais
            $totalAmount = $amount + $globalFees;
            
            // Si la devise n'est pas en USD, convertir le montant en USD (devise de base)
            $amountInUSD = $totalAmount;
            // if ($currency !== 'USD') {
            //     try {
            //         // Récupérer le taux de conversion depuis la BD ou un service
            //         $exchangeRate = ExchangeRates::where('currency', $currency)
            //             ->where('target_currency', 'USD')
            //             ->first();
                    
            //         if ($exchangeRate) {
            //             $amountInUSD = $totalAmount * $exchangeRate->rate;
            //             $amountInUSD = round($amountInUSD, 2);
            //             $globalFees = $globalFees * $exchangeRate->rate;
            //             $globalFees = round($globalFees, 2);
            //             $specificFees = $specificFees * $exchangeRate->rate;
            //             $specificFees = round($specificFees, 2);
            //         } else {
            //            return response()->json([
            //                 "succes" => false,
            //                 "message" => "La conversion de dévise a échoué, veuillez utiliser le $"    
            //            ]);
            //         }
            //     } catch (\Exception $e) {
            //         return response()->json([
            //             "succes" => false,
            //             "message" => "La conversion de dévise a échoué, veuillez utiliser le $"    
            //         ]);
            //     }
            // }
            
            $netAmountInUSD = round($amountInUSD - $globalFees, 0);
            $amountInUSDWithoutSpecificFees = round($amountInUSD - $specificFees, 2);

            // Vérifier que le montant net est suffisant pour couvrir le coût du pack
            $boostPrice = $pricePerDay * $days;
            if ($netAmountInUSD < $boostPrice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant payé est insuffisant pour couvrir le coût du pack'
                ], 400);
            }
            
            DB::beginTransaction();
            
            // Si paiement par wallet
            if ($paymentMethod === 'solifin-wallet') {
                // Vérifier le solde du wallet
                $wallet = $user->wallet;
                
                if (!$wallet || $wallet->balance < $amountInUSD) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solde insuffisant dans votre wallet.'
                    ], 400);
                }
                
                // Débiter le wallet
                $wallet->withdrawFunds($amountInUSD, 'purchase', 'completed', [
                    'Opération' => "Boost de publication",
                    'publication_id' => $publicite->id,
                    'publication_type' => 'publicité',
                    'Durée' => $days . " jours",
                    'Montant total' => $amount,
                    'Dévise' => $currency,
                    'Frais' => $globalFees,
                    'Méthode de paiement' => $paymentMethod,
                    'Type de paiement' => $paymentType
                ]);
            } else {
                // Pour les autres méthodes de paiement, enregistrer la demande
                // Dans un système réel, il faudrait intégrer avec un service de paiement externe
                // Dans un système réel, rediriger vers la passerelle de paiement
            }
            
            // Ajouter le montant au wallet system (sans les frais)
            $walletsystem = WalletSystem::first();
            if (!$walletsystem) {
                $walletsystem = WalletSystem::create(['balance' => 0]);
            }
            
            if ($paymentMethod === "solifin-wallet") {
                $walletsystem->transactions()->create([
                    'wallet_system_id' => $walletsystem->id,
                    'amount' => $amountInUSD,
                    'type' => 'sales',
                    'status' => 'completed',
                    'metadata' => [
                        "user" => $user->name, 
                        "Opération" => "Boost de publicité",
                        "Durée" => $validator['days'], 
                        "Méthode de paiement" => $paymentMethod, 
                        "Dévise" => $validator['currency'],
                        "Montant total" => $amount,
                        "Type de paiement" => $paymentType,
                        "Méthode de paiement" => $paymentMethod,
                        "Frais globaux" => $globalFees
                    ]
                ]);
            }else {
                $walletsystem->addFunds($amountInUSDWithoutSpecificFees, 'sales', 'completed', [
                    'Opération' => "Boost de publication",
                    'user' => $user->name,
                    'publication_id' => $publicite->id,
                    'Type de publication' => 'publicité',
                    'Durée' => $days . " jours",
                    'Montant total' => $amount,
                    'Frais globaux' => $globalFees,
                    'Frais spécific' => $specificFees,
                    'Dévise' => $currency,
                    'Méthode de paiement' => $paymentMethod,
                    'Type de paiement' => $paymentType
                ]);
            }
            
            // Mettre à jour la durée d'affichage
            $publicite->duree_affichage = ($publicite->duree_affichage ?? 0) + $days;
            $publicite->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Publicité boostée avec succès pour ' . $days . ' jours supplémentaires.',
                'publication' => $publicite,
                'payment_details' => [
                    'amount' => $amount,
                    'fees' => $fees,
                    'total' => $totalAmount,
                    'currency' => $currency
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Erreur lors du boost de la publicité: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du boost de la publicité: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Estimation du montant en USD
     * 
     * @param float $amount Montant à convertir
     * @param string $currency Devise d'origine
     * @return float Montant estimé en USD
     */
    private function estimateUSDAmount($amount, $currency)
    {
        // Taux de conversion approximatifs (à mettre à jour régulièrement)
        $rates = [
            'EUR' => 1.09,
            'GBP' => 1.27,
            'CAD' => 0.73,
            'AUD' => 0.66,
            'JPY' => 0.0067,
            'CHF' => 1.12,
            'CNY' => 0.14,
            'INR' => 0.012,
            'BRL' => 0.19,
            'ZAR' => 0.054,
            'NGN' => 0.00065,
            'GHS' => 0.071,
            'XOF' => 0.0017,
            'XAF' => 0.0017,
            'CDF' => 0.0017,
        ];
        
        if (isset($rates[$currency])) {
            return $amount * $rates[$currency];
        }
        
        // Si la devise n'est pas dans la liste, utiliser un taux par défaut
        return $amount;
    }
    
    /**
     * Convertit un montant d'une devise en USD
     * 
     * @param float $amount Montant à convertir
     * @param string $currency Devise d'origine
     * @return float Montant en USD
     */
    private function convertToUSD($amount, $currency)
    {
        if ($currency === 'USD') {
            return $amount;
        }
        
        try {
            // Récupérer le taux de conversion depuis la BD
            $exchangeRate = ExchangeRates::where('currency', $currency)->where('target_currency', 'USD')->first();
            if ($exchangeRate) {
                return $amount * $exchangeRate->rate;
            }
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la conversion de devise: ' . $e->getMessage());
        }
        
        // Si l'API échoue, utiliser l'estimation
        return $this->estimateUSDAmount($amount, $currency);
    }
}
