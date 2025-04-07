<?php

namespace App\Http\Controllers;

use App\Models\Publicite;
use App\Models\OffreEmploi;
use App\Models\OpportuniteAffaire;
use App\Notifications\PublicationStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminPublicationController extends Controller
{
    /**
     * Récupérer toutes les publications en attente de validation
     *
     * @return \Illuminate\Http\Response
     */
    public function getPendingPublications()
    {
        // Vérifier si l'utilisateur est admin
        $user = Auth::user();
        if (!$user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        // Récupérer les publicités en attente
        $publicites = Publicite::with('page.user')
            ->where('statut', 'en_attente')
            ->orderBy('created_at', 'asc')
            ->get();

        // Récupérer les offres d'emploi en attente
        $offresEmploi = OffreEmploi::with('page.user')
            ->where('statut', 'en_attente')
            ->orderBy('created_at', 'asc')
            ->get();

        // Récupérer les opportunités d'affaires en attente
        $opportunitesAffaires = OpportuniteAffaire::with('page.user')
            ->where('statut', 'en_attente')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'publicites' => $publicites,
            'offresEmploi' => $offresEmploi,
            'opportunitesAffaires' => $opportunitesAffaires
        ]);
    }

    /**
     * Valider ou rejeter une publication (publicité, offre d'emploi, opportunité d'affaire)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function validatePublication(Request $request)
    {
        // Vérifier si l'utilisateur est admin
        $user = Auth::user();
        if (!$user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:publicite,offre_emploi,opportunite_affaire',
            'id' => 'required|integer',
            'action' => 'required|in:approuver,rejeter',
            'motif_rejet' => 'required_if:action,rejeter|nullable|string',
            'etat' => 'nullable|in:disponible,terminé',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $type = $request->type;
        $id = $request->id;
        $action = $request->action;
        $statut = $action === 'approuver' ? 'approuvé' : 'rejeté';
        $etat = $request->etat ?? 'disponible';

        // Mettre à jour le statut de la publication selon son type
        switch ($type) {
            case 'publicite':
                $publication = Publicite::findOrFail($id);
                break;
            case 'offre_emploi':
                $publication = OffreEmploi::findOrFail($id);
                break;
            case 'opportunite_affaire':
                $publication = OpportuniteAffaire::findOrFail($id);
                break;
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Type de publication non valide'
                ], 400);
        }

        // Sauvegarder l'ancien statut avant de le mettre à jour
        $oldStatut = $publication->statut;
        
        $publication->statut = $statut;
        if ($request->has('etat')) {
            $publication->etat = $etat;
        }
        $publication->save();

        // Envoyer une notification à l'utilisateur pour l'informer de la validation/rejet
        try {
            $user = $publication->page->user;
            if ($user) {
                $user->notify(new PublicationStatusChanged(
                    $publication,
                    $type,
                    $oldStatut,
                    $statut
                ));
            }
        } catch (\Exception $e) {
            // Log de l'erreur mais continuation du processus
            \Log::error('Erreur lors de l\'envoi de la notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Publication ' . ($action === 'approuver' ? 'approuvée' : 'rejetée') . ' avec succès',
            'publication' => $publication
        ]);
    }

    /**
     * Récupérer les statistiques des publications
     *
     * @return \Illuminate\Http\Response
     */
    public function getPublicationStats()
    {
        // Vérifier si l'utilisateur est admin
        $user = Auth::user();
        if (!$user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        // Statistiques des publicités
        $publiciteStats = [
            'total' => Publicite::count(),
            'en_attente' => Publicite::where('statut', 'en_attente')->count(),
            'approuvées' => Publicite::where('statut', 'approuvé')->count(),
            'rejetées' => Publicite::where('statut', 'rejeté')->count(),
            'expirées' => Publicite::where('statut', 'expiré')->count(),
            'disponibles' => Publicite::where('etat', 'disponible')->count(),
            'terminées' => Publicite::where('etat', 'terminé')->count(),
        ];

        // Statistiques des offres d'emploi
        $offreEmploiStats = [
            'total' => OffreEmploi::count(),
            'en_attente' => OffreEmploi::where('statut', 'en_attente')->count(),
            'approuvées' => OffreEmploi::where('statut', 'approuvé')->count(),
            'rejetées' => OffreEmploi::where('statut', 'rejeté')->count(),
            'expirées' => OffreEmploi::where('statut', 'expiré')->count(),
            'disponibles' => OffreEmploi::where('etat', 'disponible')->count(),
            'terminées' => OffreEmploi::where('etat', 'terminé')->count(),
        ];

        // Statistiques des opportunités d'affaires
        $opportuniteAffaireStats = [
            'total' => OpportuniteAffaire::count(),
            'en_attente' => OpportuniteAffaire::where('statut', 'en_attente')->count(),
            'approuvées' => OpportuniteAffaire::where('statut', 'approuvé')->count(),
            'rejetées' => OpportuniteAffaire::where('statut', 'rejeté')->count(),
            'expirées' => OpportuniteAffaire::where('statut', 'expiré')->count(),
            'disponibles' => OpportuniteAffaire::where('etat', 'disponible')->count(),
            'terminées' => OpportuniteAffaire::where('etat', 'terminé')->count(),
        ];

        return response()->json([
            'success' => true,
            'publiciteStats' => $publiciteStats,
            'offreEmploiStats' => $offreEmploiStats,
            'opportuniteAffaireStats' => $opportuniteAffaireStats
        ]);
    }
}
