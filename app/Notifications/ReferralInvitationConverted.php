<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\ReferralInvitation;
use App\Models\User;
use Illuminate\Support\Facades\URL;

class ReferralInvitationConverted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * L'invitation de parrainage convertie
     *
     * @var ReferralInvitation
     */
    protected $invitation;

    /**
     * L'utilisateur qui s'est inscrit avec l'invitation
     *
     * @var User
     */
    protected $newUser;

    /**
     * Create a new notification instance.
     */
    public function __construct(ReferralInvitation $invitation, User $newUser)
    {
        $this->invitation = $invitation;
        $this->newUser = $newUser;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // URL du frontend
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        
        // URL vers le tableau de bord
        $dashboardUrl = $frontendUrl . '/dashboard';
        
        // URL vers la page du nouvel utilisateur
        $newUserPageUrl = $frontendUrl . '/pages/' . $this->newUser->id;
        
        return (new MailMessage)
            ->subject('Félicitations ! Votre invitation a été convertie en filleul')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Nous avons le plaisir de vous informer que votre invitation a été convertie en filleul.')
            ->line('**' . $this->newUser->name . '** s\'est inscrit(e) sur SOLIFIN en utilisant votre code d\'invitation.')
            ->line('Vous pouvez consulter votre réseau de parrainage dans votre tableau de bord.')
            ->action('Voir mon tableau de bord', $dashboardUrl)
            ->line('Merci de contribuer à la croissance de la communauté SOLIFIN !')
            ->salutation('Cordialement,  L\'équipe SOLIFIN');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'invitation_code' => $this->invitation->invitation_code,
            'new_user_id' => $this->newUser->id,
            'new_user_name' => $this->newUser->name,
            'registered_at' => $this->invitation->registered_at->toIso8601String(),
            'message' => 'Votre invitation a été convertie en filleul. ' . $this->newUser->name . ' a rejoint SOLIFIN !',
            'type' => 'referral_converted'
        ];
    }
}
