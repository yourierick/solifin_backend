<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\ReferralInvitation;
use App\Models\UserPack;
use Illuminate\Support\Facades\URL;

// Log pour vérifier que le fichier est bien chargé
\Log::info('Fichier ReferralInvitationNotification.php chargé');

// Mettre la notification dans la file d'attente
class ReferralInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;
    
    // Ajouter un log pour indiquer que la classe a été modifiée
    // et qu'elle n'est plus mise en file d'attente
    public static $logMessage = 'Notification modifiée pour être traitée immédiatement';

    /**
     * L'invitation de parrainage
     *
     * @var ReferralInvitation
     */
    protected $invitation;

    /**
     * Create a new notification instance.
     */
    public function __construct(ReferralInvitation $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {   
        // Récupérer le user_pack associé à l'invitation
        $userPack = UserPack::find($this->invitation->user_pack_id);
        if (!$userPack) {
            throw new \Exception('UserPack introuvable pour l\'invitation ' . $this->invitation->id);
        }
        
        $pack = $userPack->pack;
        $user = $this->invitation->user;
        
        // Générer l'URL de l'invitation avec le code et le code de parrainage
        // Forcer l'utilisation de l'URL du frontend au lieu du backend
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173'); // URL du frontend en dur pour éviter les problèmes de configuration
        
        // Vérifier et s'assurer que le code de parrainage existe
        $referralCode = $userPack->referral_code;
        if (empty($referralCode)) {
            $referralCode = 'CODE_MANQUANT';
        }
        
        // Construire l'URL avec les deux paramètres et s'assurer qu'ils sont correctement encodés
        $url = $frontendUrl . '/register?invitation=' . urlencode($this->invitation->invitation_code) . '&referral_code=' . urlencode($referralCode);
        
        // Utiliser le système de template Markdown de Laravel qui gère correctement le HTML
        $mailMessage = (new MailMessage)
            ->subject('Invitation à rejoindre SOLIFIN - ' . $user->name . ' vous parraine')
            ->greeting('Bonjour ' . ($this->invitation->name ?: '') . ',')
            ->line($user->name . ' vous invite à rejoindre SOLIFIN avec le pack **' . $pack->name . '**.')
            ->line('En rejoignant SOLIFIN via cette invitation, vous bénéficierez des avantages suivants :');
        
        // Créer une liste à puces avec Markdown
        $advantages = "* Accès à toutes les fonctionnalités du pack **{$pack->name}**\n";
        
        // Ajouter les avantages du pack s'ils existent
        if (!empty($pack->avantages)) {
            foreach ($pack->avantages as $avantage) {
                $advantages .= "* {$avantage}\n";
            }
        }
        
        $mailMessage->line($advantages);
        
        // Utiliser action() mais avec l'URL complète que nous avons générée
        // Cela permet d'avoir un bouton bien formaté dans l'email
        $mailMessage->action('Rejoindre SOLIFIN', $url)
            ->line('Cette invitation est valable jusqu\'au **' . $this->invitation->expires_at->format('d/m/Y') . '**.')
            ->line('Merci de votre intérêt pour SOLIFIN!')
            ->salutation('Cordialement,  \nL\'équipe SOLIFIN');
        
        // Ajouter une note de bas de page pour l'URL complète
        $mailMessage->line('Si le bouton ne fonctionne pas, copiez et collez ce lien : ' . $url);
            
        return $mailMessage;
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
            'user_id' => $this->invitation->user_id,
            'user_pack_id' => $this->invitation->user_pack_id,
            'email' => $this->invitation->email,
            'status' => $this->invitation->status,
            'expires_at' => $this->invitation->expires_at->toIso8601String(),
        ];
    }
}
