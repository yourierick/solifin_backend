<?php

namespace App\Http\Controllers;

use App\Models\OffreEmploi;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OffreEmploiController extends Controller
{
    /**
     * Récupérer toutes les offres d'emploi
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = OffreEmploi::with('page.user');
        
        // Filtres
        if ($request->has('type_contrat')) {
            $query->where('type_contrat', $request->type_contrat);
        }
        
        if ($request->has('lieu')) {
            $query->where('lieu', 'like', '%' . $request->lieu . '%');
        }
        
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }
        
        if ($request->has('etat')) {
            $query->where('etat', $request->etat);
        }
        
        $offres = $query->orderBy('created_at', 'desc')->paginate(10);
        
        // Ajouter l'URL complète du fichier PDF pour chaque offre
        $offres->getCollection()->transform(function ($offre) {
            if ($offre->offer_file) {
                $offre->offer_file_url = asset('storage/' . $offre->offer_file);
            }
            return $offre;
        });
        
        return response()->json([
            'success' => true,
            'offres' => $offres
        ]);
    }

    /**
     * Récupérer les offres d'emploi en attente (pour admin)
     *
     * @return \Illuminate\Http\Response
     */
    public function getPendingJobs()
    {
        $offres = OffreEmploi::with('page.user')
            ->where('statut', 'en_attente')
            ->orderBy('created_at', 'asc')
            ->paginate(10);
        
        return response()->json([
            'success' => true,
            'offres' => $offres
        ]);
    }

    /**
     * Créer une nouvelle offre d'emploi
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'entreprise' => 'required|string|max:255',
            'lieu' => 'required|string|max:255',
            'type_contrat' => 'required|string|max:255',
            'description' => 'required|string',
            'competences_requises' => 'required|string',
            'experience_requise' => 'required|string|max:255',
            'niveau_etudes' => 'nullable|string|max:255',
            'salaire' => 'nullable|string|max:255',
            'devise' => 'nullable|string|max:5',
            'avantages' => 'nullable|string',
            'date_limite' => 'nullable|date',
            'email_contact' => 'required|email',
            'contacts' => 'nullable|string|max:255',
            'offer_file' => 'nullable|file|mimes:pdf|max:5120', // 5MB max, optionnel
            'lien' => 'nullable|url|max:255',
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

        $data = $request->all();
        $data['page_id'] = $page->id;
        $data['statut'] = 'en_attente';
        $data['etat'] = 'disponible';
        
        // Traitement du fichier PDF
        if ($request->hasFile('offer_file') && $request->file('offer_file')->isValid()) {
            $file = $request->file('offer_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('offers', $fileName, 'public');
            $data['offer_file'] = $filePath;
        }

        $offre = OffreEmploi::create($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Offre d\'emploi créée avec succès. Elle est en attente de validation.',
            'offre' => $offre
        ], 201);
    }

    /**
     * Récupérer une offre d'emploi spécifique
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $offre = OffreEmploi::with('page.user')->findOrFail($id);
        
        // Ajouter l'URL complète du fichier PDF s'il existe
        if ($offre->offer_file) {
            $offre->offer_file_url = asset('storage/' . $offre->offer_file);
        }
        
        // Ajouter l'URL complète de l'image si elle existe
        if ($offre->image) {
            $offre->image_url = asset('storage/' . $offre->image);
        }
        
        // Ajouter l'URL complète de la vidéo si elle existe
        if ($offre->video) {
            $offre->video_url = asset('storage/' . $offre->video);
        }
        
        return response()->json([
            'success' => true,
            'offre' => $offre
        ]);
    }

    /**
     * Mettre à jour une offre d'emploi
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $offre = OffreEmploi::findOrFail($id);
        $user = Auth::user();
        
        // Vérifier si l'utilisateur est autorisé
        // if (!$user->is_admin && $offre->page->user_id !== $user->id) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Vous n\'êtes pas autorisé à modifier cette offre d\'emploi.'
        //     ], 403);
        // }

        $validator = Validator::make($request->all(), [
            'titre' => 'nullable|string|max:255',
            'entreprise' => 'nullable|string|max:255',
            'lieu' => 'nullable|string|max:255',
            'type_contrat' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'competences_requises' => 'nullable|string',
            'experience_requise' => 'nullable|string|max:255',
            'niveau_etudes' => 'nullable|string|max:255',
            'salaire' => 'nullable|string|max:255',
            'devise' => 'nullable|string|max:5',
            'avantages' => 'nullable|string',
            'date_limite' => 'nullable|date',
            'email_contact' => 'nullable|email',
            'contacts' => 'nullable|string|max:255',
            'offer_file' => 'nullable|file|mimes:pdf|max:5120', // 5MB max
            'lien' => 'nullable|url|max:255',
            'statut' => 'nullable|in:en_attente,approuvé,rejeté,expiré',
            'etat' => 'nullable|in:disponible,terminé',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        
        // Si l'utilisateur n'est pas admin, l'offre revient en attente si certains champs sont modifiés
        if (!$user->is_admin && $request->has(['titre', 'description', 'competences_requises'])) {
            $data['statut'] = 'en_attente';
        }
        
        // Traitement de l'image
        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image si elle existe
            if ($offre->image) {
                \Storage::disk('public')->delete($offre->image);
            }
            
            $path = $request->file('image')->store('offres/images', 'public');
            $data['image'] = $path;
        } else if ($request->has('remove_image') && $request->input('remove_image') == '1') {
            // Supprimer l'image sans la remplacer
            if ($offre->image) {
                \Storage::disk('public')->delete($offre->image);
                $data['image'] = null;
            }
        }
        
        // Traitement de la vidéo
        if ($request->hasFile('video')) {
            // Supprimer l'ancienne vidéo si elle existe
            if ($offre->video) {
                \Storage::disk('public')->delete($offre->video);
            }
            
            $path = $request->file('video')->store('offres/videos', 'public');
            $data['video'] = $path;
        } else if ($request->has('remove_video') && $request->input('remove_video') == '1') {
            // Supprimer la vidéo sans la remplacer
            if ($offre->video) {
                \Storage::disk('public')->delete($offre->video);
                $data['video'] = null;
            }
        }
        
        // Traitement du fichier PDF
        if ($request->hasFile('offer_file') && $request->file('offer_file')->isValid()) {
            // Supprimer l'ancien fichier s'il existe
            if ($offre->offer_file) {
                \Storage::disk('public')->delete($offre->offer_file);
            }
            
            $file = $request->file('offer_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('offers', $fileName, 'public');
            $data['offer_file'] = $filePath;
        }
        // Gestion de la suppression du fichier PDF
        elseif ($request->has('remove_offer_file') && $request->input('remove_offer_file') == '1') {
            // Supprimer le fichier physique
            if ($offre->offer_file) {
                \Storage::disk('public')->delete($offre->offer_file);
            }
            
            // Mettre à null le champ dans la base de données
            $data['offer_file'] = null;
        }
        
        $offre->update($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Offre d\'emploi mise à jour avec succès.',
            'offre' => $offre
        ]);
    }

    /**
     * Changer l'état d'une offre d'emploi (disponible/terminé)
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

        $offre = OffreEmploi::findOrFail($id);
        $user = Auth::user();
        
        // Vérifier si l'utilisateur est autorisé
        if (!$user->is_admin && $offre->page->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette offre d\'emploi.'
            ], 403);
        }
        
        $offre->update([
            'etat' => $request->etat
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'État de l\'offre d\'emploi mis à jour avec succès.',
            'offre' => $offre
        ]);
    }

    /**
     * Changer le statut d'une offre d'emploi (admin)
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
                'message' => 'Seuls les administrateurs peuvent changer le statut des offres d\'emploi.'
            ], 403);
        }
        
        $offre = OffreEmploi::findOrFail($id);
        $offre->update([
            'statut' => $request->statut
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Statut de l\'offre d\'emploi mis à jour avec succès.',
            'offre' => $offre
        ]);
    }

    /**
     * Supprimer une offre d'emploi
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $offre = OffreEmploi::findOrFail($id);
        $user = Auth::user();

        if ($offre->offer_file) {   
            if (\Storage::disk('public')->exists($offre->offer_file)) {
                \Storage::disk('public')->delete($offre->offer_file);
            }
        }
        
        // Vérifier si l'utilisateur est autorisé
        // if (!$user->is_admin && $offre->page->user_id !== $user->id) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Vous n\'êtes pas autorisé à supprimer cette offre d\'emploi.'
        //     ], 403);
        // }
        
        $offre->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Offre d\'emploi supprimée avec succès.'
        ]);
    }
}
