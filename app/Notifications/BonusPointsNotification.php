<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use App\Models\BonusRates;

class BonusPointsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $title;
    protected $message;
    protected $points;
    protected $type;

    /**
     * Create a new notification instance.
     *
     * @param string $title Titre de la notification
     * @param string $message Message de la notification
     * @param int $points Nombre de points/jetons attribués
     * @param string $type Type de bonus (delais ou esengo)
     * @return void
     */
    public function __construct($title, $message, $points, $type)
    {
        $this->title = $title;
        $this->message = $message;
        $this->points = $points;
        $this->type = $type;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'points' => $this->points,
            'type' => $this->type,
            'icon' => $this->type === BonusRates::TYPE_DELAIS ? 'clock' : 'ticket',
            'color' => $this->type === BonusRates::TYPE_DELAIS ? 'success' : 'primary',
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title' => $this->title,
            'message' => $this->message,
            'points' => $this->points,
            'type' => $this->type,
            'icon' => $this->type === BonusRates::TYPE_DELAIS ? 'clock' : 'ticket',
            'color' => $this->type === BonusRates::TYPE_DELAIS ? 'success' : 'primary',
            'time' => now()->toIso8601String(),
        ]);
    }
}
