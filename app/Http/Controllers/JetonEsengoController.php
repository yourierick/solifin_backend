<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BonusPointsService;
use App\Models\TicketGagnant;
use App\Models\Cadeau;
use App\Models\BonusRates;
use App\Models\UserBonusPoint;
use App\Models\UserJetonEsengo;
use App\Models\UserJetonEsengoHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class JetonEsengoController extends Controller
{
    protected $bonusPointsService;

    /**
     * Constructeur
     *
     * @param BonusPointsService $bonusPointsService
     */
    public function __construct(BonusPointsService $bonusPointsService)
    {
        $this->bonusPointsService = $bonusPointsService;
    }

    /**
     * Récupère les jetons Esengo disponibles pour l'utilisateur connecté
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJetonsEsengo()
    {
        $user = Auth::user();
        
        // Récupérer tous les jetons Esengo disponibles (non utilisés et non expirés) de l'utilisateur
        $jetonsDisponibles = UserJetonEsengo::where('user_id', $user->id)
            ->where('is_used', false)
            ->where('date_expiration', '>', now())
            ->with('pack') // Charger la relation pack pour avoir accès au nom du pack
            ->get()
            ->map(function($jeton) {
                // Récupérer l'historique d'attribution pour avoir la date d'attribution
                $attribution = UserJetonEsengoHistory::where('jeton_id', $jeton->id)
                    ->where('action_type', UserJetonEsengoHistory::ACTION_ATTRIBUTION)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                return [
                    'id' => $jeton->id,
                    'code_unique' => $jeton->code_unique,
                    'created_at' => $jeton->created_at->toDateTimeString(),
                    'date_attribution' => $attribution ? $attribution->created_at->toDateTimeString() : $jeton->created_at->toDateTimeString(),
                    'date_expiration' => $jeton->date_expiration->toDateTimeString(),
                    'pack_id' => $jeton->pack_id,
                    'pack_name' => $jeton->pack ? $jeton->pack->name : 'Pack inconnu',
                    'metadata' => $jeton->metadata
                ];
            })
            ->toArray();
        
        return response()->json([
            'success' => true,
            'jetons_disponibles' => $jetonsDisponibles,
            'total' => count($jetonsDisponibles)
        ]);
    }

    /**
     * Utilise un jeton Esengo pour tourner la roue et obtenir un cadeau
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function useJetonEsengo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jeton_id' => 'required|exists:user_jeton_esengos,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Le jeton est requis',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $user = Auth::user();
        $jeton = UserJetonEsengo::find($request->jeton_id);
        
        if (!$jeton) {
            return response()->json([
                'success' => false,
                'message' => 'Jeton non trouvé'
            ], 404);
        }
        
        $jetonCode = $jeton->code_unique;
        
        $result = $this->bonusPointsService->useJetonEsengo($user->id, $jetonCode);
        
        
        if (!$result['success']) {
            return response()->json($result, 400);
        }

        \Log::info($result['ticket']);
        $ticket = $result['ticket'];
        $ticket['cadeau'] = $result['cadeau'];
        return response()->json(
            [
                'success' => true,
                'message' => 'Félicitations ! Vous avez gagné un cadeau',
                'ticket' => $ticket,
            ]
        );
    }

    /**
     * Récupère tous les tickets gagnants de l'utilisateur connecté
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTicketsGagnants()
    {
        $user = Auth::user();
        
        $tickets = TicketGagnant::with('cadeau')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'tickets' => $tickets
        ]);
    }

    /**
     * Récupère les détails d'un ticket gagnant spécifique (pour l'administration)
     *
     * @param int $id ID du ticket
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTicketDetails($id)
    {
        $user = Auth::user();
        
        $ticket = TicketGagnant::with('cadeau')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();
        
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket non trouvé'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'ticket' => $ticket
        ]);
    }

    /**
     * Récupère la liste des cadeaux disponibles (pour l'administrateur)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCadeaux()
    {
        // Vérifier que l'utilisateur est administrateur
        if (!Auth::user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }
        
        $cadeaux = Cadeau::with('pack')->orderBy('nom')->get();

        return response()->json([
            'success' => true,
            'cadeaux' => $cadeaux
        ]);
    }

    /**
     * Crée ou met à jour un cadeau (pour l'administrateur)
     *
     * @param Request $request
     * @param int|null $id ID du cadeau (null pour création)
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveCadeau(Request $request, $id = null)
    {
        try {
            \Log::info($request->all());
            // Vérifier que l'utilisateur est administrateur
            if (!Auth::user()->is_admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'pack_id' => 'required|exists:packs,id',
                'nom' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image_url' => 'nullable|image|max:1024', // 1MB max, optionnel
                'valeur' => 'required|numeric|min:0',
                'probabilite' => 'required|numeric|min:0|max:100',
                'stock' => 'required|integer|min:0',
            ]);

            \Log::info($validator->errors());
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            // Préparer les données pour la création ou la mise à jour
            $cadeauData = $request->except('image_url');
            
            // Traiter l'image si elle est fournie
            if ($request->hasFile('image_url') && $request->file('image_url')->isValid()) {
                $path = $request->file('image_url')->store('cadeaux', 'public');
                $data['image_url'] = $path;
                
                // Convertir le chemin pour l'accès public
                $cadeauData['image_url'] = asset('storage/' . $path);
            }

            if ($request->actif === "true" || $request->actif == 1) {
                $cadeauData['actif'] = 1;
            } else {
                $cadeauData['actif'] = 0;
            }
            
            if ($id) {
                // Mise à jour
                $cadeau = Cadeau::find($id);
                
                if (!$cadeau) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cadeau non trouvé'
                    ], 404);
                }
                
                $cadeau->update($cadeauData);
                $message = 'Cadeau mis à jour avec succès';
            } else {
                // Création
                $cadeau = Cadeau::create($cadeauData);
                $message = 'Cadeau créé avec succès';
            }
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
                'cadeau' => $cadeau
            ]);   
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du cadeau'
            ], 500);
        }
    }

    /**
     * Supprime un cadeau (pour l'administrateur)
     *
     * @param int $id ID du cadeau à supprimer
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteCadeau($id)
    {
        try {
            // Vérifier que l'utilisateur est administrateur
            if (!Auth::user()->is_admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }
            
            // Trouver le cadeau
            $cadeau = Cadeau::find($id);
            
            if (!$cadeau) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cadeau non trouvé'
                ], 404);
            }
            
            // Vérifier si le cadeau est lié à des tickets gagnants
            $ticketsCount = $cadeau->ticketsGagnants()->count();
            if ($ticketsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce cadeau car il est lié à ' . $ticketsCount . ' ticket(s) gagnant(s)'
                ], 422);
            }
            
            // Supprimer l'image associée si elle existe
            if ($cadeau->image_url) {
                $imagePath = str_replace(asset('storage/'), '', $cadeau->image_url);
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }
            
            // Supprimer le cadeau
            $cadeau->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Cadeau supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression du cadeau',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère l'historique complet d'un jeton Esengo spécifique
     *
     * @param int $jetonId ID du jeton
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJetonHistory($jetonId)
    {
        $user = Auth::user();
        
        // Vérifier que le jeton existe et appartient à l'utilisateur
        $jeton = UserJetonEsengo::where('id', $jetonId)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$jeton) {
            return response()->json([
                'success' => false,
                'message' => 'Jeton non trouvé ou non autorisé'
            ], 404);
        }
        
        // Récupérer l'historique complet du jeton
        $history = UserJetonEsengoHistory::where('jeton_id', $jetonId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($entry) {
                return [
                    'id' => $entry->id,
                    'action' => $entry->action_type,
                    'description' => $entry->description,
                    'created_at' => $entry->created_at->format('Y-m-d H:i:s'),
                    'metadata' => $entry->metadata
                ];
            });
        
        return response()->json([
            'success' => true,
            'jeton' => [
                'id' => $jeton->id,
                'code_unique' => $jeton->code_unique,
                'is_used' => $jeton->is_used,
                'created_at' => $jeton->created_at->format('Y-m-d H:i:s'),
                'date_expiration' => $jeton->date_expiration ? $jeton->date_expiration->format('Y-m-d H:i:s') : null,
                'date_utilisation' => $jeton->date_utilisation ? $jeton->date_utilisation->format('Y-m-d H:i:s') : null,
                'pack_id' => $jeton->pack_id,
                'metadata' => $jeton->metadata
            ],
            'history' => $history
        ]);
    }

    /**
     * Récupère les jetons Esengo expirés de l'utilisateur connecté
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExpiredJetons()
    {
        $user = Auth::user();
        
        // Récupérer tous les jetons Esengo expirés de l'utilisateur
        $jetonsExpires = UserJetonEsengo::where('user_id', $user->id)
            ->where('is_used', false)
            ->where('date_expiration', '<=', now())
            ->with('pack') // Charger la relation pack pour avoir accès au nom du pack
            ->get()
            ->map(function($jeton) {
                // Récupérer l'historique d'attribution pour avoir la date d'attribution
                $attribution = UserJetonEsengoHistory::where('jeton_id', $jeton->id)
                    ->where('action_type', UserJetonEsengoHistory::ACTION_ATTRIBUTION)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                // Récupérer l'entrée d'expiration si elle existe
                $expiration = UserJetonEsengoHistory::where('jeton_id', $jeton->id)
                    ->where('action_type', UserJetonEsengoHistory::ACTION_EXPIRATION)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                return [
                    'id' => $jeton->id,
                    'code_unique' => $jeton->code_unique,
                    'created_at' => $jeton->created_at->toDateTimeString(),
                    'date_attribution' => $attribution ? $attribution->created_at->toDateTimeString() : $jeton->created_at->toDateTimeString(),
                    'date_expiration' => $jeton->date_expiration->toDateTimeString(),
                    'date_expiration_detection' => $expiration ? $expiration->created_at->toDateTimeString() : null,
                    'pack_id' => $jeton->pack_id,
                    'pack_name' => $jeton->pack ? $jeton->pack->name : 'Pack inconnu',
                    'metadata' => $jeton->metadata
                ];
            })
            ->toArray();
        
        return response()->json([
            'success' => true,
            'jetons_expires' => $jetonsExpires,
            'total' => count($jetonsExpires)
        ]);
    }
    
    /**
     * Récupère les jetons Esengo utilisés de l'utilisateur connecté
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsedJetons()
    {
        $user = Auth::user();
        
        // Récupérer tous les jetons Esengo utilisés de l'utilisateur
        $jetonsUtilises = UserJetonEsengo::where('user_id', $user->id)
            ->where('is_used', true)
            ->with('pack') // Charger la relation pack pour avoir accès au nom du pack
            ->get()
            ->map(function($jeton) {
                // Récupérer l'historique d'attribution pour avoir la date d'attribution
                $attribution = UserJetonEsengoHistory::where('jeton_id', $jeton->id)
                    ->where('action_type', UserJetonEsengoHistory::ACTION_ATTRIBUTION)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                // Récupérer l'entrée d'utilisation
                $utilisation = UserJetonEsengoHistory::where('jeton_id', $jeton->id)
                    ->where('action_type', UserJetonEsengoHistory::ACTION_UTILISATION)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                return [
                    'id' => $jeton->id,
                    'code_unique' => $jeton->code_unique,
                    'created_at' => $jeton->created_at->toDateTimeString(),
                    'date_attribution' => $attribution ? $attribution->created_at->toDateTimeString() : $jeton->created_at->toDateTimeString(),
                    'date_utilisation' => $jeton->date_utilisation ? $jeton->date_utilisation->toDateTimeString() : null,
                    'pack_id' => $jeton->pack_id,
                    'pack_name' => $jeton->pack ? $jeton->pack->name : 'Pack inconnu',
                    'metadata' => $jeton->metadata,
                    'ticket_id' => $jeton->ticket_id
                ];
            })
            ->toArray();
        
        return response()->json([
            'success' => true,
            'jetons_utilises' => $jetonsUtilises,
            'total' => count($jetonsUtilises)
        ]);
    }

    /**
     * Récupère les cadeaux disponibles pour un pack spécifique
     *
     * @param int $packId ID du pack
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCadeauxByPack($packId)
    {
        try {
            // Vérifier que le pack existe
            $pack = \App\Models\Pack::find($packId);
            
            if (!$pack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack non trouvé'
                ], 404);
            }
            
            // Récupérer les cadeaux associés à ce pack
            $cadeaux = \App\Models\Cadeau::where('pack_id', $packId)
                ->where('actif', true)
                ->where('stock', '>', 0)
                ->orderBy('probabilite', 'desc')
                ->get()
                ->map(function($cadeau) {
                    return [
                        'id' => $cadeau->id,
                        'nom' => $cadeau->nom,
                        'description' => $cadeau->description,
                        'image_url' => $cadeau->image_url,
                        'valeur' => $cadeau->valeur,
                        'probabilite' => $cadeau->probabilite
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $cadeaux,
                'total' => $cadeaux->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des cadeaux',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupère les informations d'un ticket gagnant à partir de son code de vérification
     * Cette méthode est réservée aux administrateurs pour vérifier un ticket
     *
     * @param string $code_verification Code de vérification du ticket à vérifier
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifierTicket($code_verification)
    {
        try {
            // Recherche du ticket par son code de vérification
            $ticket = TicketGagnant::where('code_verification', $code_verification)
                ->with(['user', 'cadeau'])
                ->first();
            
            // Vérifier si le ticket existe
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket non trouvé'
                ], 404);
            }
            
            // Récupérer les informations du ticket pour l'affichage
            $ticketData = [
                'id' => $ticket->id,
                'code_verification' => $ticket->code_verification,
                'code_jeton' => $ticket->code_jeton,
                'date_creation' => $ticket->created_at->format('Y-m-d H:i:s'),
                'date_expiration' => $ticket->date_expiration ? $ticket->date_expiration->format('Y-m-d H:i:s') : null,
                'consomme' => $ticket->consomme,
                'date_consommation' => $ticket->date_consommation ? $ticket->date_consommation->format('Y-m-d H:i:s') : null,
                'user' => [
                    'id' => $ticket->user->id,
                    'name' => $ticket->user->name,
                    'email' => $ticket->user->email,
                    'phone' => $ticket->user->phone
                ],
                'cadeau' => [
                    'id' => $ticket->cadeau->id,
                    'nom' => $ticket->cadeau->nom,
                    'description' => $ticket->cadeau->description,
                    'image_url' => $ticket->cadeau->image_url,
                    'valeur' => $ticket->cadeau->valeur
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $ticketData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la vérification du ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consomme un ticket gagnant sans demander à nouveau le code de vérification
     * Cette méthode est réservée aux administrateurs
     *
     * @param int $id ID du ticket à consommer
     * @return \Illuminate\Http\JsonResponse
     */
    public function consommerTicket($id)
    {
        try {
            // Recherche du ticket par son ID
            $ticket = TicketGagnant::where('id', $id)
                ->with(['user', 'cadeau'])
                ->first();
            
            // Vérifier si le ticket existe
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket non trouvé'
                ], 404);
            }
            
            // Si le ticket est déjà consommé, retourner une erreur
            if ($ticket->consomme) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce ticket a déjà été utilisé'
                ]);
            }
            
            // Si le ticket est expiré, retourner une erreur
            if ($ticket->estExpire()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce ticket est expiré'
                ]);
            }
            
            // À ce stade, le ticket est valide
            // On peut marquer le ticket comme consommé
            DB::beginTransaction();
            
            try {
                // Marquer le ticket comme consommé
                $ticket->marquerCommeConsomme();
                
                DB::commit();
                
                // Retourner le succès
                return response()->json([
                    'success' => true,
                    'message' => 'Ticket validé avec succès'
                ]);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la consommation du ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
