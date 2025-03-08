<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserPack;
use App\Models\Pack;
use App\Models\Commission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackController extends Controller
{
    public function getUserPacks(Request $request)
    {
        try {
            $userPacks = UserPack::with(['pack', 'sponsor'])
                ->where('user_id', $request->user()->id)
                ->get()
                ->map(function ($userPack) {
                    $data = $userPack->toArray();
                    if ($userPack->sponsor) {
                        $data['sponsor_info'] = [
                            'name' => $userPack->sponsor->name,
                            'email' => $userPack->sponsor->email,
                            'phone' => $userPack->sponsor->phone,
                        ];
                    }
                    return $data;
                });

            return response()->json([
                'success' => true,
                'data' => $userPacks
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des packs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des packs'
            ], 500);
        }
    }

    public function renewPack(Request $request, Pack $pack)
    {
        try {
            // Vérifier si l'utilisateur a déjà ce pack
            $userPack = UserPack::where('user_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack non trouvé'
                ], 404);
            }

            // Logique de renouvellement à implémenter
            // Pour l'instant, on met juste à jour la date d'expiration
            $userPack->expiry_date = now()->addMonths($request->duration_months ?? 1);
            $userPack->status = 'active';
            $userPack->save();

            return response()->json([
                'success' => true,
                'message' => 'Pack renouvelé avec succès',
                'data' => $userPack
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du renouvellement du pack'
            ], 500);
        }
    }

    public function downloadPack(Pack $pack, Request $request)
    {
        try {
            
            // Vérifier si l'utilisateur a accès à ce pack
            $userPack = UserPack::where('user_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                \Log::warning('Accès refusé au pack ' . $pack->id . ' pour l\'utilisateur ' . $request->user()->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à ce pack'
                ], 403);
            }
            
            // Vérifier si le pack a un fichier associé
            if (!$pack->formations) {
                \Log::warning('Aucun fichier associé au pack ' . $pack->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier du pack n\'est pas disponible'
                ], 404);
            }

            if (!Storage::disk('public')->exists($pack->formations)) {
                \Log::warning('Fichier non trouvé: ' . $pack->formations);
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier du pack n\'est pas disponible'
                ], 404);
            }

            return Storage::disk('public')->download($pack->formations, "pack-{$pack->id}.zip");

        } catch (\Exception $e) {
            \Log::error('Erreur lors du téléchargement du pack: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement du pack'
            ], 500);
        }
    }

    public function getPackStats(Request $request, Pack $pack)
    {
        try {
            $userPack = UserPack::where('user_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack non trouvé'
                ], 404);
            }

            // Calculer les commissions totales
            $totalCommission = UserPack::where('sponsor_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->sum('commission');

            // Calculer les commissions du mois en cours
            $monthlyCommission = UserPack::where('sponsor_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('commission');

            // Compter le nombre de filleuls
            $referralCount = UserPack::where('sponsor_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_commission' => number_format($totalCommission, 2),
                    'monthly_commission' => number_format($monthlyCommission, 2),
                    'referral_count' => $referralCount
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des statistiques: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    public function getPackReferrals(Request $request, Pack $pack)
    {
        try {
            $userPack = UserPack::where('user_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->first();

            if (!$userPack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pack non trouvé'
                ], 404);
            }

            $allGenerations = [];
            
            // Première génération (niveau 1)
            $level1Referrals = UserPack::with(['user', 'sponsor', 'pack'])
                ->where('sponsor_id', $request->user()->id)
                ->where('pack_id', $pack->id)
                ->get()
                ->map(function ($referral) use ($request, $pack) {
                    $commissions = Commission::where('user_id', $request->user()->id)->where('source_user_id', $referral->user_id)->where('pack_id', $pack->id)->where('status', "completed")->sum('amount');
                    return [
                        'id' => $referral->user->id ?? null,
                        'name' => $referral->user->name ?? 'N/A',
                        'purchase_date' => optional($referral->purchase_date)->format('d/m/Y'),
                        'pack_status' => $referral->status ?? 'inactive',
                        'total_commission' => $commissions ?? 0,
                        'sponsor_id' => $referral->sponsor_id,
                        'referral_code' => $referral->referral_code ?? 'N/A',
                        'pack_name' => $referral->referral_pack_name ?? 'N/A',
                        'pack_price' => $referral->pack->price ?? 0,
                        'expiry_date' => optional($referral->expiry_date)->format('d/m/Y')
                    ];
                });
            $allGenerations[] = $level1Referrals;

            // Générations 2 à 4
            for ($level = 2; $level <= 4; $level++) {
                $currentGeneration = collect();
                $previousGeneration = $allGenerations[$level - 2];

                foreach ($previousGeneration as $parent) {
                    $children = UserPack::with(['user', 'sponsor', 'pack'])
                        ->where('sponsor_id', $parent['id'])
                        ->where('pack_id', $pack->id)
                        ->get()
                        ->map(function ($referral) use ($parent, $request, $pack) {
                            //calcul du total de commission générée par ce filleul pour cet utilisateur.
                            $commissions = Commission::where('user_id', $request->user()->id)->where('source_user_id', $referral->user_id)->where('pack_id', $pack->id)->where('status', "completed")->sum('amount');
                            return [
                                'id' => $referral->user->id ?? null,
                                'name' => $referral->user->name ?? 'N/A',
                                'purchase_date' => optional($referral->purchase_date)->format('d/m/Y'),
                                'pack_status' => $referral->status ?? 'inactive',
                                'total_commission' => $commissions ?? "0 $",
                                'sponsor_id' => $referral->sponsor_id,
                                'sponsor_name' => $parent['name'] ?? 'N/A',
                                'referral_code' => $referral->referral_code ?? 'N/A',
                                'pack_name' => $referral->pack->name ?? 'N/A',
                                'pack_price' => $referral->pack->price ?? 0,
                                'expiry_date' => optional($referral->expiry_date)->format('d/m/Y')
                            ];
                        });
                    $currentGeneration = $currentGeneration->concat($children);
                }
                $allGenerations[] = $currentGeneration;
            }

            return response()->json([
                'success' => true,
                'data' => $allGenerations
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des filleuls: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des filleuls'
            ], 500);
        }
    }
} 