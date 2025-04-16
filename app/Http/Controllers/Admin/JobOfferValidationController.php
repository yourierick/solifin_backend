<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OffreEmploi;
use App\Models\User;
use App\Notifications\PublicationStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class JobOfferValidationController extends Controller
{
    /**
     * Afficher la liste des offres d'emploi pour validation
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
            $allJobs = OffreEmploi::with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($job) {
                    // Ajouter l'URL du fichier d'offre si elle existe
                    if ($job->offer_file) {
                        $job->offer_file_url = asset('storage/' . $job->offer_file);
                    }
                    
                    // Générer l'URL correcte pour la photo de profil
                    if ($job->user && $job->user->picture) {
                        $job->user->picture_url = asset('storage/' . $job->user->picture);
                    }
                    return $job;
                });
            
            return response()->json($allJobs);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la récupération de toutes les offres d\'emploi', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Récupérer les offres d'emploi en attente de validation
     *
     * @return \Illuminate\Http\Response
     */
    // public function getPendingJobOffers()
    // {
    //     // Vérifier si l'utilisateur est un administrateur
    //     if (!Auth::user()->is_admin) {
    //         return response()->json(['message' => 'Non autorisé'], 403);
    //     }
        
    //     try {
    //         $pendingJobs = OffreEmploi::with('user')
    //             ->where('statut', 'en_attente')
    //             ->orderBy('created_at', 'desc')
    //             ->get()
    //             ->map(function ($job) {
    //                 // Ajouter l'URL du fichier d'offre si elle existe
    //                 if ($job->offer_file) {
    //                     $job->offer_file_url = asset('storage/' . $job->offer_file);
    //                 }
    //                 return $job;
    //             });
            
    //         return response()->json($pendingJobs);
    //     } catch (\Exception $e) {
    //         return response()->json(['message' => 'Erreur lors de la récupération des offres d\'emploi en attente', 'error' => $e->getMessage()], 500);
    //     }
    // }
    
    /**
     * Approuver une offre d'emploi
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve($id)
    {
        $offreEmploi = OffreEmploi::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour le statut
        $offreEmploi->statut = 'approuvé';
        $offreEmploi->save();
        
        // Notifier l'utilisateur que sa publication a été approuvée
        $user = User::find($offreEmploi->user->id);
        $user->notify(new PublicationStatusChanged([
            'type' => 'offres_emploi',
            'id' => $offreEmploi->id,
            'titre' => $offreEmploi->titre,
            'statut' => 'approuve',
            'message' => 'Votre offre d\'emploi a été approuvée et est maintenant visible par tous les utilisateurs.'
        ]));
        
        return response()->json([
            'message' => 'Offre d\'emploi approuvée avec succès',
            'offreEmploi' => $offreEmploi
        ]);
    }
    
    /**
     * Rejeter une offre d'emploi
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
        
        $offreEmploi = OffreEmploi::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour le statut
        $offreEmploi->statut = 'rejeté';
        $offreEmploi->raison_rejet = $request->reason;
        $offreEmploi->save();
        
        // Notifier l'utilisateur que sa publication a été rejetée
        $user = User::find($offreEmploi->user->id);
        $user->notify(new PublicationStatusChanged([
            'type' => 'offres_emploi',
            'id' => $offreEmploi->id,
            'titre' => $offreEmploi->titre,
            'statut' => 'rejeté',
            'message' => 'Votre offre d\'emploi a été rejetée.',
            'raison' => $request->reason
        ]));
        
        return response()->json([
            'message' => 'Offre d\'emploi rejetée avec succès',
            'offreEmploi' => $offreEmploi
        ]);
    }
    
    /**
     * Mettre à jour le statut d'une offre d'emploi
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
        
        $offreEmploi = OffreEmploi::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour le statut
        $offreEmploi->statut = $request->statut;
        $offreEmploi->save();
        
        // Notifier l'utilisateur que le statut de sa publication a été modifié
        $user = User::find($offreEmploi->user->id);
        $user->notify(new PublicationStatusChanged([
            'type' => 'offres_emploi',
            'id' => $offreEmploi->id,
            'titre' => $offreEmploi->titre,
            'statut' => $request->statut,
            'message' => 'Le statut de votre offre d\'emploi a été mis à jour.'
        ]));
        
        return response()->json([
            'message' => 'Statut de l\'offre d\'emploi mis à jour avec succès',
            'offreEmploi' => $offreEmploi
        ]);
    }
    
    /**
     * Mettre à jour l'état d'une offre d'emploi (disponible/terminé)
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
        
        $offreEmploi = OffreEmploi::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour l'état
        $offreEmploi->etat = $request->etat;
        $offreEmploi->save();
        
        return response()->json([
            'message' => 'État de l\'offre d\'emploi mis à jour avec succès',
            'offreEmploi' => $offreEmploi
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
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        try {
            $offreEmploi = OffreEmploi::findOrFail($id);
            
            // Supprimer le fichier d'offre associé s'il existe
            if ($offreEmploi->offer_file && Storage::disk('public')->exists($offreEmploi->offer_file)) {
                Storage::disk('public')->delete($offreEmploi->offer_file);
            }
            
            // Supprimer les commentaires et likes associés
            $offreEmploi->comments()->delete();
            $offreEmploi->likes()->delete();
            
            // Supprimer l'offre d'emploi
            $offreEmploi->delete();
            
            return response()->json(['message' => 'Offre d\'emploi supprimée avec succès']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la suppression de l\'offre d\'emploi', 'error' => $e->getMessage()], 500);
        }
    }
}
