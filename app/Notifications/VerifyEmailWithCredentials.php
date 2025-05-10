<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use App\Models\Pack;

class VerifyEmailWithCredentials extends VerifyEmailFrench implements ShouldQueue
{
    use Queueable;

    protected $userData;
    protected $packId;
    protected $durationMonths;
    protected $password;
    protected $referralCode;
    protected $referralLink;

    public function __construct($packId, $durationMonths, $password, $referralCode, $referralLink)
    {
        $this->packId = $packId;
        $this->durationMonths = $durationMonths;
        $this->password = $password;
        $this->referralCode = $referralCode;
        $this->referralLink = $referralLink;
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

    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $pack = Pack::find($this->packId);

        return (new MailMessage)
            ->subject('Confirmation de votre adresse e-mail')
            ->greeting('Bonjour !')
            ->line('Félicitations, bienvenue dans la famille SOLIFIN')
            ->line('Voici vos coordonnées de connexion :')
            ->line('- ID de votre compte: ' . $notifiable->account_id)
            ->line('- Mot de passe: ' . $this->password)
            ->line('- Pack de souscription: ' . $pack->name)
            ->line('- Email: ' . $notifiable->email)
            ->line('- Période de validité du pack: ' . $this->durationMonths . ' mois')
            ->line('- Votre code de parrainage pour ce pack: ' . $this->referralCode)
            ->line('- Votre lien de parrainage: '. $this->referralLink)
            ->line('Veuillez avant de vous connecter, cliquer sur ce lien ci-dessous pour vérifier votre adresse email.')
            ->action('Vérifier l\'adresse e-mail', $verificationUrl)
            ->line('Si vous n\'avez pas créé de compte, aucune action supplémentaire n\'est requise.')
            ->salutation('Cordialement,<br>L\'équipe SOLIFIN');
    }
}
