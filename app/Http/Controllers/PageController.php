<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\User;
use App\Models\PageAbonnes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PageController extends Controller
{
    /**
     * Récupérer la page de l'utilisateur connecté avec toutes ses publications
     *
     * @return \Illuminate\Http\Response
     */
    public function getMyPage()
    {
        $user = Auth::user();
        $page = $user->page;

        if (!$page) {
            // Créer une page pour l'utilisateur si elle n'existe pas
            $page = Page::create([
                'user_id' => $user->id,
                'nombre_abonnes' => 0,
                'nombre_likes' => 0
            ]);
        }

        // Charger les publications
        $page->load([
            'publicites', 
            'offresEmploi', 
            'opportunitesAffaires',
            'user'
        ]);
        
        // Ajouter l'URL complète du fichier PDF pour chaque offre d'emploi
        if ($page->offresEmploi) {
            foreach ($page->offresEmploi as $offre) {
                if ($offre->offer_file) {
                    $offre->offer_file_url = asset('storage/' . $offre->offer_file);
                }
            }
        }

        return response()->json([
            'success' => true,
            'page' => $page
        ]);
    }

    /**
     * Récupérer les détails d'une page spécifique
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getPage($id)
    {
        $page = Page::with([
            'publicites' => function($query) {
                $query->where('statut', 'approuvé')
                      ->where('etat', 'disponible');
            }, 
            'offresEmploi' => function($query) {
                $query->where('statut', 'approuvé')
                      ->where('etat', 'disponible');
            }, 
            'opportunitesAffaires' => function($query) {
                $query->where('statut', 'approuvé')
                      ->where('etat', 'disponible');
            },
            'user'
        ])->findOrFail($id);

        \Log::info($page);

        return response()->json([
            'success' => true,
            'page' => $page
        ]);
    }

    /**
     * S'abonner à une page
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $pageId
     * @return \Illuminate\Http\Response
     */
    public function subscribe($pageId)
    {
        $user = Auth::user();
        $page = Page::findOrFail($pageId);

        // Vérifier si l'utilisateur est déjà abonné
        $existingSubscription = PageAbonnes::where('page_id', $pageId)
            ->where('user_id', $user->id)
            ->first();

        if ($existingSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'Vous êtes déjà abonné à cette page.'
            ], 400);
        }

        // Créer l'abonnement
        PageAbonnes::create([
            'page_id' => $pageId,
            'user_id' => $user->id
        ]);

        // Mettre à jour le nombre d'abonnés
        $page->nombre_abonnes = $page->nombre_abonnes + 1;
        $page->save();

        return response()->json([
            'success' => true,
            'message' => 'Vous êtes maintenant abonné à cette page.'
        ]);
    }

    /**
     * Se désabonner d'une page
     *
     * @param  int  $pageId
     * @return \Illuminate\Http\Response
     */
    public function unsubscribe($pageId)
    {
        $user = Auth::user();
        $page = Page::findOrFail($pageId);

        // Supprimer l'abonnement
        $deleted = PageAbonnes::where('page_id', $pageId)
            ->where('user_id', $user->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas abonné à cette page.'
            ], 400);
        }

        // Mettre à jour le nombre d'abonnés
        $page->nombre_abonnes = max(0, $page->nombre_abonnes - 1);
        $page->save();

        return response()->json([
            'success' => true,
            'message' => 'Vous êtes maintenant désabonné de cette page.'
        ]);
    }

    /**
     * Aimer une page
     *
     * @param  int  $pageId
     * @return \Illuminate\Http\Response
     */
    public function likePage($pageId)
    {
        $page = Page::findOrFail($pageId);
        $page->nombre_likes = $page->nombre_likes + 1;
        $page->save();

        return response()->json([
            'success' => true,
            'message' => 'Vous avez aimé cette page.'
        ]);
    }

    /**
     * Récupérer les abonnés d'une page
     *
     * @param  int  $pageId
     * @return \Illuminate\Http\Response
     */
    public function getPageStats($pageId)
    {
        $page = Page::findOrFail($pageId);
        $subscribers = $page->abonnes()->with('user')->get();

        return response()->json([
            'success' => true,
            'subscribers' => $subscribers,
            'page_subscribers' => $page->nombre_abonnes,
            'page_likes' => $page->nombre_likes,
        ]);
    }
}
