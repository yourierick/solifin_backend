<?php

namespace App\Http\Controllers;

use App\Models\Pack;
use App\Models\UserPack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PackController extends Controller
{
    public function getUserPacks()
    {
        try {
            $userPacks = UserPack::with('pack')
                ->where('user_id', Auth::id())
                ->get()
                ->map(function ($userPack) {
                    $userPack->is_admin_pack = $userPack->pack->is_admin_pack ?? false;
                    return $userPack;
                });

            return response()->json([
                'success' => true,
                'data' => $userPacks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des packs'
            ], 500);
        }
    }

    public function renewPack(Request $request, Pack $pack)
    {
        try {
            $request->validate([
                'duration_months' => 'required|integer|min:1'
            ]);

            DB::beginTransaction();

            // Vérifier si l'utilisateur a déjà ce pack
            $userPack = UserPack::where('user_id', Auth::id())
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                throw new \Exception('Pack non trouvé');
            }

            // Calculer la nouvelle date d'expiration
            $newExpiryDate = $userPack->status === 'expired' 
                ? Carbon::now() 
                : Carbon::parse($userPack->expiry_date);
            
            $newExpiryDate->addMonths($request->duration_months);

            // Mettre à jour le pack utilisateur
            $userPack->update([
                'status' => 'active',
                'expiry_date' => $newExpiryDate,
                'last_renewal_date' => Carbon::now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pack renouvelé avec succès',
                'data' => $userPack
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
