<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalRequestPaid extends Notification implements ShouldQueue
{
    use Queueable;

    private $withdrawalRequest;

    public function __construct(WithdrawalRequest $withdrawalRequest)
    {
        $this->withdrawalRequest = $withdrawalRequest;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Votre retrait a été effectué')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre retrait d'un montant de {$this->withdrawalRequest->amount}€ a été effectué avec succès.")
            ->line("Le paiement a été envoyé via {$this->withdrawalRequest->payment_method}.")
            ->line("Merci de votre confiance !");
    }

    public function toArray($notifiable)
    {
        return [
            'withdrawal_request_id' => $this->withdrawalRequest->id,
            'amount' => $this->withdrawalRequest->amount,
            'payment_method' => $this->withdrawalRequest->payment_method,
            'paid_at' => $this->withdrawalRequest->paid_at,
        ];
    }
}
