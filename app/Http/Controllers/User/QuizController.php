<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\FormationModule;
use App\Models\QuizAttempt;
use App\Models\UserModuleProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class QuizController extends Controller
{
    /**
     * Soumettre les réponses à un quiz
     *
     * @param Request $request
     * @param int $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitAnswers(Request $request, $moduleId)
    {
        try {
            $user = Auth::user();
            $module = FormationModule::findOrFail($moduleId);
            
            // Vérifier que le module est bien un quiz
            if ($module->type !== 'quiz') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce module n\'est pas un quiz'
                ], 400);
            }
            
            // // Vérifier que l'utilisateur a accès au module
            // if (!$module->isAccessibleByUser($user)) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Vous n\'avez pas accès à ce module'
            //     ], 403);
            // }
            
            // Valider les réponses
            $validator = Validator::make($request->all(), [
                'answers' => 'required|array'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Récupérer le contenu du quiz (questions et réponses correctes)
            $quizContent = json_decode($module->content, true);
            $questions = $quizContent['questions'] ?? [];
            
            if (empty($questions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce quiz ne contient aucune question'
                ], 400);
            }
            
            // Calculer le score
            $score = 0;
            $totalQuestions = count($questions);
            $userAnswers = $request->answers;
            $results = [];
            
            // Log pour déboguer
            \Log::info('Quiz submission - Total questions: ' . $totalQuestions);
            \Log::info('Quiz submission - User answers: ' . json_encode($userAnswers));
            
            foreach ($questions as $index => $question) {
                $questionId = $question['id'] ?? $index;
                $correctAnswers = $question['correctAnswers'] ?? [];
                $userAnswer = $userAnswers[$questionId] ?? null;
                $isCorrect = $this->isAnswerCorrect($userAnswer, $correctAnswers);
                
                // Ajouter le résultat pour cette question
                $results[$questionId] = [
                    'isCorrect' => $isCorrect,
                    'userAnswer' => $userAnswer,
                    'correctAnswers' => $correctAnswers
                ];
                
                // Incrémenter le score si la réponse est correcte
                if ($isCorrect) {
                    $score++;
                    \Log::info("Question {$questionId} correcte - Score actuel: {$score}");
                } else {
                    \Log::info("Question {$questionId} incorrecte - Réponse utilisateur: {$userAnswer}, Réponses correctes: " . json_encode($correctAnswers));
                }
            }
            
            // Enregistrer la tentative
            $attempt = QuizAttempt::create([
                'user_id' => $user->id,
                'module_id' => $module->id,
                'answers' => $userAnswers,
                'score' => $score,
                'total_questions' => $totalQuestions,
                'completed_at' => now()
            ]);
            
            // Mettre à jour la progression de l'utilisateur
            UserModuleProgress::updateOrCreate(
                ['user_id' => $user->id, 'formation_module_id' => $module->id],
                ['status' => 'completed', 'completed_at' => now()]
            );
            
            // Calculer le pourcentage
            $percentage = ($totalQuestions > 0) ? round(($score / $totalQuestions) * 100, 2) : 0;
            
            // Log pour déboguer le calcul final
            \Log::info("Calcul final - Score: {$score}, Total questions: {$totalQuestions}, Pourcentage: {$percentage}%");
            
            // Retourner le résultat avec les bonnes réponses
            return response()->json([
                'success' => true,
                'data' => [
                    'score' => $score,
                    'totalQuestions' => $totalQuestions,
                    'percentage' => $percentage,
                    'results' => $results,
                    'questions' => $questions // Inclut les questions complètes
                ]
            ]);
        }catch (\Exception $e) {
            \Log::error('Erreur lors de l\'enregistrement des réponses: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la soumission des réponses'
            ], 500);
        }
    }
    
    /**
     * Vérifier si une réponse est correcte
     *
     * @param mixed $userAnswer
     * @param array $correctAnswers
     * @return bool
     */
    private function isAnswerCorrect($userAnswer, $correctAnswers)
    {
        // Si l'utilisateur n'a pas répondu
        if ($userAnswer === null) {
            return false;
        }
        
        // Si c'est une question à choix unique
        if (!is_array($userAnswer)) {
            return in_array($userAnswer, $correctAnswers);
        }
        
        // Si c'est une question à choix multiples
        if (empty($userAnswer)) {
            return false;
        }
        
        // Trier les tableaux pour une comparaison précise
        sort($userAnswer);
        sort($correctAnswers);
        
        // Vérifier si les tableaux sont identiques
        return $userAnswer == $correctAnswers;
    }
    
    /**
     * Récupérer les résultats d'un quiz
     *
     * @param int $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResults($moduleId)
    {
        $user = Auth::user();
        $module = FormationModule::findOrFail($moduleId);
        
        // Vérifier que le module est bien un quiz
        if ($module->type !== 'quiz') {
            return response()->json([
                'success' => false,
                'message' => 'Ce module n\'est pas un quiz'
            ], 400);
        }
        
        // // Vérifier que l'utilisateur a accès au module
        // if (!$module->isAccessibleByUser($user)) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Vous n\'avez pas accès à ce module'
        //     ], 403);
        // }
        
        // Récupérer la dernière tentative
        $attempt = QuizAttempt::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->latest()
            ->first();
            
        if (!$attempt) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune tentative trouvée pour ce quiz',
                'data' => null
            ], 404);
        }
        
        // Récupérer le contenu du quiz
        $quizContent = json_decode($module->content, true);
        $questions = $quizContent['questions'] ?? [];
        
        // Préparer les résultats détaillés
        $results = [];
        foreach ($questions as $index => $question) {
            $questionId = $question['id'] ?? $index;
            $correctAnswers = $question['correctAnswers'] ?? [];
            $userAnswer = $attempt->answers[$questionId] ?? null;
            
            $results[$questionId] = [
                'isCorrect' => $this->isAnswerCorrect($userAnswer, $correctAnswers),
                'userAnswer' => $userAnswer,
                'correctAnswers' => $correctAnswers
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'score' => $attempt->score,
                'totalQuestions' => $attempt->total_questions,
                'percentage' => $attempt->percentage,
                'results' => $results,
                'questions' => $questions,
                'completedAt' => $attempt->completed_at
            ]
        ]);
    }
}
