<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\User;
use App\Models\Pack;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    protected $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * Récupérer la liste des commissions avec pagination et filtres
     */
    public function index(Request $request)
    {
        $query = Commission::with(['sponsor_user', 'source_user', 'pack']);

        // Filtres
        if ($request->has('status') && $request->status != 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('sponsor_user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('source_user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('pack_id') && $request->pack_id) {
            $query->where('pack_id', $request->pack_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Tri
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 10);
        $commissions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $commissions
        ]);
    }

    /**
     * Récupérer les statistiques des commissions
     */
    public function statistics()
    {
        $stats = [
            'total_commissions' => Commission::count(),
            'total_amount' => Commission::sum('amount'),
            'pending_count' => Commission::where('status', 'pending')->count(),
            'pending_amount' => Commission::where('status', 'pending')->sum('amount'),
            'completed_count' => Commission::where('status', 'completed')->count(),
            'completed_amount' => Commission::where('status', 'completed')->sum('amount'),
            'failed_count' => Commission::where('status', 'failed')->count(),
            'failed_amount' => Commission::where('status', 'failed')->sum('amount'),
            'recent_commissions' => Commission::with(['sponsor_user', 'source_user', 'pack'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
            'commissions_by_status' => [
                'pending' => Commission::where('status', 'pending')->count(),
                'completed' => Commission::where('status', 'completed')->count(),
                'failed' => Commission::where('status', 'failed')->count()
            ],
            'commissions_by_pack' => DB::table('commissions')
                ->join('packs', 'commissions.pack_id', '=', 'packs.id')
                ->select('packs.name', DB::raw('count(*) as count'), DB::raw('sum(commissions.amount) as total_amount'))
                ->where('commissions.status', 'completed')
                ->groupBy('packs.name')
                ->get(),
            'commissions_by_level' => DB::table('commissions')
                ->select('level', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
                ->where('status', 'completed')
                ->groupBy('level')
                ->get(),
            'monthly_commissions' => DB::table('commissions')
                ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'), DB::raw('sum(amount) as total_amount'))
                ->where('status', 'completed')
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Récupérer les détails d'une commission
     */
    public function show($id)
    {
        $commission = Commission::with(['sponsor_user', 'source_user', 'pack'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $commission
        ]);
    }

    /**
     * Relancer une commission qui a échoué
     */
    public function retry($id)
    {
        $commission = Commission::findOrFail($id);

        if ($commission->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les commissions échouées peuvent être relancées'
            ], 400);
        }

        // Mettre à jour le statut à pending
        $commission->update([
            'status' => 'pending',
            'error_message' => null
        ]);

        // Relancer le traitement
        $duration_months = $commission->duree;
        $result = $this->commissionService->processCommission($commission->id, $duration_months);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Commission relancée avec succès',
                'data' => Commission::with(['sponsor_user', 'source_user', 'pack'])->find($id)
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Échec de la relance de la commission',
                'data' => Commission::with(['sponsor_user', 'source_user', 'pack'])->find($id)
            ], 500);
        }
    }

    /**
     * Récupérer la liste des packs pour les filtres
     */
    public function getPacks()
    {
        $packs = Pack::select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'data' => $packs
        ]);
    }

    /**
     * Récupérer les erreurs les plus fréquentes
     */
    public function commonErrors()
    {
        $errors = DB::table('commissions')
            ->select('error_message', DB::raw('count(*) as count'))
            ->where('status', 'failed')
            ->whereNotNull('error_message')
            ->groupBy('error_message')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $errors
        ]);
    }
}
