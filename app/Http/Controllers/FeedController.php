<?php

namespace App\Http\Controllers;

use App\Models\OffreEmploi;
use App\Models\OpportuniteAffaire;
use App\Models\Publicite;
use App\Models\Page;
use App\Models\PageAbonnes;

// Modèles spécifiques pour les likes, commentaires et partages
use App\Models\OffreEmploiLike;
use App\Models\OffreEmploiComment;
use App\Models\OffreEmploiShare;
use App\Models\OpportuniteAffaireLike;
use App\Models\OpportuniteAffaireComment;
use App\Models\OpportuniteAffaireShare;
use App\Models\PubliciteLike;
use App\Models\PubliciteComment;
use App\Models\PubliciteShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    /**
     * Récupérer les publications pour le fil d'actualité
     * Pagination infinie avec chargement par lots
     */
    public function index(Request $request)
    {
        $type = $request->input('type', 'publicites');
        $lastId = $request->input('last_id', 0);
        $limit = $request->input('limit', 10);
        $userId = Auth::id();
        
        // Récupérer les IDs des pages auxquelles l'utilisateur est abonné
        $subscribedPageIds = PageAbonnes::where('user_id', $userId)
            ->pluck('page_id')
            ->toArray();

        // Préparer la requête en fonction du type de publication
        switch ($type) {
            case 'offres-emploi':
                $query = OffreEmploi::with(['page', 'page.user'])
                    ->where('statut', 'approuvé')
                    ->where('etat', 'disponible')
                    ->latest();
                break;
                
            case 'opportunites-affaires':
                $query = OpportuniteAffaire::with(['page', 'page.user'])
                    ->where('statut', 'approuvé')
                    ->where('etat', 'disponible')
                    ->latest();
                break;
                
            default: // 'publicites'
                $query = Publicite::with(['page', 'page.user'])
                    ->where('statut', 'approuvé')
                    ->where('etat', 'disponible')
                    ->latest();
                break;
        }

        // Pour l'infinite scroll
        if ($lastId > 0) {
            $query->where('id', '<', $lastId);
        }
        
        // Prioriser les publications des pages auxquelles l'utilisateur est abonné
        // mais afficher aussi les autres publications
        $query->orderByRaw('CASE WHEN page_id IN (' . (empty($subscribedPageIds) ? '0' : implode(',', $subscribedPageIds)) . ') THEN 0 ELSE 1 END');

        $publications = $query->take($limit)->get();

        // Transformer les publications pour avoir un format uniforme
        $posts = $publications->map(function ($publication) use ($userId, $type) {
            $post = new \stdClass();
            $post->id = $publication->id;
            $post->type = $type;
            $post->title = $publication->titre;
            $post->content = $publication->description ?? '';
            $post->external_link = $publication->lien ?? null;
            $post->statut = $publication->statut;
            $post->etat = $publication->etat;
            $post->created_at = $publication->created_at;
            $post->created_at_formatted = $publication->created_at->diffForHumans();
            
            // Informations sur la page et l'utilisateur
            $post->page_id = $publication->page_id;
            $post->page = $publication->page;
            $post->user = $publication->page->user;
            $post->user->picture_url = asset('storage/' . $publication->page->user->picture);
            
            // Vérifier si l'utilisateur est abonné à cette page
            $post->is_subscribed = PageAbonnes::where('user_id', $userId)
                ->where('page_id', $publication->page_id)
                ->exists();
            
            // Informations spécifiques selon le type
            if ($type === 'offres-emploi') {
                $post->company_name = $publication->entreprise;
                $post->location = $publication->lieu;
                $post->salary_range = $publication->salaire . ' ' . $publication->devise;
                $post->sector = $publication->secteur ?? '';
                $post->reference = $publication->reference;
            } elseif ($type === 'opportunites-affaires') {
                $post->investment_amount = $publication->investissement_requis . ' ' . $publication->devise;
                $post->sector = $publication->secteur;
            }
            
            // Images
            $post->images = [];
            if (!empty($publication->image)) {
                // Obtenir l'URL de base du serveur
                $baseUrl = url('/');
                
                // Vérifier si l'image est un JSON (tableau d'images) ou une chaîne simple
                if (is_string($publication->image) && json_decode($publication->image) !== null) {
                    $imageArray = json_decode($publication->image, true);
                    foreach ($imageArray as $img) {
                        // Construire l'URL complète avec le domaine du serveur
                        $imageUrl = $baseUrl . Storage::url($img);
                        $post->images[] = [
                            'url' => $imageUrl,
                            'path' => $img
                        ];
                    }
                } else {
                    // Si c'est une seule image (chaîne)
                    // Construire l'URL complète avec le domaine du serveur
                    $imageUrl = $baseUrl . Storage::url($publication->image);
                    $post->images[] = [
                        'url' => $imageUrl,
                        'path' => $publication->image
                    ];
                }
            }
            
            // Statistiques (likes, commentaires, partages)
            // Utiliser les modèles spécifiques en fonction du type de publication
            switch ($type) {
                case 'offres-emploi':
                    // Compter les likes pour cette offre d'emploi
                    $post->likes_count = OffreEmploiLike::where('offre_emploi_id', $publication->id)->count();
                    
                    
                    $post->offer_file_url = $publication->offer_file ? asset('storage/' . $publication->offer_file) : null;
                    
                    // Vérifier si l'utilisateur a aimé cette offre d'emploi
                    $post->is_liked = OffreEmploiLike::where('offre_emploi_id', $publication->id)
                        ->where('user_id', $userId)
                        ->exists();
                    
                    // Compter les commentaires pour cette offre d'emploi
                    $post->comments_count = OffreEmploiComment::where('offre_emploi_id', $publication->id)->count();
                    
                    // Récupérer les 3 derniers commentaires
                    $post->comments = OffreEmploiComment::where('offre_emploi_id', $publication->id)
                        ->with('user')
                        ->orderBy('created_at', 'desc')
                        ->take(3)
                        ->get()
                        ->map(function($comment) use ($userId) {
                            return [
                                'id' => $comment->id,
                                'content' => $comment->contenu,
                                'created_at' => $comment->created_at,
                                'created_at_formatted' => $comment->created_at->diffForHumans(),
                                'user' => [
                                    'id' => $comment->user->id,
                                    'name' => $comment->user->name,
                                    'profile_picture' => $comment->user->picture ? url('/') . Storage::url($comment->user->picture) : null
                                ],
                                'likes_count' => 0, // À implémenter si les commentaires ont des likes
                                'is_liked' => false // À implémenter si les commentaires ont des likes
                            ];
                        });
                    
                    // Compter les partages pour cette offre d'emploi
                    $post->shares_count = OffreEmploiShare::where('offre_emploi_id', $publication->id)->count();
                    break;
                    
                case 'opportunites-affaires':
                    // Compter les likes pour cette opportunité d'affaire
                    $post->likes_count = OpportuniteAffaireLike::where('opportunite_affaire_id', $publication->id)->count();
                    
                    // Vérifier si l'utilisateur a aimé cette opportunité d'affaire
                    $post->is_liked = OpportuniteAffaireLike::where('opportunite_affaire_id', $publication->id)
                        ->where('user_id', $userId)
                        ->exists();
                    
                    // Compter les commentaires pour cette opportunité d'affaire
                    $post->comments_count = OpportuniteAffaireComment::where('opportunite_affaire_id', $publication->id)->count();
                    
                    // Récupérer les 3 derniers commentaires
                    $post->comments = OpportuniteAffaireComment::where('opportunite_affaire_id', $publication->id)
                        ->with('user')
                        ->orderBy('created_at', 'desc')
                        ->take(3)
                        ->get()
                        ->map(function($comment) use ($userId) {
                            return [
                                'id' => $comment->id,
                                'content' => $comment->contenu,
                                'created_at' => $comment->created_at,
                                'created_at_formatted' => $comment->created_at->diffForHumans(),
                                'user' => [
                                    'id' => $comment->user->id,
                                    'name' => $comment->user->name,
                                    'profile_picture' => $comment->user->picture ? url('/') . Storage::url($comment->user->picture) : null
                                ],
                                'likes_count' => 0, // À implémenter si les commentaires ont des likes
                                'is_liked' => false // À implémenter si les commentaires ont des likes
                            ];
                        });
                    
                    // Compter les partages pour cette opportunité d'affaire
                    $post->shares_count = OpportuniteAffaireShare::where('opportunite_affaire_id', $publication->id)->count();
                    break;
                    
                default: // Pour les publicites
                    // Compter les likes pour cette publication
                    $post->likes_count = PubliciteLike::where('publicite_id', $publication->id)->count();
                    
                    // Vérifier si l'utilisateur a aimé cette publication
                    $post->is_liked = PubliciteLike::where('publicite_id', $publication->id)
                        ->where('user_id', $userId)
                        ->exists();
                    
                    // Compter les commentaires pour cette publication
                    $post->comments_count = PubliciteComment::where('publicite_id', $publication->id)->count();
                    
                    // Récupérer les 3 derniers commentaires
                    $post->comments = PubliciteComment::where('publicite_id', $publication->id)
                        ->with('user')
                        ->orderBy('created_at', 'desc')
                        ->take(3)
                        ->get()
                        ->map(function($comment) use ($userId) {
                            return [
                                'id' => $comment->id,
                                'content' => $comment->contenu,
                                'created_at' => $comment->created_at,
                                'created_at_formatted' => $comment->created_at->diffForHumans(),
                                'user' => [
                                    'id' => $comment->user->id,
                                    'name' => $comment->user->name,
                                    'profile_picture' => $comment->user->picture ? url('/') . Storage::url($comment->user->picture) : null
                                ],
                                'likes_count' => 0, // À implémenter si les commentaires ont des likes
                                'is_liked' => false // À implémenter si les commentaires ont des likes
                            ];
                        });
                    
                    // Compter les partages pour cette publication
                    $post->shares_count = PubliciteShare::where('publicite_id', $publication->id)->count();
                    break;
            }
            
            return $post;
        });

        \Log::info('Publications récupérées: ' . json_encode($posts));

        return response()->json([
            'posts' => $posts,
            'has_more' => count($publications) == $limit
        ]);
    }

    /**
     * S'abonner à une page
     */
    public function subscribe($pageId)
    {
        $userId = Auth::id();
        
        // Vérifier si la page existe
        $page = Page::findOrFail($pageId);
        
        // Vérifier si l'utilisateur est déjà abonné
        $existingSubscription = PageAbonnes::where('user_id', $userId)
            ->where('page_id', $pageId)
            ->first();
            
        if ($existingSubscription) {
            return response()->json([
                'message' => 'Vous êtes déjà abonné à cette page',
                'subscribed' => true
            ]);
        }
        
        // Créer l'abonnement
        $subscription = new PageAbonnes();
        $subscription->user_id = $userId;
        $subscription->page_id = $pageId;
        $subscription->save();
        
        // Mettre à jour le compteur d'abonnés de la page
        $page->nombre_abonnes = $page->nombre_abonnes + 1;
        $page->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Vous êtes maintenant abonné à cette page',
            'subscribed' => true
        ], 201);
    }

    /**
     * Afficher une publication spécifique avec ses commentaires
     */
    public function show(Request $request, $id)
    {
        $type = $request->input('type', 'publicites');
        $userId = Auth::id();
        
        // Rechercher la publication en fonction du type
        switch ($type) {
            case 'emploi':
                $publication = OffreEmploi::with(['page', 'page.user'])
                    ->where('id', $id)
                    ->where('statut', 'approuve')
                    ->where('etat', 'actif')
                    ->firstOrFail();
                break;
                
            case 'opportunite':
                $publication = OpportuniteAffaire::with(['page', 'page.user'])
                    ->where('id', $id)
                    ->where('statut', 'approuve')
                    ->where('etat', 'actif')
                    ->firstOrFail();
                break;
                
            default: // 'publicites'
                $publication = Publicite::with(['page', 'page.user'])
                    ->where('id', $id)
                    ->where('statut', 'approuve')
                    ->where('etat', 'actif')
                    ->firstOrFail();
                break;
        }

        // Transformer la publication pour avoir un format uniforme
        $post = new \stdClass();
        $post->id = $publication->id;
        $post->type = $type;
        $post->title = $publication->titre;
        $post->content = $publication->description ?? '';
        $post->created_at = $publication->created_at;
        $post->created_at_formatted = $publication->created_at->diffForHumans();
        
        // Informations sur la page et l'utilisateur
        $post->page_id = $publication->page_id;
        $post->page = $publication->page;
        $post->user = $publication->page->user;
        
        // Vérifier si l'utilisateur est abonné à cette page
        $post->is_subscribed = PageAbonnes::where('user_id', $userId)
            ->where('page_id', $publication->page_id)
            ->exists();
        
        // Informations spécifiques selon le type
        if ($type === 'emploi') {
            $post->company_name = $publication->entreprise;
            $post->location = $publication->lieu;
            $post->salary_range = $publication->salaire . ' ' . $publication->devise;
            $post->sector = $publication->secteur ?? '';
            $post->type_contrat = $publication->type_contrat;
            $post->experience_requise = $publication->experience_requise;
            $post->niveau_etudes = $publication->niveau_etudes;
            $post->competences_requises = $publication->competences_requises;
            $post->date_limite = $publication->date_limite;
            $post->email_contact = $publication->email_contact;
            $post->contacts = $publication->contacts;
            $post->lien = $publication->lien;
        } elseif ($type === 'opportunite') {
            $post->investment_amount = $publication->investissement_requis . ' ' . $publication->devise;
            $post->sector = $publication->secteur;
            $post->benefices_attendus = $publication->benefices_attendus;
            $post->duree_retour_investissement = $publication->duree_retour_investissement;
            $post->localisation = $publication->localisation;
            $post->contacts = $publication->contacts;
            $post->email = $publication->email;
            $post->conditions_participation = $publication->conditions_participation;
            $post->date_limite = $publication->date_limite;
        } else { // publicites
            $post->categorie = $publication->categorie;
            $post->contacts = $publication->contacts;
            $post->email = $publication->email;
            $post->adresse = $publication->adresse;
            $post->lien = $publication->lien;
            $post->video = $publication->video;
        }
        
        // Images
        $post->images = [];
        if (!empty($publication->image)) {
            $post->images[] = $publication->image;
        }
        
        // Statistiques (likes, commentaires, partages)
        // Ces fonctionnalités devront être implémentées dans les modèles
        $post->likes_count = 0; // À implémenter
        $post->comments_count = 0; // À implémenter
        $post->shares_count = 0; // À implémenter
        $post->is_liked = false; // À implémenter
        $post->comments = []; // À implémenter

        return response()->json([
            'post' => $post
        ]);
    }

    /**
     * Aimer ou ne plus aimer une publication
     */
    public function toggleLike($id)
    {
        $post = Post::findOrFail($id);
        
        // Vérifier si le post est approuvé
        if ($post->status !== 'approved') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $user = Auth::user();
        $like = Like::where('user_id', $user->id)
                    ->where('post_id', $post->id)
                    ->first();

        if ($like) {
            // Si l'utilisateur a déjà aimé le post, supprimer le like
            $like->delete();
            $liked = false;
        } else {
            // Sinon, créer un nouveau like
            Like::create([
                'user_id' => $user->id,
                'post_id' => $post->id
            ]);
            $liked = true;
        }

        return response()->json([
            "success" => true,
            'liked' => $liked,
            'likes_count' => $post->likes()->count()
        ]);
    }

    /**
     * Se désabonner d'une page
     */
    public function unsubscribe($pageId)
    {
        $userId = Auth::id();
        
        // Vérifier si la page existe
        $page = Page::findOrFail($pageId);
        
        // Vérifier si l'abonnement existe
        $subscription = PageAbonnes::where('user_id', $userId)
            ->where('page_id', $pageId)
            ->first();
            
        if (!$subscription) {
            return response()->json([
                'message' => 'Vous n\'êtes pas abonné à cette page',
                'subscribed' => false
            ]);
        }
        
        // Supprimer l'abonnement
        $subscription->delete();
        
        // Mettre à jour le compteur d'abonnés de la page
        if ($page->nombre_abonnes > 0) {
            $page->nombre_abonnes = $page->nombre_abonnes - 1;
            $page->save();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Vous vous êtes désabonné de cette page',
            'subscribed' => false
        ]);
    }
    
    /**
     * Récupérer la liste des pages auxquelles l'utilisateur est abonné
     */
    public function subscribedPages()
    {
        $userId = Auth::id();
        
        $pages = Page::whereHas('abonnes', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with('user')->get();

        foreach ($pages as $page) {
            if ($page->user->picture) {
                $page->user->picture = asset('storage/' . $page->user->picture);
            }

            if ($page->photo_de_couverture) {
                $page->photo_de_couverture = asset('storage/' . $page->photo_de_couverture);
            }
        }

        
        return response()->json([
            'pages' => $pages
        ]);
    }
    
    /**
     * Récupérer la liste des pages recommandées pour l'utilisateur
     */
    public function recommendedPages()
    {
        $userId = Auth::id();
        
        // Exclure les pages auxquelles l'utilisateur est déjà abonné
        $subscribedPageIds = PageAbonnes::where('user_id', $userId)
            ->pluck('page_id')
            ->toArray();
        
        // Récupérer les pages les plus populaires (avec le plus d'abonnés)
        $pages = Page::whereNotIn('id', $subscribedPageIds)
            ->orderBy('nombre_abonnes', 'desc')
            ->with('user')
            ->take(10)
            ->get();

            foreach ($pages as $page) {
                if ($page->user->picture) {
                    $page->user->picture = asset('storage/' . $page->user->picture);
                }
    
                if ($page->photo_de_couverture) {
                    $page->photo_de_couverture = asset('storage/' . $page->photo_de_couverture);
                }
            }
        
        return response()->json([
            'pages' => $pages
        ]);
    }

    /**
     * Ajouter un commentaire à une publication
     */
    // public function addComment(Request $request, $id)
    // {
    //     $post = Post::findOrFail($id);
        
    //     // Vérifier si le post est approuvé
    //     if ($post->status !== 'approved') {
    //         return response()->json(['message' => 'Non autorisé'], 403);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'content' => 'required|string',
    //         'parent_id' => 'nullable|exists:comments,id'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }

    //     $comment = new Comment();
    //     $comment->user_id = Auth::id();
    //     $comment->post_id = $post->id;
    //     $comment->content = $request->content;
    //     $comment->parent_id = $request->parent_id;
    //     $comment->save();

    //     // Charger les relations pour la réponse
    //     $comment->load('user');
    //     $comment->created_at_formatted = $comment->created_at->diffForHumans();
    //     $comment->likes_count = 0;
    //     $comment->is_liked = false;

    //     return response()->json([
    //         "success" => true,
    //         'message' => 'Commentaire ajouté avec succès',
    //         'comment' => $comment,
    //         'comments_count' => $post->comments()->count()
    //     ], 201);
    // }

    // /**
    //  * Supprimer un commentaire
    //  */
    // public function deleteComment($id)
    // {
    //     $comment = Comment::findOrFail($id);

    //     // Vérifier si l'utilisateur est autorisé à supprimer ce commentaire
    //     if ($comment->user_id !== Auth::id()) {
    //         return response()->json(['message' => 'Non autorisé'], 403);
    //     }

    //     $post_id = $comment->post_id;
    //     $comment->delete();

    //     return response()->json([
    //         "success" => true,
    //         'message' => 'Commentaire supprimé avec succès',
    //         'comments_count' => Post::find($post_id)->comments()->count()
    //     ]);
    // }

    // /**
    //  * Aimer ou ne plus aimer un commentaire
    //  */
    // public function toggleCommentLike($id)
    // {
    //     $comment = Comment::findOrFail($id);
    //     $user = Auth::user();
        
    //     $like = $comment->likes()->where('user_id', $user->id)->first();

    //     if ($like) {
    //         // Si l'utilisateur a déjà aimé le commentaire, supprimer le like
    //         $like->delete();
    //         $liked = false;
    //     } else {
    //         // Sinon, créer un nouveau like
    //         $comment->likes()->create([
    //             'user_id' => $user->id
    //         ]);
    //         $liked = true;
    //     }

    //     return response()->json([
    //         "success" => true,
    //         'liked' => $liked,
    //         'likes_count' => $comment->likes()->count()
    //     ]);
    // }

    // /**
    //  * Enregistrer un partage de publication
    //  */
    // public function sharePost(Request $request, $id)
    // {
    //     $post = Post::findOrFail($id);
        
    //     // Vérifier si le post est approuvé
    //     if ($post->status !== 'approved') {
    //         return response()->json(['message' => 'Non autorisé'], 403);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'platform' => 'required|in:facebook,twitter,instagram,whatsapp,linkedin,email,other'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }

    //     $share = new Share();
    //     $share->user_id = Auth::id();
    //     $share->post_id = $post->id;
    //     $share->platform = $request->platform;
    //     $share->save();

    //     return response()->json([
    //         "success" => true,
    //         'message' => 'Partage enregistré avec succès',
    //         'shares_count' => $post->shares()->count()
    //     ], 201);
    // }
}
