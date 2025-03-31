<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Récupère toutes les notifications de l'utilisateur connecté
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'meta' => [
                'total' => $user->notifications()->count(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * Récupère uniquement les notifications non lues de l'utilisateur connecté
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function unread()
    {
        $user = Auth::user();
        $notifications = $user->unreadNotifications()->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'meta' => [
                'total' => $user->notifications()->count(),
                'unread_count' => $notifications->count(),
            ],
        ]);
    }

    /**
     * Marque une notification spécifique comme lue
     *
     * @param string $id Identifiant de la notification
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = DatabaseNotification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification non trouvée',
            ], 404);
        }

        // Vérifier que la notification appartient bien à l'utilisateur
        if ($notification->notifiable_id != $user->id || $notification->notifiable_type != get_class($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à accéder à cette notification',
            ], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Marque toutes les notifications de l'utilisateur comme lues
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues',
            'unread_count' => 0,
        ]);
    }

    /**
     * Supprime une notification spécifique
     *
     * @param string $id Identifiant de la notification
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        $user = Auth::user();
        $notification = DatabaseNotification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification non trouvée',
            ], 404);
        }

        // Vérifier que la notification appartient bien à l'utilisateur
        if ($notification->notifiable_id != $user->id || $notification->notifiable_type != get_class($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à accéder à cette notification',
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification supprimée',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Supprime toutes les notifications de l'utilisateur
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAll()
    {
        $user = Auth::user();
        $user->notifications()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été supprimées',
        ]);
    }
}