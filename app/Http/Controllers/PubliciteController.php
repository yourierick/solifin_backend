<?php

namespace App\Http\Controllers;

use App\Models\Publicite;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
        
        \Log::info($data);
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
                'categorie' => 'required|in:produit,service',
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
                \Log::info($validator->errors());
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
                $data['duree_affichage'] = 30; // 30 jours par défaut
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
    
            
            
            return response()->json([
                'success' => true,
                'message' => 'Publicité créée avec succès. Elle est en attente de validation.',
                'publicite' => $publicite
            ], 201);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            \Log::info($request->all());
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
        
        return response()->json([
            'success' => true,
            'publicite' => $publicite
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
        $publicite = Publicite::findOrFail($id);
        $user = Auth::user();
        
        // Vérifier si l'utilisateur est autorisé
        if (!$user->is_admin && $publicite->page->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette publicité.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'categorie' => 'nullable|in:produit,service',
            'titre' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'video' => 'nullable|file|mimes:mp4,mov,avi|max:20480',
            'contacts' => 'nullable|string',
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
            \Log::info($validator->errors());
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
        }
        
        if ($request->hasFile('video')) {
            // Supprimer l'ancienne vidéo si elle existe
            if ($publicite->video) {
                Storage::disk('public')->delete($publicite->video);
            }
            
            $path = $request->file('video')->store('publicites/videos', 'public');
            $data['video'] = $path;
        }
        
        $publicite->update($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Publicité mise à jour avec succès.',
            'publicite' => $publicite
        ]);
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
}
