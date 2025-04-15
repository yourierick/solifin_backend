<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PublicationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     *
     * @param  array  $data
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
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

        if ($this->data['statut'] === 'approuve') {
            $message->line('Bonne nouvelle ! Votre publication a été approuvée.')
                ->line('Titre: ' . $this->data['titre']);
        } elseif ($this->data['statut'] === 'rejete') {
            $message->line('Votre publication a été rejetée.')
                ->line('Titre: ' . $this->data['titre'])
                ->line(isset($this->data['raison']) ? 'Raison: ' . $this->data['raison'] : 'Veuillez contacter notre équipe pour plus d\'informations.');
        } elseif ($this->data['statut'] === 'en_attente') {
            $message->line('Votre publication a été mise en attente.')
                ->line('Titre: ' . $this->data['titre'])
                ->line('Elle sera examinée par notre équipe prochainement.');
        }

        $url = '/dashboard';
        switch ($this->data['type']) {
            case 'publicites':
                $url = '/publicites/' . $this->data['id'];
                break;
            case 'offres_emploi':
                $url = '/offres-emploi/' . $this->data['id'];
                break;
            case 'opportunites_affaires':
                $url = '/opportunites-affaires/' . $this->data['id'];
                break;
        }

        return $message->action('Voir ma publication', url($url))
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
        $message = isset($this->data['message']) ? $this->data['message'] : '';
        $link = '/dashboard/my-page';

        // Déterminer le type de notification en fonction du statut
        if ($this->data['statut'] === 'approuve') {
            $type = 'success';
            if (empty($message)) {
                $message = 'Votre publication "' . $this->data['titre'] . '" a été approuvée et est maintenant visible.';
            }
        } elseif ($this->data['statut'] === 'rejete') {
            $type = 'danger';
            if (empty($message)) {
                $message = 'Votre publication "' . $this->data['titre'] . '" a été rejetée.';
                if (isset($this->data['raison'])) {
                    $message .= ' Raison: ' . $this->data['raison'];
                }
            }
        } elseif ($this->data['statut'] === 'en_attente') {
            $type = 'warning';
            if (empty($message)) {
                $message = 'Votre publication "' . $this->data['titre'] . '" a été mise en attente et sera examinée prochainement.';  
            }
        }

        return [
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'publication_id' => $this->data['id'],
            'publication_type' => $this->data['type'],
            'statut' => $this->data['statut'],
            'titre' => $this->data['titre']
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
