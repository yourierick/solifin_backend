<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FaqController extends Controller
{
    /**
     * Récupère toutes les FAQ avec leurs catégories et questions connexes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $faqs = Faq::with(['category', 'relatedFaqs'])
                ->orderBy('order')
                ->get();
                
            return response()->json($faqs);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des FAQ', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des FAQ'
            ], 500);
        }
    }
    
    /**
     * Récupère toutes les catégories de FAQ
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategories()
    {
        try {
            // Récupérer les catégories avec le comptage des FAQ associées
            $categories = FaqCategory::withCount('faqs')
                ->orderBy('order')
                ->get();
                
            return response()->json($categories);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des catégories de FAQ', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des catégories'
            ], 500);
        }
    }
    
    /**
     * Enregistre un vote pour une FAQ
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function vote(Request $request, $id)
    {
        try {
            $faq = Faq::findOrFail($id);
            
            if ($request->helpful) {
                $faq->increment('helpful_votes');
            } else {
                $faq->increment('unhelpful_votes');
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Vote enregistré avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'enregistrement du vote', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement du vote'
            ], 500);
        }
    }
    
    /**
     * Recherche dans les FAQ
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            $query = $request->input('query');
            
            $faqs = Faq::with(['category', 'relatedFaqs'])
                ->where('question', 'like', "%{$query}%")
                ->orWhere('answer', 'like', "%{$query}%")
                ->orderBy('order')
                ->get();
                
            return response()->json($faqs);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la recherche dans les FAQ', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la recherche'
            ], 500);
        }
    }
    
    /**
     * Crée une nouvelle FAQ
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'question' => 'required|string|max:255',
                'answer' => 'required|string',
                'category_id' => 'required|exists:faq_categories,id',
                'is_published' => 'boolean'
            ]);
            
            // Déterminer l'ordre maximum actuel et ajouter 1
            $maxOrder = Faq::max('order') ?? 0;
            $validatedData['order'] = $maxOrder + 1;
            
            $faq = Faq::create($validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'FAQ créée avec succès',
                'data' => $faq
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de la FAQ', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la FAQ'
            ], 500);
        }
    }
    
    /**
     * Met à jour une FAQ existante
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $faq = Faq::findOrFail($id);
            
            $validatedData = $request->validate([
                'question' => 'string|max:255',
                'answer' => 'string',
                'category_id' => 'exists:faq_categories,id',
                'is_published' => 'boolean'
            ]);
            
            $faq->update($validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'FAQ mise à jour avec succès',
                'data' => $faq
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la FAQ', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour de la FAQ'
            ], 500);
        }
    }
    
    /**
     * Supprime une FAQ
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $faq = Faq::findOrFail($id);
            
            // Supprimer les relations avec d'autres FAQ
            DB::table('faq_related')->where('faq_id', $id)->orWhere('related_faq_id', $id)->delete();
            
            $faq->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'FAQ supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la FAQ', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression de la FAQ'
            ], 500);
        }
    }
    
    /**
     * Met à jour l'ordre d'une FAQ
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateOrder(Request $request, $id)
    {
        try {
            $faq = Faq::findOrFail($id);
            $direction = $request->input('direction');
            
            if ($direction === 'up' && $faq->order > 1) {
                // Trouver la FAQ avec l'ordre précédent
                $previousFaq = Faq::where('order', $faq->order - 1)->first();
                
                if ($previousFaq) {
                    $previousFaq->order = $faq->order;
                    $previousFaq->save();
                    
                    $faq->order = $faq->order - 1;
                    $faq->save();
                }
            } elseif ($direction === 'down') {
                // Trouver la FAQ avec l'ordre suivant
                $nextFaq = Faq::where('order', $faq->order + 1)->first();
                
                if ($nextFaq) {
                    $nextFaq->order = $faq->order;
                    $nextFaq->save();
                    
                    $faq->order = $faq->order + 1;
                    $faq->save();
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Ordre mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de l\'ordre de la FAQ', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour de l\'ordre'
            ], 500);
        }
    }
    
    /**
     * Récupère les FAQ connexes à une FAQ spécifique
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRelatedFaqs($id)
    {
        try {
            $faq = Faq::findOrFail($id);
            $relatedFaqs = $faq->relatedFaqs;
            
            return response()->json($relatedFaqs);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des FAQ connexes', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des FAQ connexes'
            ], 500);
        }
    }
    
    /**
     * Ajoute une FAQ connexe à une FAQ spécifique
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function addRelatedFaq(Request $request, $id)
    {
        try {
            $faq = Faq::findOrFail($id);
            $relatedFaqId = $request->input('related_faq_id');
            
            // Vérifier que la FAQ connexe existe
            $relatedFaq = Faq::findOrFail($relatedFaqId);
            
            // Vérifier que la relation n'existe pas déjà
            $existingRelation = DB::table('faq_related')
                ->where('faq_id', $id)
                ->where('related_faq_id', $relatedFaqId)
                ->exists();
                
            if ($existingRelation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette relation existe déjà'
                ], 422);
            }
            
            // Ajouter la relation
            DB::table('faq_related')->insert([
                'faq_id' => $id,
                'related_faq_id' => $relatedFaqId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'FAQ connexe ajoutée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'ajout d\'une FAQ connexe', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'ajout d\'une FAQ connexe'
            ], 500);
        }
    }
    
    /**
     * Supprime une FAQ connexe d'une FAQ spécifique
     *
     * @param int $id
     * @param int $relatedId
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeRelatedFaq($id, $relatedId)
    {
        try {
            // Supprimer la relation
            $deleted = DB::table('faq_related')
                ->where('faq_id', $id)
                ->where('related_faq_id', $relatedId)
                ->delete();
                
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Relation non trouvée'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'FAQ connexe supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression d\'une FAQ connexe', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression d\'une FAQ connexe'
            ], 500);
        }
    }
    
    /**
     * Récupère les statistiques de vues des FAQ
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getViewStats()
    {
        try {
            // Dans une implémentation réelle, on récupérerait les vues depuis une table de statistiques
            // Pour cette démonstration, on renvoie des données simulées basées sur les votes
            $faqs = Faq::select('id', 'question', 'helpful_votes', 'unhelpful_votes')
                ->orderBy('helpful_votes', 'desc')
                ->limit(10)
                ->get()
                ->map(function($faq) {
                    // Simuler un nombre de vues basé sur les votes
                    $views = ($faq->helpful_votes + $faq->unhelpful_votes) * 5 + rand(10, 100);
                    return [
                        'id' => $faq->id,
                        'question' => $faq->question,
                        'views' => $views
                    ];
                });
                
            return response()->json($faqs);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques de vues', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des statistiques de vues'
            ], 500);
        }
    }
    
    /**
     * Crée une nouvelle catégorie de FAQ
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeCategory(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:faq_categories,name',
                'icon' => 'nullable|string|max:50'
            ]);
            
            // Déterminer l'ordre maximum actuel et ajouter 1
            $maxOrder = FaqCategory::max('order') ?? 0;
            $validatedData['order'] = $maxOrder + 1;
            
            $category = FaqCategory::create($validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Catégorie créée avec succès',
                'data' => $category
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de la catégorie', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la catégorie'
            ], 500);
        }
    }
    
    /**
     * Met à jour une catégorie de FAQ existante
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCategory(Request $request, $id)
    {
        try {
            $category = FaqCategory::findOrFail($id);
            
            $validatedData = $request->validate([
                'name' => 'string|max:255|unique:faq_categories,name,' . $id,
                'icon' => 'nullable|string|max:50'
            ]);
            
            $category->update($validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Catégorie mise à jour avec succès',
                'data' => $category
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de la catégorie', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour de la catégorie'
            ], 500);
        }
    }
    
    /**
     * Supprime une catégorie de FAQ
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyCategory($id)
    {
        try {
            $category = FaqCategory::findOrFail($id);
            
            // Mettre à jour les FAQ associées à cette catégorie
            Faq::where('category_id', $id)->update(['category_id' => null]);
            
            $category->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Catégorie supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la catégorie', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression de la catégorie'
            ], 500);
        }
    }
}
