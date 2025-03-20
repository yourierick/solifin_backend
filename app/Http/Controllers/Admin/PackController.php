<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\User;
use App\Models\CommissionRate;
use App\Models\UserPack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

#[\Illuminate\Routing\Middleware\Authenticate]
#[\App\Http\Middleware\AdminMiddleware]
class PackController extends Controller
{
    public function index()
    {
        try {
            $packs = Pack::all();
            
            return response()->json([
                'success' => true,
                'packs' => $packs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des packs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'name' => ['required', 'max:255', 'string', Rule::unique('packs')],
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'status' => 'required|boolean',
                'avantages' => 'required|json',
                'formations' => 'required|file|mimes:zip,rar,7z|max:102400',
            ]);


            // Gérer le fichier de formations
            if ($request->hasFile('formations')) {
                $formationsPath = $request->file('formations')->store('formations', 'public');
                $validated['formations'] = $formationsPath;
            }

            // Créer le pack
            $pack = Pack::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'price' => $validated['price'],
                'status' => $request->boolean('status'),
                'avantages' => json_decode($request->avantages, true),
                'formations' => $validated['formations'] ?? null,
            ]);

            //Attribuer automatiquement le pack aux administrateurs
            $admins = User::where('is_admin', true)->get();
            foreach ($admins as $admin) {
                $referralLetter = substr($pack->name, 0, 1);
                $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $referralCode = 'SPR' . $referralLetter . $referralNumber;

                // Vérifier que le code est unique
                while (UserPack::where('referral_code', $referralCode)->exists()) {
                    $referralNumber = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $referralCode = 'SPR' . $referralLetter . $referralNumber;
                }

                // Récupérer l'URL du frontend depuis le fichier .env
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

                // Créer le lien de parrainage en utilisant l'URL du frontend
                $referralLink = $frontendUrl . "/register?referral_code=" . $referralCode;

                $admin->packs()->attach($pack->id, [
                    'status' => 'active',
                    'purchase_date' => now(),
                    'expiry_date' => null, // Durée illimitée pour les admins
                    'is_admin_pack' => true,
                    'payment_status' => 'completed',
                    'referral_prefix' => 'SPR',
                    'referral_pack_name' => $pack->name,
                    'referral_letter' => $referralLetter,
                    'referral_number' => $referralNumber,
                    'referral_code' => $referralCode,
                    'link_referral' => $referralLink,
                ]);
            }

            //Créer définir les taux de commission à zéro pour ce pack créé
            for ($i = 1; $i <= 4; $i++) {
                $commissionrate = CommissionRate::create([
                    'pack_id' => $pack->id,
                    'level' => $i,
                    'rate' => 0, 
                ]);
            }


            return response()->json([
                'success' => true,
                'message' => 'Pack créé avec succès',
                'data' => $pack
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($formationsPath)) {
                Storage::disk('public')->delete($formationsPath);
            }
            
            Log::error('Erreur dans PackController@store: ' . $e->getMessage());
            Log::error($validated);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du pack'
            ], 500);
        }
    }

    
    public function renew(Request $request, UserPack $userPack)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validate([
                'duration_months' => 'required|integer|min:1',
            ]);

            // Si le pack est expiré, on met à jour la date d'expiration à partir de maintenant
            // Sinon, on ajoute la durée à la date d'expiration existante
            $newExpiryDate = $userPack->status === 'expired' 
                ? now()->addMonths($validated['duration_months'])
                : $userPack->expiry_date->addMonths($validated['duration_months']);

            $userPack->update([
                'status' => 'active',
                'expiry_date' => $newExpiryDate,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pack renouvelé avec succès',
                'data' => $userPack
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur dans PackController@renew: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du renouvellement du pack'
            ], 500);
        }
    }

    public function show(Pack $pack)
    {
        return response()->json([
            'success' => true,
            'data' => $pack
        ]);
    }

    public function update(Request $request, Pack $pack)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'status' => 'required|boolean',
            'avantages' => 'required|json',
            'formations' => 'nullable|file|mimes:zip,rar,7z|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Gérer le fichier des formations s'il est présent
            if ($request->hasFile('formations')) {
                // Supprimer l'ancien fichier s'il existe
                if ($pack->formations && Storage::exists($pack->formations)) {
                    Storage::delete($pack->formations);
                }

                $file = $request->file('formations');
                $path = $file->store('formations');
                $pack->formations = $path;
            }

            $pack->update([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'status' => filter_var($request->status, FILTER_VALIDATE_BOOLEAN),
                'avantages' => $request->avantages,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pack mis à jour avec succès',
                'data' => $pack
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du pack'
            ], 500);
        }
    }

    public function destroy(Pack $pack)
    {
        try {
            DB::beginTransaction();

            // Supprimer le fichier des formations s'il existe
            if ($pack->formations && Storage::exists($pack->formations)) {
                Storage::delete($pack->formations);
            }

            $pack->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pack supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression du pack'
            ], 500);
        }
    }

    public function toggleStatus(Pack $pack)
    {
        try {
            $pack->update([
                'status' => !$pack->status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Statut du pack mis à jour avec succès',
                'data' => $pack
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du statut'
            ], 500);
        }
    }

    public function updateCommissionRate(Request $request, $packId)
    {
        $request->validate([
            'level' => 'required|integer|between:1,4',
            'commission_rate' => 'required|numeric|min:0|max:100'
        ]);

        $pack = Pack::findOrFail($packId);
        
        // Mettre à jour ou créer le taux de commission pour le niveau spécifié
        CommissionRate::updateOrCreate(
            [
                'pack_id' => $packId,
                'level' => $request->level
            ],
            ['rate' => $request->commission_rate]
        );

        return response()->json(['message' => 'Taux de commission mis à jour avec succès']);
    }

    public function getCommissionRates($packId)
    {
        $commissionRates = CommissionRate::where('pack_id', $packId)
            ->orderBy('level')
            ->get();

        // Assurer que nous avons les 4 niveaux, même si certains n'existent pas encore
        $rates = [];
        for ($i = 1; $i <= 4; $i++) {
            $rate = $commissionRates->firstWhere('level', $i);
            $rates[$i] = $rate ? $rate->rate : 0;
        }

        return response()->json(['rates' => $rates]);
    }
}