<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\FormationPurchase;
use App\Models\UserFormationProgress;
use App\Models\UserModuleProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FormationStatsController extends Controller
{
    /**
     * Récupérer les statistiques d'une formation pour son créateur
     *
     * @param int $formationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFormationStats($formationId)
    {
        $user = Auth::user();
        $formation = Formation::findOrFail($formationId);
        
        // Vérifier que l'utilisateur est le créateur de la formation
        if ($formation->created_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à voir ces statistiques'
            ], 403);
        }
        
        // Statistiques d'achat
        $purchases = FormationPurchase::where('formation_id', $formationId)
            ->select(
                DB::raw('COUNT(*) as total_purchases'),
                DB::raw('SUM(amount_paid) as total_revenue'),
                DB::raw('COUNT(CASE WHEN payment_status = "completed" THEN 1 END) as completed_purchases'),
                DB::raw('COUNT(CASE WHEN payment_status = "pending" THEN 1 END) as pending_purchases'),
                DB::raw('COUNT(CASE WHEN payment_status = "failed" THEN 1 END) as failed_purchases')
            )
            ->first();
        
        // Statistiques de progression (exclure le créateur)
        $progressStats = UserFormationProgress::where('formation_id', $formationId)
            ->where('user_id', '!=', $user->id)
            ->select(
                DB::raw('COUNT(*) as total_users'),
                DB::raw('COUNT(CASE WHEN is_completed = 1 THEN 1 END) as completed_users'),
                DB::raw('AVG(progress_percentage) as average_progress')
            )
            ->first();
        
        // Statistiques par module (exclure le créateur)
        $moduleStats = UserModuleProgress::join('formation_modules', 'user_module_progress.formation_module_id', '=', 'formation_modules.id')
            ->join('users', 'user_module_progress.user_id', '=', 'users.id')
            ->where('formation_modules.formation_id', $formationId)
            ->where('users.id', '!=', $user->id)
            ->select(
                'formation_modules.id',
                'formation_modules.title',
                'formation_modules.type',
                DB::raw('COUNT(user_module_progress.id) as total_users'),
                DB::raw('COUNT(CASE WHEN user_module_progress.is_completed = 1 THEN 1 END) as completed_users'),
                DB::raw('AVG(user_module_progress.progress_percentage) as average_progress')
            )
            ->groupBy('formation_modules.id', 'formation_modules.title', 'formation_modules.type')
            ->get();
        
        // Progression dans le temps (par semaine)
        $weeklyProgress = UserFormationProgress::where('formation_id', $formationId)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%u") as week'),
                DB::raw('COUNT(*) as new_users'),
                DB::raw('COUNT(CASE WHEN is_completed = 1 THEN 1 END) as completed_users'),
                DB::raw('AVG(progress_percentage) as average_progress')
            )
            ->groupBy('week')
            ->orderBy('week')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'purchases' => $purchases,
                'progress' => $progressStats,
                'modules' => $moduleStats,
                'weekly_progress' => $weeklyProgress
            ]
        ]);
    }
}
