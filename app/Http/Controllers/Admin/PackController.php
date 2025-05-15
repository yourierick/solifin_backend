<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\User;
use App\Models\CommissionRate;
use App\Models\UserPack;
use App\Models\BonusRates;
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
        \Log::info($request->all());
        try {
            $validated = $request->validate([
                'categorie' => ['required'],
                'name' => ['required', 'max:255', 'string', Rule::unique('packs')],
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'status' => 'required|boolean',
                'avantages' => 'required|json',
                'duree_publication_en_jour' => 'required|numeric|min:1',
                'formations' => 'nullable|file|mimes:zip,rar,7z|max:102400',
                'abonnement' => 'required|string|in:mensuel,trimestriel,semestriel,annuel',
            ]);


            //Gérer le fichier de formations
            if ($request->hasFile('formations')) {
                $formationsPath = $request->file('formations')->store('formations', 'public');
                $validated['formations'] = $formationsPath;
            }

            // Créer le pack
            $pack = Pack::create([
                'categorie' => $validated['categorie'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'price' => $validated['price'],
                'status' => $request->boolean('status'),
                'avantages' => json_decode($request->avantages, true),
                'duree_publication_en_jour' => $validated['duree_publication_en_jour'],
                'formations' => $validated['formations'] ?? null,
                'abonnement' => $validated['abonnement'],
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
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du pack'
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
            'categorie' => 'required',
            'duree_publication_en_jour' => 'required|numeric|min:1',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'status' => 'required|boolean',
            'avantages' => 'required|json',
            'formations' => 'nullable|file|mimes:zip,rar,7z|max:102400',
            'abonnement' => 'required|string|in:mensuel,trimestriel,semestriel,annuel',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Gérer le fichier des formations
            if ($request->hasFile('formations')) {
                // Supprimer l'ancien fichier s'il existe
                if ($pack->formations && Storage::disk('public')->exists($pack->formations)) {
                    Storage::disk('public')->delete($pack->formations);
                }

                $file = $request->file('formations');
                $path = $file->store('formations', 'public');
                $pack->formations = $path;
            } elseif ($request->has('delete_formations') && $request->delete_formations) {
                // Supprimer le fichier existant sans en ajouter un nouveau
                if ($pack->formations && Storage::disk('public')->exists($pack->formations)) {
                    Storage::disk('public')->delete($pack->formations);
                }
                $pack->formations = null;
            }

            $pack->update([
                'categorie' => $request->categorie,
                'duree_publication_en_jour' => $request->duree_publication_en_jour,
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'status' => filter_var($request->status, FILTER_VALIDATE_BOOLEAN),
                'avantages' => $request->avantages,
                'abonnement' => $request->abonnement,
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

            //Supprimer le fichier des formations s'il existe
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

    public function getBonusRates($packId)
    {
        $bonusRates = BonusRates::where('pack_id', $packId)->get();
        
        return response()->json([
            'success' => true,
            'bonusRates' => $bonusRates
        ]);
    }

    public function storeBonusRate(Request $request, $packId)
    {
        $request->validate([
            'frequence' => 'required|in:daily,weekly,monthly,yearly',
            'nombre_filleuls' => 'required|integer|min:1',
            'points_attribues' => 'required|integer|min:1',
            'valeur_point' => 'required|numeric|min:0.01',
        ]);

        $pack = Pack::findOrFail($packId);
        
        $bonusRate = BonusRates::create([
            'pack_id' => $packId,
            'frequence' => $request->frequence,
            'nombre_filleuls' => $request->nombre_filleuls,
            'points_attribues' => $request->points_attribues,
            'valeur_point' => $request->valeur_point,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Taux de bonus ajouté avec succès',
            'bonusRate' => $bonusRate
        ]);
    }

    public function updateBonusRate(Request $request, $id)
    {
        $request->validate([
            'frequence' => 'required|in:daily,weekly,monthly,yearly',
            'nombre_filleuls' => 'required|integer|min:1',
            'points_attribues' => 'required|integer|min:1',
            'valeur_point' => 'required|numeric|min:0.01',
        ]);

        $bonusRate = BonusRates::findOrFail($id);
        
        $bonusRate->update([
            'frequence' => $request->frequence,
            'nombre_filleuls' => $request->nombre_filleuls,
            'points_attribues' => $request->points_attribues,
            'valeur_point' => $request->valeur_point,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Taux de bonus mis à jour avec succès',
            'bonusRate' => $bonusRate
        ]);
    }

    public function deleteBonusRate($id)
    {
        $bonusRate = BonusRates::findOrFail($id);
        $bonusRate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Taux de bonus supprimé avec succès'
        ]);
    }
}