<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Code OTP pour votre demande de retrait')
            ->greeting('Bonjour ' . $notifiable->name)
            ->line('Vous avez demandé un retrait sur votre compte SOLIFIN.')
            ->line('Voici votre code OTP : ' . $this->otp)
            ->line('Ce code est valable pendant 10 minutes.')
            ->line('Si vous n\'avez pas demandé ce retrait, veuillez ignorer cet email.')
            ->salutation('L\'équipe SOLIFIN');
    }
}
