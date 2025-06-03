<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use App\Models\Cadeau;
use App\Models\TicketGagnant;

class TicketGagnantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $title;
    protected $message;
    protected $cadeau;
    protected $ticket;

    /**
     * Create a new notification instance.
     *
     * @param string $title Titre de la notification
     * @param string $message Message de la notification
     * @param Cadeau $cadeau Cadeau gagné
     * @param TicketGagnant $ticket Ticket gagnant
     * @return void
     */
    public function __construct($title, $message, Cadeau $cadeau, TicketGagnant $ticket)
    {
        $this->title = $title;
        $this->message = $message;
        $this->cadeau = $cadeau;
        $this->ticket = $ticket;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast', 'mail'];
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
            'cadeau' => [
                'id' => $this->cadeau->id,
                'nom' => $this->cadeau->nom,
                'description' => $this->cadeau->description,
                'image_url' => $this->cadeau->image_url,
                'valeur' => $this->cadeau->valeur,
            ],
            'ticket' => [
                'id' => $this->ticket->id,
                'code_verification' => $this->ticket->code_verification,
                'date_expiration' => $this->ticket->date_expiration->format('Y-m-d H:i:s'),
                'consomme' => $this->ticket->consomme,
            ],
            'icon' => 'gift',
            'color' => 'warning',
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
            'cadeau' => [
                'id' => $this->cadeau->id,
                'nom' => $this->cadeau->nom,
                'description' => $this->cadeau->description,
                'image_url' => $this->cadeau->image_url,
                'valeur' => $this->cadeau->valeur,
            ],
            'ticket' => [
                'id' => $this->ticket->id,
                'code_verification' => $this->ticket->code_verification,
                'date_expiration' => $this->ticket->date_expiration->format('Y-m-d H:i:s'),
                'consomme' => $this->ticket->consomme,
            ],
            'icon' => 'gift',
            'color' => 'warning',
            'time' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $expirationDate = $this->ticket->date_expiration->format('d/m/Y');
        
        return (new MailMessage)
            ->subject('Félicitations ! Vous avez gagné un cadeau')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line($this->title)
            ->line($this->message)
            ->line('Détails de votre cadeau :')
            ->line('Nom : ' . $this->cadeau->nom)
            ->line('Description : ' . $this->cadeau->description)
            ->line('Valeur : ' . $this->cadeau->valeur . ' €')
            ->line('Code de vérification : ' . $this->ticket->code_verification)
            ->line('Date d\'expiration : ' . $expirationDate)
            ->line('Conservez précieusement ce code pour récupérer votre cadeau !')
            ->action('Voir mes tickets', env('FRONTEND_URL') . '/dashboard/jetons-esengo')
            ->line('Merci d\'utiliser notre application !');
    }
}
