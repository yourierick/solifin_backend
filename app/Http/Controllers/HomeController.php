<?php

namespace App\Http\Controllers;

use App\Models\Pack;
use App\Models\Advertisement;
use App\Models\JobOffer;
use App\Models\BusinessOpportunity;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class HomeController extends Controller
{
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