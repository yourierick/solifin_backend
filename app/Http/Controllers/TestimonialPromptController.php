<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TestimonialPrompt;
use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\TestimonialSubmitted;
use App\Services\TestimonialPromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TestimonialPromptController extends Controller
{
    /**
     * Le service de gestion des invitations à témoigner.
     *
     * @var \App\Services\TestimonialPromptService
     */
    protected $testimonialPromptService;

    /**
     * Crée une nouvelle instance du contrôleur.
     *
     * @param \App\Services\TestimonialPromptService $testimonialPromptService
     * @return void
     */
    public function __construct(TestimonialPromptService $testimonialPromptService)
    {
        $this->testimonialPromptService = $testimonialPromptService;
    }

    /**
     * Récupère les invitations actives pour l'utilisateur connecté.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivePrompts(): JsonResponse
    {
        $user = Auth::user();
        $prompts = $this->testimonialPromptService->getActivePromptsForUser($user);
        
        return response()->json([
            'success' => true,
            'prompts' => $prompts,
        ]);
    }

    /**
     * Marque une invitation comme affichée.
     *
     * @param string $id ID de l'invitation
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsDisplayed(string $id): JsonResponse
    {
        $user = Auth::user();
        $prompt = TestimonialPrompt::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$prompt) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation non trouvée',
            ], 404);
        }
        
        $result = $prompt->markAsDisplayed();
        
        return response()->json([
            'success' => $result,
            'prompt' => $prompt,
        ]);
    }

    /**
     * Marque une invitation comme cliquée.
     *
     * @param string $id ID de l'invitation
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsClicked(string $id): JsonResponse
    {
        $user = Auth::user();
        $prompt = TestimonialPrompt::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$prompt) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation non trouvée',
            ], 404);
        }
        
        $result = $prompt->markAsClicked();
        
        return response()->json([
            'success' => $result,
            'prompt' => $prompt,
        ]);
    }

    /**
     * Soumet un témoignage en réponse à une invitation.
     *
     * @param Request $request Requête contenant les données du témoignage
     * @param string $id ID de l'invitation
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitTestimonial(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $prompt = TestimonialPrompt::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$prompt) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation non trouvée',
            ], 404);
        }
        
        // Valider les données du témoignage
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:10|max:1000',
            'rating' => 'required|integer|min:1|max:5',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        // Créer le témoignage
        $testimonial = Testimonial::create([
            'user_id' => $user->id,
            'content' => $request->content,
            'rating' => $request->rating,
            'status' => "pending", // En attente de modération par défaut
        ]);
        
        // Mettre à jour le statut de l'invitation
        $prompt->markAsSubmitted($testimonial->id);
        
        // Envoyer une notification aux administrateurs
        $admins = User::where('is_admin', 1)->get();
        
        foreach ($admins as $admin) {
            $admin->notify(new TestimonialSubmitted([
                'titre' => "Temoignage de ". $user->name,
                'id' => $testimonial->id,
                'rating' => $testimonial->rating,
                'user_id' => $user->id,
                'user_name' => $user->name
            ]));
        }
        
        Log::info('Notification de nouveau témoignage envoyée aux administrateurs', [
            'testimonial_id' => $testimonial->id,
            'user_id' => $user->id
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Témoignage soumis avec succès et en attente de modération',
            'testimonial' => $testimonial,
            'prompt' => $prompt,
        ]);
    }

    /**
     * Décline une invitation à témoigner.
     *
     * @param string $id ID de l'invitation
     * @return \Illuminate\Http\JsonResponse
     */
    public function declinePrompt(string $id): JsonResponse
    {
        $user = Auth::user();
        $prompt = TestimonialPrompt::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$prompt) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation non trouvée',
            ], 404);
        }
        
        $result = $prompt->markAsDeclined();
        
        return response()->json([
            'success' => $result,
            'message' => 'Invitation déclinée avec succès',
            'prompt' => $prompt,
        ]);
    }
}
