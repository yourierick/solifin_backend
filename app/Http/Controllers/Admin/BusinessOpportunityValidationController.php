<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpportuniteAffaire;
use App\Models\User;
use App\Notifications\PublicationStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class BusinessOpportunityValidationController extends Controller
{
    /**
     * Afficher la liste des opportunités d'affaires pour validation
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
            $allOpportunities = OpportuniteAffaire::with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($opportunity) {
                    // Ajouter l'URL du fichier de présentation si elle existe
                    if ($opportunity->opportunity_file) {
                        $opportunity->opportunity_file_url = asset('storage/' . $opportunity->opportunity_file);
                    }
                    
                    // Générer l'URL correcte pour la photo de profil
                    if ($opportunity->user && $opportunity->user->picture) {
                        $opportunity->user->picture_url = asset('storage/' . $opportunity->user->picture);
                    }
                    return $opportunity;
                });
            
            return response()->json($allOpportunities);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la récupération de toutes les opportunités d\'affaires', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Approuver une opportunité d'affaire
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve($id)
    {
        $opportuniteAffaire = OpportuniteAffaire::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour le statut
        $opportuniteAffaire->statut = 'approuvé';
        $opportuniteAffaire->created_at = now();
        $opportuniteAffaire->save();
        
        // Notifier l'utilisateur que sa publication a été approuvée
        $user = User::find($opportuniteAffaire->user->id);
        $user->notify(new PublicationStatusChanged([
            'type' => [
                    'partenariat' => 'Opportunité de partenariat',
                    'appel_projet' => 'Appel à projet',
                ][$opportuniteAffaire->type] ?? 'Opportunité d\'affaire',
            'id' => $opportuniteAffaire->id,
            'titre' => $opportuniteAffaire->titre,
            'statut' => 'approuvé',
            'message' => 'Votre opportunité d\'affaire a été approuvée et est maintenant visible par tous les utilisateurs pendant '. $opportuniteAffaire->duree_affichage . ' jours.'
        ]));
        
        return response()->json([
            'message' => 'Opportunité d\'affaire approuvée avec succès',
            'opportuniteAffaire' => $opportuniteAffaire
        ]);
    }
    
    /**
     * Rejeter une opportunité d'affaire
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
        
        $opportuniteAffaire = OpportuniteAffaire::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour le statut
        $opportuniteAffaire->statut = 'rejeté';
        $opportuniteAffaire->raison_rejet = $request->reason;
        $opportuniteAffaire->save();
        
        // Notifier l'utilisateur que sa publication a été rejetée
        $user = User::find($opportuniteAffaire->user->id);
        $user->notify(new PublicationStatusChanged([
            'type' => [
                    'partenariat' => 'Opportunité de partenariat',
                    'appel_projet' => 'Appel à projet',
                ][$opportuniteAffaire->type] ?? 'Opportunité d\'affaire',
            'id' => $opportuniteAffaire->id,
            'titre' => $opportuniteAffaire->titre,
            'statut' => 'rejeté',
            'message' => 'Votre opportunité d\'affaire a été rejetée.',
            'raison' => $request->reason
        ]));
        
        return response()->json([
            'message' => 'Opportunité d\'affaire rejetée avec succès',
            'opportuniteAffaire' => $opportuniteAffaire
        ]);
    }
    
    /**
     * Mettre à jour le statut d'une opportunité d'affaire
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
        
        $opportuniteAffaire = OpportuniteAffaire::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour le statut
        $opportuniteAffaire->statut = $request->statut;
        $opportuniteAffaire->save();
        
        // Notifier l'utilisateur que le statut de sa publication a été modifié
        $user = User::find($opportuniteAffaire->user->id);
        $user->notify(new PublicationStatusChanged([
            'type' => [
                    'partenariat' => 'Opportunité de partenariat',
                    'appel_projet' => 'Appel à projet',
                ][$opportuniteAffaire->type] ?? 'Opportunité d\'affaire',
            'id' => $opportuniteAffaire->id,
            'titre' => $opportuniteAffaire->titre,
            'statut' => $request->statut,
            'message' => 'Le statut de votre opportunité d\'affaire a été mis à jour.'
        ]));
        
        return response()->json([
            'message' => 'Statut de l\'opportunité d\'affaire mis à jour avec succès',
            'opportuniteAffaire' => $opportuniteAffaire
        ]);
    }
    
    /**
     * Mettre à jour l'état d'une opportunité d'affaire (disponible/terminé)
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
        
        $opportuniteAffaire = OpportuniteAffaire::findOrFail($id);
        
        // Vérifier si l'utilisateur est un administrateur
        if (!Auth::user()->is_admin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Mettre à jour l'état
        $opportuniteAffaire->etat = $request->etat;
        $opportuniteAffaire->save();
        
        return response()->json([
            'message' => 'État de l\'opportunité d\'affaire mis à jour avec succès',
            'opportuniteAffaire' => $opportuniteAffaire
        ]);
    }
    
    /**
     * Supprimer une opportunité d'affaires
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
            $opportunite = OpportuniteAffaire::findOrFail($id);
            
            // Supprimer le fichier de présentation associé s'il existe
            if ($opportunite->fichier_presentation && Storage::disk('public')->exists($opportunite->fichier_presentation)) {
                Storage::disk('public')->delete($opportunite->fichier_presentation);
            }
            
            // Supprimer les commentaires et likes associés
            $opportunite->comments()->delete();
            $opportunite->likes()->delete();
            
            // Supprimer l'opportunité d'affaires
            $opportunite->delete();
            
            return response()->json(['message' => 'Opportunité d\'affaires supprimée avec succès']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la suppression de l\'opportunité d\'affaires', 'error' => $e->getMessage()], 500);
        }
    }
}
