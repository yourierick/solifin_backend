<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Setting;

class CountrySettingsController extends Controller
{
    /**
     * Récupérer les paramètres de pays
     */
    public function index()
    {
        try {
            // Récupérer les paramètres de pays depuis la base de données
            $countrySettings = Setting::where('key', 'country_restrictions')->first();
            $isRestrictionEnabled = Setting::where('key', 'enable_country_restrictions')->first();

            $countries = [];
            $enabled = false;

            if ($countrySettings) {
                $countries = json_decode($countrySettings->value, true) ?: [];
            }

            if ($isRestrictionEnabled) {
                $enabled = (bool) $isRestrictionEnabled->value;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'countries' => $countries,
                    'is_restriction_enabled' => $enabled
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des paramètres de pays: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres de pays'
            ], 500);
        }
    }

    /**
     * Mettre à jour les paramètres de pays
     */
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'countries' => 'required|array',
                'is_restriction_enabled' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Mettre à jour les paramètres de pays
            Setting::updateOrCreate(
                ['key' => 'country_restrictions'],
                ['value' => json_encode($request->countries)]
            );

            // Mettre à jour l'état d'activation des restrictions
            Setting::updateOrCreate(
                ['key' => 'enable_country_restrictions'],
                ['value' => $request->is_restriction_enabled ? '1' : '0']
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paramètres de pays mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la mise à jour des paramètres de pays: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des paramètres de pays'
            ], 500);
        }
    }

    /**
     * Changer le statut d'un pays spécifique (autorisé/bloqué)
     */
    public function toggleStatus(Request $request, $countryCode)
    {
        try {
            $validator = Validator::make($request->all(), [
                'is_allowed' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Récupérer les paramètres de pays actuels
            $countrySettings = Setting::where('key', 'country_restrictions')->first();
            $countries = [];

            if ($countrySettings) {
                $countries = json_decode($countrySettings->value, true) ?: [];
            }

            // Rechercher le pays dans la liste
            $countryIndex = array_search($countryCode, array_column($countries, 'code'));
            
            if ($countryIndex !== false) {
                // Mettre à jour le statut du pays existant
                $countries[$countryIndex]['is_allowed'] = $request->is_allowed;
            } else {
                // Le pays n'existe pas dans la liste, retourner une erreur
                return response()->json([
                    'success' => false,
                    'message' => 'Pays non trouvé dans la configuration'
                ], 404);
            }

            // Sauvegarder les modifications
            Setting::updateOrCreate(
                ['key' => 'country_restrictions'],
                ['value' => json_encode($countries)]
            );

            return response()->json([
                'success' => true,
                'message' => 'Statut du pays mis à jour avec succès',
                'data' => [
                    'country_code' => $countryCode,
                    'is_allowed' => $request->is_allowed
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du statut du pays: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut du pays'
            ], 500);
        }
    }

    /**
     * Activer ou désactiver le mode de restriction global
     */
    public function toggleGlobalRestriction(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'is_restriction_enabled' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Mettre à jour l'état d'activation des restrictions
            Setting::updateOrCreate(
                ['key' => 'enable_country_restrictions'],
                ['value' => $request->is_restriction_enabled ? '1' : '0']
            );

            return response()->json([
                'success' => true,
                'message' => 'Mode de restriction global mis à jour avec succès',
                'data' => [
                    'is_restriction_enabled' => (bool) $request->is_restriction_enabled
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du mode de restriction global: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du mode de restriction global'
            ], 500);
        }
    }
}
