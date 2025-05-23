<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Publicite;
use App\Models\User;
use App\Notifications\PublicationStatusChanged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;

class AdvertisementValidationController extends Controller
{
    /**
     * Afficher la liste des publicités pour validation
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        try {
            $allAds = Publicite::with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($ad) {
                    // Ajouter l'URL de l'image si elle existe
                    if ($ad->image) {
                        $ad->image_url = asset('storage/' . $ad->image);
                    }
                    
                    // Générer l'URL correcte pour la photo de profil
                    if ($ad->user && $ad->user->picture) {
                        $ad->user->picture_url = asset('storage/' . $ad->user->picture);
                    }

                    if ($ad->video) {
                        $ad->video_url = asset('storage/' . $ad->video);
                    }
                    return $ad;
                });
            
            return response()->json($allAds);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la récupération de toutes les publicités', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Approuver une publicité
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve($id)
    {
        $publicite = Publicite::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour le statut
        $publicite->statut = 'approuvé';
        $publicite->save();
        
        // Notifier l'utilisateur que sa publication a été approuvée
        $publicite->user->notify(new PublicationStatusChanged([
            'type' => $publicite->type === "publicité" ? "Publicité" : "Annonce",
            'id' => $publicite->id,
            'titre' => $publicite->titre,
            'statut' => 'approuvé',
            'message' => 'Votre publicité a été approuvée et est maintenant visible par tous les utilisateurs.'
        ]));
        
        return response()->json([
            'message' => 'Publicité approuvée avec succès',
            'publicite' => $publicite
        ]);
    }
    
    /**
     * Rejeter une publicité
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);
        
        $publicite = Publicite::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour le statut
        $publicite->statut = 'rejete';
        $publicite->raison_rejet = $request->reason;
        $publicite->save();
        
        // Notifier l'utilisateur que sa publication a été rejetée
        $user = User::find($publicite->user->id);
        $user->notify(new PublicationStatusChanged([
            'type' => $publicite->type === "publicité" ? "Publicité" : "Annonce",
            'id' => $publicite->id,
            'titre' => $publicite->titre,
            'statut' => 'rejete',
            'message' => 'Votre publicité a été rejetée.',
            'raison' => $request->reason
        ]));
        
        return response()->json([
            'message' => 'Publicité rejetée avec succès',
            'publicite' => $publicite
        ]);
    }
    
    /**
     * Mettre à jour le statut d'une publicité
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|string|in:en_attente,approuve,rejete',
        ]);
        
        $publicite = Publicite::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour le statut
        $publicite->statut = $request->statut;
        $publicite->save();
        
        // Notifier l'utilisateur que le statut de sa publication a été modifié
        $user = User::find($publicite->user->id);
        $user->notify(new PublicationStatusChanged([
            'type' => $publicite->type === "publicité" ? "Publicité" : "Annonce",
            'id' => $publicite->id,
            'titre' => $publicite->titre,
            'statut' => $request->statut,
            'message' => 'Le statut de votre publicité a été modifié.'
        ]));
        
        return response()->json([
            'message' => 'Statut de la publicité mis à jour avec succès',
            'publicite' => $publicite
        ]);
    }
    
    /**
     * Mettre à jour l'état d'une publicité (disponible/terminé)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateEtat(Request $request, $id)
    {
        $request->validate([
            'etat' => 'required|string|in:disponible,terminé',
        ]);
        
        $publicite = Publicite::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour l'état
        $publicite->etat = $request->etat;
        $publicite->save();
        
        return response()->json([
            'message' => 'L\'état de la publicité mis à jour avec succès',
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
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        try {
            $publicite = Publicite::findOrFail($id);
            
            // Supprimer l'image associée si elle existe
            if ($publicite->image && Storage::disk('public')->exists($publicite->image)) {
                Storage::disk('public')->delete($publicite->image);
            }
            
            // Supprimer les commentaires et likes associés
            $publicite->comments()->delete();
            $publicite->likes()->delete();
            
            // Supprimer la publicité
            $publicite->delete();
            
            return response()->json(['message' => 'Publicité supprimée avec succès']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la suppression de la publicité', 'error' => $e->getMessage()], 500);
        }
    }
}
