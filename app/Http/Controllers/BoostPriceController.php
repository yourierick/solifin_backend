<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class BoostPriceController extends Controller
{
    /**
     * Récupère le prix du boost par jour depuis les paramètres système
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBoostPrice()
    {
        // Récupérer le paramètre de prix du boost
        $setting = Setting::where('key', 'boost_price')->first();
        
        // Valeur par défaut si le paramètre n'est pas défini
        $defaultPrice = 1;
        
        // Si le paramètre existe, utiliser sa valeur, sinon utiliser la valeur par défaut
        $price = $setting ? $setting->value : $defaultPrice;
        
        return response()->json([
            'success' => true,
            'price' => $price
        ]);
    }
}
