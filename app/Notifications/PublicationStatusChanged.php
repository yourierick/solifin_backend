<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PublicationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    protected $publication;
    protected $publicationType;
    protected $oldStatus;
    protected $newStatus;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($publication, $publicationType, $oldStatus, $newStatus)
    {
        $this->publication = $publication;
        $this->publicationType = $publicationType;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $message = (new MailMessage)
            ->subject('Statut de votre publication modifié');

        if ($this->newStatus === 'approuvé') {
            $message->line('Bonne nouvelle ! Votre publication a été approuvée.')
                ->line('Titre: ' . $this->publication->titre);
        } elseif ($this->newStatus === 'rejeté') {
            $message->line('Votre publication a été rejetée.')
                ->line('Titre: ' . $this->publication->titre)
                ->line('Veuillez contacter notre équipe pour plus d\'informations.');
        } elseif ($this->newStatus === 'expiré') {
            $message->line('Votre publication a expiré.')
                ->line('Titre: ' . $this->publication->titre)
                ->line('Elle n\'est plus visible pour les autres utilisateurs.');
        }

        return $message->action('Voir mes publications', url('/publications'))
            ->line('Merci d\'utiliser notre application !');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        $type = 'info';
        $message = '';
        $link = '/dashboard';

        switch ($this->publicationType) {
            case 'publicite':
                $link = '/publicites/' . $this->publication->id;
                break;
            case 'offre_emploi':
                $link = '/offres-emploi/' . $this->publication->id;
                break;
            case 'opportunite_affaire':
                $link = '/opportunites-affaires/' . $this->publication->id;
                break;
        }

        if ($this->newStatus === 'approuvé') {
            $type = 'success';
            $message = 'Votre ' . $this->getPublicationTypeName() . ' "' . $this->publication->titre . '" a été approuvée et est maintenant visible.';
        } elseif ($this->newStatus === 'rejeté') {
            $type = 'danger';
            $message = 'Votre ' . $this->getPublicationTypeName() . ' "' . $this->publication->titre . '" a été rejetée. Veuillez la réviser.';
        } elseif ($this->newStatus === 'expiré') {
            $type = 'warning';
            $message = 'Votre ' . $this->getPublicationTypeName() . ' "' . $this->publication->titre . '" a expiré et n\'est plus visible.';
        }

        return [
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'user_name' => 'SOLIFIN',
            'publication_id' => $this->publication->id,
            'publication_type' => $this->publicationType,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }

    /**
     * Get the human-readable name for the publication type
     * 
     * @return string
     */
    private function getPublicationTypeName()
    {
        switch ($this->publicationType) {
            case 'publicite':
                return 'publicité';
            case 'offre_emploi':
                return 'offre d\'emploi';
            case 'opportunite_affaire':
                return 'opportunité d\'affaire';
            default:
                return 'publication';
        }
    }
}
