<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserPack;
use App\Models\Pack;

class UserPackController extends Controller
{
    /**
     * Vérifie le statut du pack de publication de l'utilisateur
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkPackStatus(Request $request)
    {
        $user = $request->user();
        $isActive = false;
        $packInfo = null;

        // Vérifier si l'utilisateur a un pack de publication assigné
        if ($user->pack_de_publication_id) {
            $pack = Pack::find($user->pack_de_publication_id);
            
            if ($pack) {
                // Vérifier si l'utilisateur a un pack actif dans UserPack
                $userPack = UserPack::where('user_id', $user->id)
                    ->where('pack_id', $user->pack_de_publication_id)
                    ->where('status', 'active')
                    ->first();
                
                $isActive = (bool) $userPack;
                
                $packInfo = [
                    'id' => $pack->id,
                    'name' => $pack->name,
                    'duree_publication_en_jour' => $pack->duree_publication_en_jour,
                    'description' => $pack->description,
                    'prix' => $pack->prix,
                    'user_pack_status' => $userPack ? $userPack->status : 'inactive',
                    'user_pack_expiry' => $userPack ? $userPack->expiry_date : null
                ];
            }
        }

        \Log::info($packInfo);

        return response()->json([
            'is_active' => $isActive,
            'pack' => $packInfo
        ]);
    }
}
