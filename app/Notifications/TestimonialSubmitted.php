<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestimonialSubmitted extends Notification
{
    use Queueable;

    /**
     * Les données du témoignage soumis.
     *
     * @var array
     */
    protected $data;

    /**
     * Create a new notification instance.
     *
     * @param array $data Les données du témoignage
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
                    ->line('Un nouveau témoignage a été soumis par ' . $this->data['user_name'])
                    ->line('Note: ' . $this->data['rating'] . '/5')
                    ->action('Voir le témoignage', url('/admin/testimonials'))
                    ->line('Merci d\'utiliser SOLIFIN!');
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
            'type' => 'testimonial',
            'titre' => $this->data['titre'],
            'message' => 'en attente de traitement',
            'id' => $this->data['id'],
            'rating' => $this->data['rating'],
            'user_id' => $this->data['user_id'],
            'user_name' => $this->data['user_name'],
            'link' => '/admin/testimonials'
        ];
    }
}
