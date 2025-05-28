<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Les clés de paramètres autorisées
     */
    protected $allowedKeys = [
        'withdrawal_commission',
        'boost_price',
        'withdrawal_fee_percentage',
        'sending_fee_percentage',
        'transfer_fee_percentage',
        'purchase_fee_percentage',
    ];

    /**
     * Récupère un paramètre par sa clé.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByKey($key)
    {
        if (!in_array($key, $this->allowedKeys)) {
            return response()->json([
                'success' => false,
                'message' => 'Clé de paramètre non autorisée.'
            ], 400);
        }

        $setting = Setting::where('key', $key)->first();
        
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètre non trouvé.'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'setting' => $setting
        ]);
    }

    /**
     * Met à jour un paramètre existant par sa clé ou le crée s'il n'existe pas.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateByKey(Request $request, $key)
    {
        // Vérifier que la clé est autorisée
        if (!in_array($key, $this->allowedKeys)) {
            return response()->json([
                'success' => false,
                'message' => 'Clé de paramètre non autorisée.'
            ], 400);
        }

        // Validation des données
        $validator = Validator::make($request->all(), [
            'value' => 'required|string',
            'description' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Validation spécifique selon la clé
        if (in_array($key, [
            'withdrawal_commission', 
            'withdrawal_fee_percentage', 
            'sending_fee_percentage', 
            'transfer_fee_percentage',
            'purchase_fee_percentage',
        ])) {
            $value = floatval($request->value);
            if ($value < 0 || $value > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'La valeur doit être un nombre entre 0 et 100.'
                ], 400);
            }
        } elseif ($key === 'boost_price') {
            $value = floatval($request->value);
            if ($value <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'La valeur doit être un nombre positif.'
                ], 400);
            }
        }

        // Recherche du paramètre
        $setting = Setting::where('key', $key)->first();
        
        if (!$setting) {
            // Si le paramètre n'existe pas, on le crée
            $setting = Setting::create([
                'key' => $key,
                'value' => $request->value,
                'description' => $request->description
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paramètre créé avec succès.',
                'setting' => $setting
            ], 201);
        }

        // Mise à jour du paramètre existant
        $setting->value = $request->value;
        $setting->description = $request->description;
        $setting->save();

        return response()->json([
            'success' => true,
            'message' => 'Paramètre mis à jour avec succès.',
            'setting' => $setting
        ]);
    }

    /**
     * Récupère tous les paramètres.
     * 
     * Conservé pour compatibilité avec le frontend.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $settings = Setting::all();
        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }
}
