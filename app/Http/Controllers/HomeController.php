<?php

namespace App\Http\Controllers;

use App\Models\Pack;
use App\Models\Publicite; // Correction du modèle
use App\Models\JobOffer;
use App\Models\BusinessOpportunity;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Retourner les publicités approuvées (pour le carrousel)
     */
    public function approvedAds()
    {
        try {
            $ads = Publicite::where('statut', 'approuvé')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function($ad) {
                    // Générer les URLs complètes pour image/vidéo si besoin
                    $ad->image_url = $ad->image ? asset('storage/' . $ad->image) : null;
                    $ad->video_url = $ad->video ? asset('storage/' . $ad->video) : null;
                    return $ad;
                });
            return response()->json([
                'success' => true,
                'ads' => $ads
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des publicités',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function index()
    {
        try {
            $packs = Pack::where('status', true)->get();
            
            return response()->json([
                'success' => true,
                'data' => $packs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des packs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
} 