<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class PublicationSubmitted extends Notification
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     *
     * @param array $data
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
        return (new MailMessage)
            ->subject(Lang::get('Nouvelle publication en attente d\'approbation'))
            ->line(Lang::get('Une nouvelle publication a été soumise et est en attente d\'approbation.'))
            ->line(Lang::get('Titre: :titre', ['titre' => $this->data['titre']]))
            ->line(Lang::get('Soumise par: :user', ['user' => $this->data['user_name']]))
            ->action(Lang::get('Voir la publication'), url('/admin/validation-publications'));
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
            'type' => $this->data['type'],
            'id' => $this->data['id'],
            'titre' => $this->data['titre'],
            'user_id' => $this->data['user_id'],
            'user_name' => $this->data['user_name'],
            'url' => '/admin/validations'
        ];
    }
}
