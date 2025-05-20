<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestimonialController extends Controller
{
    /**
     * Récupère les témoignages approuvés et mis en avant pour affichage public
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeatured()
    {
        try {
            // Récupérer les témoignages approuvés et mis en avant
            $testimonials = Testimonial::with('user')
                ->where('status', 'approved')
                ->where('featured', true)
                ->latest()
                ->take(6) // Limiter à 6 témoignages
                ->get();
            
            // Formater les données pour le frontend
            $formattedTestimonials = $testimonials->map(function ($testimonial) {
                return [
                    'id' => $testimonial->id,
                    'name' => $testimonial->user->name,
                    'role' => 'Membre depuis ' . $testimonial->user->created_at->format('Y'),
                    'image' => $testimonial->user->picture 
                        ? url('storage/' . $testimonial->user->picture) 
                        : null,
                    'content' => $testimonial->content,
                    'rating' => $testimonial->rating,
                ];
            });
            
            return response()->json([
                'success' => true,
                'testimonials' => $formattedTestimonials
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des témoignages mis en avant', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des témoignages'
            ], 500);
        }
    }
    
    /**
     * Récupère tous les témoignages approuvés pour affichage public
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getApproved()
    {
        try {
            // Récupérer tous les témoignages approuvés
            $testimonials = Testimonial::with('user')
                ->where('status', 'approved')
                ->latest()
                ->paginate(12);
            
            // Formater les données pour le frontend
            $formattedTestimonials = $testimonials->through(function ($testimonial) {
                return [
                    'id' => $testimonial->id,
                    'name' => $testimonial->user->name,
                    'role' => 'Membre depuis ' . $testimonial->user->created_at->format('Y'),
                    'image' => $testimonial->user->profile_photo_path 
                        ? url('storage/' . $testimonial->user->profile_photo_path) 
                        : null,
                    'content' => $testimonial->content,
                    'rating' => $testimonial->rating,
                    'featured' => $testimonial->featured,
                ];
            });
            
            return response()->json([
                'success' => true,
                'testimonials' => $formattedTestimonials
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des témoignages approuvés', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des témoignages'
            ], 500);
        }
    }
}
