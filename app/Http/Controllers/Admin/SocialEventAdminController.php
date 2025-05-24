<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialEvent;
use App\Models\SocialEventReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SocialEventAdminController extends Controller
{
    /**
     * Afficher tous les statuts sociaux pour l'administration.
     */
    public function index()
    {
        $socialEvents = SocialEvent::with(['user', 'reports'])
            ->orderBy('created_at', 'desc')
            ->get();

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
            
            // Ajouter le nombre de signalements
            $socialEvent->reports_count = $socialEvent->reports->count();
            
            // Regrouper les raisons de signalement pour une meilleure analyse
            $reasonCounts = [];
            foreach ($socialEvent->reports as $report) {
                if (!isset($reasonCounts[$report->reason])) {
                    $reasonCounts[$report->reason] = 0;
                }
                $reasonCounts[$report->reason]++;
            }
            $socialEvent->report_reasons = $reasonCounts;
            
            // Déterminer si le statut nécessite une attention urgente (plus de 3 signalements)
            $socialEvent->needs_attention = $socialEvent->reports_count >= 3;
        }

        return response()->json($socialEvents);
    }
    
    /**
     * Approuver un statut social.
     */
    public function approve($id)
    {
        $socialEvent = SocialEvent::findOrFail($id);
        $socialEvent->statut = 'approuvé';
        $socialEvent->created_at = now();
        $socialEvent->save();

        return response()->json(['message' => 'Statut social approuvé avec succès']);
    }

    /**
     * Rejeter un statut social.
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'raison_rejet' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        \Log::info($validator->fails());

        $socialEvent = SocialEvent::findOrFail($id);
        $socialEvent->statut = 'rejeté';
        $socialEvent->raison_rejet = $request->raison_rejet;
        $socialEvent->save();

        return response()->json(['message' => 'Statut social rejeté avec succès']);
    }

    /**
     * Mettre à jour le statut d'un statut social.
     */
    public function updateStatus(Request $request, $id)
    {
        $socialEvent = SocialEvent::findOrFail($id);
        $socialEvent->statut = 'en_attente';
        $socialEvent->save();
        
        return response()->json(['message' => 'Statut social mis en attente avec succès']);
    }

    /**
     * Supprimer un statut social.
     */
    public function destroy($id)
    {
        $socialEvent = SocialEvent::findOrFail($id);

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
}
