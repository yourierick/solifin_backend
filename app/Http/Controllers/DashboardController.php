<?php

namespace App\Http\Controllers;

use App\Models\Pack;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class DashboardController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $user = auth()->user();
        
        // Rediriger l'administrateur vers le dashboard admin
        if ($user->is_admin) {
            return response()->json(['redirect' => 'admin.dashboard']);
        }
        
        // Récupérer les statistiques
        $stats = [
            'direct_referrals' => $user->referrals()->count(),
            //'total_network' => $user->getAllDownlines()->count(),
            'wallet_balance' => $user->wallet->balance,
            'total_earned' => $user->wallet->total_earned,
            'total_withdrawn' => $user->wallet->total_withdrawn,
        ];

        // Récupérer les packs de l'utilisateur
        $userPacks = $user->packs()->with('users')->get();

        // Récupérer les packs disponibles que l'utilisateur peut acheter
        $availablePacks = Pack::active()
            ->whereNotIn('id', $userPacks->pluck('id'))
            ->get();

        // Récupérer les dernières transactions
        $recentTransactions = $user->wallet->transactions()
            ->latest()
            ->take(10)
            ->get();

        // Récupérer les filleuls par génération
        $referralsByGeneration = [];
        for ($i = 1; $i <= 4; $i++) {
            $referralsByGeneration[$i] = $this->getReferralsByGeneration($user, $i);
        }

        return response()->json([
            'stats' => $stats,
            'userPacks' => $userPacks,
            'availablePacks' => $availablePacks,
            'recentTransactions' => $recentTransactions,
            'referralsByGeneration' => $referralsByGeneration
        ]);
    }

    private function getReferralsByGeneration(User $user, int $generation)
    {
        if ($generation === 1) {
            return $user->referrals;
        }

        $referrals = collect();
        $previousGeneration = $this->getReferralsByGeneration($user, $generation - 1);

        foreach ($previousGeneration as $referral) {
            $referrals = $referrals->merge($referral->referrals);
        }

        return $referrals;
    }

    public function network()
    {
        $user = auth()->user();
        $referrals = $user->referrals()->with('packs')->paginate(20);

        return response()->json($referrals);
    }

    public function wallet()
    {
        $user = auth()->user();
        $transactions = $user->wallet->transactions()->latest()->paginate(20);

        return response()->json($transactions);
    }

    public function packs()
    {
        $user = auth()->user();
        $userPacks = $user->packs()->with('users')->get();
        $availablePacks = Pack::active()
            ->whereNotIn('id', $userPacks->pluck('id'))
            ->get();

        return response()->json([
            'userPacks' => $userPacks,
            'availablePacks' => $availablePacks
        ]);
    }
} 