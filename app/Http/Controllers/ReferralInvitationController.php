<?php

namespace App\Http\Controllers;

use App\Models\ReferralInvitation;
use App\Models\User;
use App\Models\UserPack;
use App\Notifications\ReferralInvitationNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReferralInvitationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 10);
        $status = $request->input('status');
        $packId = $request->input('pack_id');
        
        $query = ReferralInvitation::with(['userPack.pack'])
            ->where('user_id', $user->id);
            
        if ($status) {
            $query->where('status', $status);
        }
        
        if ($packId) {
            $query->whereHas('userPack', function($q) use ($packId) {
                $q->where('pack_id', $packId);
            });
        }
        
        $invitations = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json([
            'success' => true,
            'data' => $invitations
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_pack_id' => 'required|exists:user_packs,id',
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'channel' => 'nullable|string|in:email,sms,whatsapp',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $userPack = UserPack::find($request->user_pack_id);
        
        // Vérifier que le pack appartient bien à l'utilisateur
        if ($userPack->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Ce pack ne vous appartient pas'
            ], 403);
        }
        
        // Vérifier si une invitation a déjà été envoyée à cet email pour ce pack
        $existingInvitation = ReferralInvitation::where('user_id', $user->id)
            ->where('user_pack_id', $userPack->id)
            ->where('email', $request->email)
            ->whereIn('status', ['pending', 'sent', 'opened'])
            ->first();
            
        if ($existingInvitation) {
            return response()->json([
                'success' => false,
                'message' => 'Une invitation a déjà été envoyée à cet email pour ce pack',
                'data' => $existingInvitation
            ], 409);
        }
        
        // Créer l'invitation
        $invitation = new ReferralInvitation([
            'user_id' => $user->id,
            'user_pack_id' => $userPack->id,
            'email' => $request->email,
            'name' => $request->name,
            'invitation_code' => ReferralInvitation::generateInvitationCode(),
            'channel' => $request->channel ?? 'email',
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(30) // L'invitation expire après 30 jours
        ]);
        
        $invitation->save();
        
        // Envoyer l'invitation par email
        if ($invitation->channel === 'email') {
            try {
                // Vérifier que le user_pack existe et a un code de parrainage
                $userPack = UserPack::find($invitation->user_pack_id);
                if (!$userPack) {
                    throw new \Exception('UserPack introuvable pour l\'invitation ' . $invitation->id);
                }
                
                if (empty($userPack->referral_code)) {
                    \Log::warning('Code de parrainage manquant pour le UserPack ' . $userPack->id . ' de l\'invitation ' . $invitation->id);
                    // Générer un code de parrainage temporaire si nécessaire
                    // ou mettre à jour le UserPack avec un code valide
                }
                
                \Log::info('ReferralInvitationController::store - Préparation de l\'envoi de la notification');
                \Log::info('ReferralInvitationController::store - Invitation ID: ' . $invitation->id);
                \Log::info('ReferralInvitationController::store - Email destinataire: ' . $invitation->email);
                
                // Envoyer la notification directement à l'adresse email spécifiée dans la requête
                // plutôt qu'à l'utilisateur qui a créé l'invitation
                try {
                    \Log::info('ReferralInvitationController::store - Tentative d\'envoi de la notification');
                    
                    // Générer l'URL d'invitation directement ici pour vérification
                    $frontendUrl = 'http://localhost:5173';
                    $userPack = UserPack::find($invitation->user_pack_id);
                    $referralCode = $userPack ? $userPack->referral_code : 'CODE_MANQUANT';
                    $url = $frontendUrl . '/register?invitation=' . urlencode($invitation->invitation_code) . '&referral_code=' . urlencode($referralCode);
                    
                    // Log l'URL générée directement dans le contrôleur
                    \Log::error('=== URL GENEREE DANS LE CONTROLEUR ===');
                    \Log::error($url);
                    
                    // Envoyer la notification normalement
                    \Illuminate\Support\Facades\Notification::route('mail', [
                        $invitation->email => $invitation->name ?: $invitation->email,
                    ])->notify(new ReferralInvitationNotification($invitation));
                    
                    \Log::info('ReferralInvitationController::store - Notification envoyée avec succès');
                } catch (\Exception $e) {
                    \Log::error('ReferralInvitationController::store - Erreur lors de l\'envoi de la notification: ' . $e->getMessage());
                    \Log::error('ReferralInvitationController::store - Trace: ' . $e->getTraceAsString());
                    throw $e;
                }
                
                $invitation->status = 'sent';
                $invitation->sent_at = Carbon::now();
                $invitation->save();
                
                // Log pour déboguer l'URL générée
                $frontendUrl = 'http://localhost:5173';
                $referralCode = $userPack->referral_code ?: 'CODE_MANQUANT';
                $url = $frontendUrl . '/register?invitation=' . $invitation->invitation_code . '&referral_code=' . $referralCode;
                \Log::info('URL d\'invitation générée: ' . $url);
                
            } catch (\Exception $e) {
                \Log::error('Erreur lors de l\'envoi de l\'invitation: ' . $e->getMessage());
                // On laisse l'invitation en statut 'pending' pour pouvoir réessayer plus tard
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Invitation créée avec succès',
            'data' => $invitation
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $invitation = ReferralInvitation::with(['userPack.pack'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();
            
        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation non trouvée'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $invitation
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        $invitation = ReferralInvitation::where('user_id', $user->id)
            ->where('id', $id)
            ->first();
            
        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation non trouvée'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'channel' => 'nullable|string|in:email,sms,whatsapp',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Mettre à jour les champs modifiables
        if ($request->has('name')) {
            $invitation->name = $request->name;
        }
        
        if ($request->has('email') && $invitation->status === 'pending') {
            $invitation->email = $request->email;
        }
        
        if ($request->has('channel') && $invitation->status === 'pending') {
            $invitation->channel = $request->channel;
        }
        
        $invitation->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Invitation mise à jour avec succès',
            'data' => $invitation->load('userPack.pack')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = Auth::user();
        $invitation = ReferralInvitation::where('user_id', $user->id)
            ->where('id', $id)
            ->first();
            
        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation non trouvée'
            ], 404);
        }
        
        $invitation->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Invitation supprimée avec succès'
        ]);
    }
    
    /**
     * Renvoyer une invitation
     */
    public function resend(string $id)
    {
        $user = Auth::user();
        $invitation = ReferralInvitation::with(['userPack.pack'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();
            
        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation non trouvée'
            ], 404);
        }
        
        // Vérifier si l'invitation n'est pas déjà utilisée ou expirée
        if (in_array($invitation->status, ['registered', 'expired'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette invitation ne peut pas être renvoyée car elle est ' . 
                    ($invitation->status === 'registered' ? 'déjà utilisée' : 'expirée')
            ], 400);
        }
        
        // Réinitialiser l'invitation
        $invitation->status = 'pending';
        $invitation->expires_at = Carbon::now()->addDays(30);
        $invitation->save();
        
        // Envoyer l'invitation par email au destinataire de l'invitation
        if ($invitation->channel === 'email') {
            try {
                // Envoyer la notification directement à l'adresse email spécifiée dans l'invitation
                // plutôt qu'à l'utilisateur qui a créé l'invitation
                \Illuminate\Support\Facades\Notification::route('mail', [
                    $invitation->email => $invitation->name ?: $invitation->email,
                ])->notify(new ReferralInvitationNotification($invitation));
                
                $invitation->status = 'sent';
                $invitation->sent_at = Carbon::now();
                $invitation->save();
            } catch (\Exception $e) {
                \Log::error('Erreur lors de l\'envoi de l\'invitation: ' . $e->getMessage());
                // On laisse l'invitation en statut 'pending' pour pouvoir réessayer plus tard
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Invitation renvoyée avec succès',
            'data' => $invitation
        ]);
    }
    
    /**
     * Vérifier une invitation par son code
     */
    public function checkInvitation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invitation_code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $invitation = ReferralInvitation::with(['userPack.pack', 'user'])
            ->where('invitation_code', $request->invitation_code)
            ->first();
            
        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Code d\'invitation invalide'
            ], 404);
        }
        
        // Vérifier si l'invitation est expirée
        if ($invitation->status === 'expired' || ($invitation->expires_at && $invitation->expires_at->isPast())) {
            $invitation->status = 'expired';
            $invitation->save();
            
            return response()->json([
                'success' => false,
                'message' => 'Cette invitation a expiré'
            ], 400);
        }
        
        // Vérifier si l'invitation a déjà été utilisée
        if ($invitation->status === 'registered') {
            return response()->json([
                'success' => false,
                'message' => 'Cette invitation a déjà été utilisée'
            ], 400);
        }
        
        // Marquer l'invitation comme ouverte si ce n'est pas déjà le cas
        if ($invitation->status !== 'opened') {
            $invitation->status = 'opened';
            $invitation->opened_at = Carbon::now();
            $invitation->save();
        }
        
        // Retourner les informations sur l'invitation
        return response()->json([
            'success' => true,
            'data' => [
                'invitation' => $invitation,
                'referral_code' => $invitation->userPack->referral_code,
                'pack' => $invitation->userPack->pack,
                'sponsor' => [
                    'name' => $invitation->user->name,
                    'account_id' => $invitation->user->account_id
                ]
            ]
        ]);
    }
    
    /**
     * Récupérer les statistiques des invitations de l'utilisateur
     */
    public function statistics()
    {
        $user = Auth::user();
        
        // Statistiques globales
        $totalCount = ReferralInvitation::where('user_id', $user->id)->count();
        
        // Statistiques par statut
        $statusCounts = ReferralInvitation::where('user_id', $user->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
        
        // S'assurer que tous les statuts sont présents
        $allStatuses = ['pending', 'sent', 'opened', 'registered', 'expired'];
        foreach ($allStatuses as $status) {
            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
        }
        
        // Calculer le taux de conversion
        $conversionRate = $totalCount > 0 
            ? round(($statusCounts['registered'] / $totalCount) * 100, 2) 
            : 0;
        
        // Statistiques par pack
        $packStats = ReferralInvitation::where('referral_invitations.user_id', $user->id)
            ->join('user_packs', 'referral_invitations.user_pack_id', '=', 'user_packs.id')
            ->join('packs', 'user_packs.pack_id', '=', 'packs.id')
            ->select(
                'packs.id', 
                'packs.name', 
                DB::raw('count(*) as total'),
                DB::raw('SUM(CASE WHEN referral_invitations.status = "registered" THEN 1 ELSE 0 END) as registered')
            )
            ->groupBy('packs.id', 'packs.name')
            ->get();
        
        // Statistiques par période (7 derniers jours)
        $last7Days = ReferralInvitation::where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();
        
        // Statistiques par période (30 derniers jours)
        $last30Days = ReferralInvitation::where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalCount,
                'pending' => $statusCounts['pending'],
                'sent' => $statusCounts['sent'],
                'opened' => $statusCounts['opened'],
                'registered' => $statusCounts['registered'],
                'expired' => $statusCounts['expired'],
                'conversion_rate' => $conversionRate,
                'by_pack' => $packStats,
                'last_7_days' => $last7Days,
                'last_30_days' => $last30Days
            ]
        ]);
    }
}
