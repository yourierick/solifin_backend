<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserPack;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\FormationPurchase;
use App\Models\UserFormationProgress;
use App\Models\UserModuleProgress;
use App\Models\Wallet;
use App\Models\WalletSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserFormationController extends Controller
{
    /**
     * Afficher la liste des formations disponibles pour l'utilisateur.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $userPackIds = UserPack::where('user_id', $user->id)
                                ->where('status', "active")
                                ->where('payment_status', "completed")
                                ->pluck('pack_id')
                                ->toArray();
            
            // Récupérer toutes les formations publiées
            $query = Formation::with([
                    'creator:id,name', 
                    'modules' => function($query) {
                        $query->select('id', 'formation_id', 'title', 'type', 'duration')
                            ->orderBy('order');
                    },
                    'packs:id,name' // Inclure tous les packs associés à la formation
                ])
                ->where('status', 'published')
                ->where('created_by', '!=', $user->id);
            
            // Filtrer par type si spécifié, sinon inclure toutes les formations admin et celles accessibles de type user
            if ($request->has('type')) {
                $query->where('type', $request->type);
            } else {
                $query->where(function($query) use ($userPackIds, $user) {
                    // Inclure toutes les formations admin (accessibles ou non)
                    $query->where('type', 'admin');
                    
                    // OU inclure toutes les formations de type user
                    $query->orWhere('type', 'user');
                });
            }
            
            // Filtrer par type si spécifié
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }
            
            // Recherche par titre ou description
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            $formations = $query->orderBy('created_at', 'desc')->paginate(10);
            
            // Ajouter les informations de progression et d'accès pour chaque formation
            $formations->getCollection()->transform(function($formation) use ($user, $userPackIds) {
                // Informations de progression
                $progress = UserFormationProgress::where('user_id', $user->id)
                                                ->where('formation_id', $formation->id)
                                                ->first();
                
                $formation->progress = $progress ? [
                    'is_completed' => $progress->is_completed,
                    'progress_percentage' => $progress->progress_percentage,
                    'started_at' => $progress->started_at,
                    'completed_at' => $progress->completed_at,
                ] : [
                    'is_completed' => false,
                    'progress_percentage' => 0,
                    'started_at' => null,
                    'completed_at' => null,
                ];
                
                // Déterminer si l'utilisateur a accès à cette formation
                $hasAccess = false;
                $requiredPacks = [];
                
                if ($formation->type === 'admin') {
                    // Pour les formations admin, vérifier si l'utilisateur a un des packs requis
                    $formationPackIds = $formation->packs->pluck('id')->toArray();
                    $hasAccess = count(array_intersect($userPackIds, $formationPackIds)) > 0;
                    
                    // Si l'utilisateur n'a pas accès, récupérer les noms des packs requis
                    if (!$hasAccess) {
                        $requiredPacks = $formation->packs->map(function($pack) {
                            return [
                                'id' => $pack->id,
                                'name' => $pack->name
                            ];
                        })->toArray();
                    }
                } else if ($formation->type === 'user') {
                    // Pour les formations user, vérifier si elle est gratuite, créée par l'utilisateur ou achetée
                    $hasAccess = !$formation->is_paid ||
                                 ($formation->is_paid && $formation->purchasers()->where('user_id', $user->id)->where('payment_status', 'completed')->exists());
                }
                
                $formation->access = [
                    'has_access' => $hasAccess,
                    'required_packs' => $requiredPacks
                ];
                
                return $formation;
            });
            
            return response()->json([
                'success' => true,
                'data' => $formations
            ]);
        }catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des formations: ' . $e->getMessage());
            \Log::error('Erreur lors de la récupération des formations: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des formations'
            ], 500);
        }
    }

    /**
     * Afficher les détails d'une formation avec ses modules.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();
        $formation = Formation::with([
            'creator:id,name',
            'modules' => function($query) {
                $query->orderBy('order');
            }
        ])->findOrFail($id);
        
        // Vérifier si l'utilisateur a accès à cette formation
        if (!$formation->isAccessibleByUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette formation'
            ], 403);
        }
        
        // Récupérer la progression de l'utilisateur pour cette formation
        $progress = UserFormationProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'formation_id' => $formation->id
            ],
            [
                'started_at' => now(),
            ]
        );
        
        $formation->progress = [
            'is_completed' => $progress->is_completed,
            'progress_percentage' => $progress->progress_percentage,
            'started_at' => $progress->started_at,
            'completed_at' => $progress->completed_at,
        ];
        
        // Ajouter les informations de progression pour chaque module
        $formation->modules->transform(function($module) use ($user) {
            $moduleProgress = UserModuleProgress::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'formation_module_id' => $module->id
                ],
                [
                    'started_at' => now(),
                ]
            );
            
            $module->progress = [
                'is_completed' => $moduleProgress->is_completed,
                'progress_percentage' => $moduleProgress->progress_percentage,
                'started_at' => $moduleProgress->started_at,
                'completed_at' => $moduleProgress->completed_at,
            ];
            
            // // Vérifier si l'utilisateur a accès à ce module spécifique
            // $module->is_accessible = $module->isAccessibleByUser($user);
            
            return $module;
        });
        
        return response()->json([
            'success' => true,
            'data' => $formation
        ]);
    }

    /**
     * Afficher les détails d'un module spécifique.
     *
     * @param int $formationId
     * @param int $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function showModule($formationId, $moduleId)
    {
        $user = Auth::user();
        $formation = Formation::findOrFail($formationId);
        $module = $formation->modules()->findOrFail($moduleId);
        
        // // Vérifier si l'utilisateur a accès à ce module
        // if (!$module->isAccessibleByUser($user)) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Vous n\'avez pas accès à ce module'
        //     ], 403);
        // }
        
        // Récupérer ou créer la progression de l'utilisateur pour ce module
        $progress = UserModuleProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'formation_module_id' => $module->id
            ],
            [
                'started_at' => now(),
            ]
        );
        
        $module->progress = [
            'is_completed' => $progress->is_completed,
            'progress_percentage' => $progress->progress_percentage,
            'started_at' => $progress->started_at,
            'completed_at' => $progress->completed_at,
        ];
        
        return response()->json([
            'success' => true,
            'data' => $module
        ]);
    }

    /**
     * Marquer un module comme complété.
     *
     * @param int $formationId
     * @param int $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeModule($formationId, $moduleId)
    {
        $user = Auth::user();
        $formation = Formation::findOrFail($formationId);
        $module = $formation->modules()->findOrFail($moduleId);
        
        // // Vérifier si l'utilisateur a accès à ce module
        // if (!$module->isAccessibleByUser($user)) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Vous n\'avez pas accès à ce module'
        //     ], 403);
        // }
        
        // Récupérer ou créer la progression de l'utilisateur pour ce module
        $progress = UserModuleProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'formation_module_id' => $module->id
            ],
            [
                'started_at' => now(),
            ]
        );
        
        // Marquer le module comme complété
        $progress->markAsCompleted();
        
        // Récupérer la progression mise à jour de la formation
        $formationProgress = UserFormationProgress::where('user_id', $user->id)
                                                 ->where('formation_id', $formationId)
                                                 ->first();
        
        return response()->json([
            'success' => true,
            'message' => 'Module marqué comme complété',
            'data' => [
                'module_progress' => [
                    'is_completed' => $progress->is_completed,
                    'progress_percentage' => $progress->progress_percentage,
                    'completed_at' => $progress->completed_at,
                ],
                'formation_progress' => [
                    'is_completed' => $formationProgress->is_completed,
                    'progress_percentage' => $formationProgress->progress_percentage,
                    'completed_at' => $formationProgress->completed_at,
                ]
            ]
        ]);
    }

    /**
     * Créer une nouvelle formation (pour les utilisateurs).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail' => 'nullable|image|max:2048', // Max 2MB
            'is_paid' => 'required',
            'price' => 'required_if:is_paid,true|nullable|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Traitement de l'image de couverture
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('formations/thumbnails', 'public');
        }
        
        // Création de la formation
        $formation = Formation::create([
            'title' => $request->title,
            'category' => $request->category,
            'description' => $request->description,
            'thumbnail' => $thumbnailPath ? asset('storage/' . $thumbnailPath) : null,
            'status' => 'draft', // Les formations créées par les utilisateurs doivent être validées
            'type' => 'user',
            'created_by' => $user->id,
            'is_paid' => $request->is_paid === "true" ? 1:0,
            'price' => $request->is_paid === "true" ? $request->price : null,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Formation créée avec succès et en attente de validation',
            'data' => $formation
        ], 201);
    }

    /**
     * Mettre à jour une formation créée par l'utilisateur.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $formation = Formation::where('created_by', $user->id)->findOrFail($id);
        
        // Vérifier si la formation peut être modifiée (seulement si elle est en brouillon ou rejetée)
        if (!in_array($formation->status, ['draft', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette formation ne peut pas être modifiée dans son état actuel'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail' => 'nullable|image|max:2048', // Max 2MB
            'is_paid' => 'required',
            'price' => 'required_if:is_paid,true|nullable|numeric|min:0',
            'currency' => 'required_if:is_paid,true|nullable|string|size:3',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Traitement de l'image de couverture
        if ($request->hasFile('thumbnail')) {
            // Supprimer l'ancienne image si elle existe
            if ($formation->thumbnail) {
                $oldPath = str_replace(asset('storage/'), '', $formation->thumbnail);
                Storage::disk('public')->delete($oldPath);
            }
            
            $thumbnailPath = $request->file('thumbnail')->store('formations/thumbnails', 'public');
            $formation->thumbnail = asset('storage/' . $thumbnailPath);
        }
        
        // Mise à jour de la formation
        $formation->title = $request->title;
        $formation->category = $request->category;
        $formation->description = $request->description;
        $formation->is_paid = $request->is_paid === "true" ? 1:0;
        $formation->price = $request->is_paid === "true" ? $request->price : null;
        $formation->status = 'draft'; // Remettre en draft après modification
        $formation->rejection_reason = null; // Effacer la raison du rejet précédent
        
        $formation->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Formation mise à jour avec succès et en attente de validation',
            'data' => $formation
        ]);
    }

    /**
     * Soumettre une formation pour validation.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit($id)
    {
        $user = Auth::user();
        $formation = Formation::where('created_by', $user->id)->findOrFail($id);
        
        // Vérifier si la formation peut être soumise (seulement si elle est en brouillon ou rejetée)
        if (!in_array($formation->status, ['draft', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette formation ne peut pas être soumise dans son état actuel'
            ], 400);
        }
        
        // Vérifier si la formation a au moins un module
        if ($formation->modules()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'La formation doit avoir au moins un module avant d\'être soumise'
            ], 400);
        }
        
        $formation->status = 'pending';
        $formation->rejection_reason = null; // Effacer la raison du rejet précédent
        $formation->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Formation soumise avec succès et en attente de validation',
            'data' => $formation
        ]);
    }


     /**
     * Récupère le pourcentage de frais d'achat depuis les paramètres du système
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPurchaseFeePercentage()
    {
        $feePercentage = \App\Models\Setting::where('key', 'purchase_fee_percentage')->first();
        
        if (!$feePercentage) {
            // Valeur par défaut si le paramètre n'existe pas
            $feePercentage = 0;
        } else {
            $feePercentage = floatval($feePercentage->value);
        }
        
        return response()->json([
            'success' => true,
            'fee_percentage' => $feePercentage
        ]);
    }

    /**
     * Récupère la liste des formations achetées par l'utilisateur connecté
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPurchasedFormations()
    {
        try {
            $user = Auth::user();
            
            // Récupérer les IDs des formations achetées
            $purchasedFormationIds = FormationPurchase::where('user_id', $user->id)
                                                    ->where('payment_status', 'completed')
                                                    ->pluck('formation_id')
                                                    ->toArray();
            
            // Récupérer les détails des formations achetées
            $formations = Formation::whereIn('id', $purchasedFormationIds)
                                ->where('status', 'published')
                                ->get();
            
            // Ajouter les informations de progression pour chaque formation
            $formations->each(function ($formation) use ($user) {
                $progress = UserFormationProgress::where('user_id', $user->id)
                                            ->where('formation_id', $formation->id)
                                            ->first();
                
                $formation->progress = $progress;
            });
            
            return response()->json([
                'success' => true,
                'data' => $formations
            ]);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des formations achetées',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Acheter une formation payante.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function purchase(Request $request, $id)
    {
        try {
            $formation = Formation::where('status', 'published')
                                ->where('is_paid', true)
                                ->findOrFail($id);

            $user = Auth::user();
            $walletPurchaser = $user->wallet;
            $walletSeller = $formation->creator->wallet;
            // Vérifier si l'utilisateur a déjà acheté cette formation
            $existingPurchase = FormationPurchase::where('user_id', $user->id)
                                                ->where('formation_id', $formation->id)
                                                ->where('payment_status', 'completed')
                                                ->first();
            if ($existingPurchase) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà acheté cette formation'
                ], 400);
            }

            $formationPrice = $formation->price;
            $feePercentage = \App\Models\Setting::where('key', 'purchase_fee_percentage')->first();
            if (!$feePercentage) {
                $purchaseFeePercentage = 0;
            } else {
                $purchaseFeePercentage = floatval($feePercentage->value);
            }

            $fees = $formationPrice * $purchaseFeePercentage / 100;


            $totalAmount = $formationPrice + $fees;

            if ($walletPurchaser->balance < $totalAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas assez d\'argent sur votre portefeuille pour acheter cette formation'
                ], 400);
            }

            DB::beginTransaction();

            $walletPurchaser->withdrawFunds($totalAmount, "purchase", "completed", ["formation" => $formation->title, "montant"=>$formation->price, "Vendeur"=>$formation->creator->name, "description"=>"Vous avez achaté la formation titrée " . $formation->title . "à l'utilisateur " . $formation->creator->name]);
            $walletSeller->addFunds($formationPrice, "sale", "completed", ["formation" => $formation->title, "montant"=>$formation->price, "Acheteur"=>$user->name, "description"=>"Vous avez vendu la formation titrée " . $formation->title ." à l'utilisateur " . $user->name]);
            
            $walletsystem = WalletSystem::first();
            if (!$walletsystem) {
                $walletsystem = WalletSystem::create([
                    "balance" => 0,
                    "total_in" => 0,
                    "total_out" => 0,
                ]);
            }
            $walletsystem->addFunds($fees, "frais d achat", "completed", [
                "user" => $user->name, 
                "Opération" => "Achat de formation", 
                "Méthode de paiement" => "SOLIFIN WALLET",
                "Type de paiement" => "Achat direct",
                "Titre de la formation" => $formation->title, 
                "Auteur" => $formation->creator->name,
                "Prix de la formation" => $formation->price . "$", 
                "Frais" => $fees . "$",
            ]);
            
            // Créer l'achat (en attente de paiement)
            $purchase = FormationPurchase::create([
                'user_id' => $user->id,
                'formation_id' => $formation->id,
                'amount_paid' => $formation->price,
                'currency' => "$",
                'payment_method' => "SOLIFIN WALLET",
                'payment_status' => 'completed',
                'transaction_id' => 'sim_' . uniqid(),
                "purchased_at" => now(),
            ]);
            
            // Créer ou mettre à jour la progression de l'utilisateur pour cette formation
            UserFormationProgress::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'formation_id' => $formation->id
                ],
                [
                    'started_at' => now(),
                ]
            );

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Formation achetée avec succès',
                'data' => $purchase
            ]);
        }catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de l'achat de la formation"
            ], 500);
        }
    }

    /**
     * Obtenir la liste des formations créées par l'utilisateur.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myFormations(Request $request)
    {
        $user = Auth::user();
        
        $query = Formation::with(['modules' => function($query) {
            $query->select('id', 'formation_id', 'title', 'type', 'duration')
                  ->orderBy('order');
        }])
            ->where('created_by', $user->id);
        
        // Filtrer par statut si spécifié
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $formations = $query->orderBy('created_at', 'desc')->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => $formations
        ]);
    }

    /**
     * Afficher les détails d'un module spécifique sans avoir besoin de l'ID de la formation.
     * Cette méthode est utilisée par le composant QuizPlayer.
     *
     * @param int $moduleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function showModuleWithoutFormation($moduleId)
    {
        $user = Auth::user();
        $module = FormationModule::findOrFail($moduleId);
        $formation = $module->formation;
        
        // Vérifier si l'utilisateur a accès à ce module
        // if (!$module->isAccessibleByUser($user)) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Vous n\'avez pas accès à ce module'
        //     ], 403);
        // }
        
        // Récupérer ou créer la progression de l'utilisateur pour ce module
        $progress = UserModuleProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'formation_module_id' => $module->id
            ],
            [
                'started_at' => now(),
            ]
        );
        
        $module->progress = [
            'is_completed' => $progress->is_completed,
            'progress_percentage' => $progress->progress_percentage,
            'started_at' => $progress->started_at,
            'completed_at' => $progress->completed_at,
        ];
        
        return response()->json([
            'success' => true,
            'data' => $module
        ]);
    }
}
