<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestimonialController extends Controller
{
    public function index(Request $request)
    {
        $query = Testimonial::with('user')->latest();

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', $request->input('min_rating'));
        }
        
        if ($request->filled('featured')) {
            $query->where('featured', $request->boolean('featured'));
        }

        $perPage = $request->input('per_page', 10);
        $testimonials = $query->paginate($perPage);
        foreach ($testimonials as $testimonial) {
            $testimonial->user->profile_picture = asset('storage/' . $testimonial->user->picture);
        }

        return response()->json([
            'success' => true,
            'testimonials' => $testimonials
        ]);
    }

    public function show($id)
    {
        $testimonial = Testimonial::with('user')->find($id);
        
        if (!$testimonial) {
            return response()->json([
                'success' => false,
                'message' => 'Témoignage non trouvé'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'testimonial' => $testimonial
        ]);
    }

    public function approve($id)
    {
        $testimonial = Testimonial::find($id);
        
        if (!$testimonial) {
            return response()->json([
                'success' => false,
                'message' => 'Témoignage non trouvé'
            ], 404);
        }
        
        try {
            $testimonial->update([
                'status' => 'approved'
            ]);
            
            Log::info('Témoignage approuvé', ['testimonial_id' => $testimonial->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Témoignage approuvé avec succès',
                'testimonial' => $testimonial->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'approbation du témoignage', [
                'testimonial_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'approbation du témoignage'
            ], 500);
        }
    }

    public function reject($id)
    {
        $testimonial = Testimonial::find($id);
        
        if (!$testimonial) {
            return response()->json([
                'success' => false,
                'message' => 'Témoignage non trouvé'
            ], 404);
        }
        
        try {
            $testimonial->update([
                'status' => 'rejected'
            ]);
            
            Log::info('Témoignage rejeté', ['testimonial_id' => $testimonial->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Témoignage rejeté avec succès',
                'testimonial' => $testimonial->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du rejet du témoignage', [
                'testimonial_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du rejet du témoignage'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $testimonial = Testimonial::find($id);
        
        if (!$testimonial) {
            return response()->json([
                'success' => false,
                'message' => 'Témoignage non trouvé'
            ], 404);
        }
        
        try {
            $testimonial->delete();
            
            Log::info('Témoignage supprimé', ['testimonial_id' => $id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Témoignage supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du témoignage', [
                'testimonial_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression du témoignage'
            ], 500);
        }
    }
    
    /**
     * Mettre en avant un témoignage
     *
     * @param int $id ID du témoignage
     * @return \Illuminate\Http\JsonResponse
     */
    public function feature($id)
    {
        $testimonial = Testimonial::find($id);
        
        if (!$testimonial) {
            return response()->json([
                'success' => false,
                'message' => 'Témoignage non trouvé'
            ], 404);
        }
        
        try {
            $testimonial->update([
                'featured' => true
            ]);
            
            Log::info('Témoignage mis en avant', ['testimonial_id' => $testimonial->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Témoignage mis en avant avec succès',
                'testimonial' => $testimonial->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise en avant du témoignage', [
                'testimonial_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise en avant du témoignage'
            ], 500);
        }
    }
    
    /**
     * Retirer la mise en avant d'un témoignage
     *
     * @param int $id ID du témoignage
     * @return \Illuminate\Http\JsonResponse
     */
    public function unfeature($id)
    {
        $testimonial = Testimonial::find($id);
        
        if (!$testimonial) {
            return response()->json([
                'success' => false,
                'message' => 'Témoignage non trouvé'
            ], 404);
        }
        
        try {
            $testimonial->update([
                'featured' => false
            ]);
            
            Log::info('Mise en avant du témoignage retirée', ['testimonial_id' => $testimonial->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Mise en avant du témoignage retirée avec succès',
                'testimonial' => $testimonial->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du retrait de la mise en avant du témoignage', [
                'testimonial_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du retrait de la mise en avant du témoignage'
            ], 500);
        }
    }
    
    /**
     * Compte le nombre de témoignages en attente de modération
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function countPending()
    {
        try {
            $count = Testimonial::where('status', 'pending')->count();
            
            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du comptage des témoignages en attente', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du comptage des témoignages en attente',
                'count' => 0
            ], 500);
        }
    }
} 