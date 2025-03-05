<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalRequestProcessed extends Notification implements ShouldQueue
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
        $status = $this->withdrawalRequest->status === 'approved' 
            ? 'approuvée' 
            : 'rejetée';

        return (new MailMessage)
            ->subject("Votre demande de retrait a été {$status}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre demande de retrait d'un montant de {$this->withdrawalRequest->amount}€ a été {$status}.")
            ->when($this->withdrawalRequest->status === 'approved', function ($message) {
                return $message->line("Le paiement sera traité automatiquement.")
                    ->line("Vous recevrez une confirmation une fois le paiement effectué.");
            })
            ->when($this->withdrawalRequest->status === 'rejected', function ($message) {
                return $message->line("Raison : {$this->withdrawalRequest->admin_note}");
            })
            ->line("Merci de votre confiance !");
    }

    public function toArray($notifiable)
    {
        return [
            'withdrawal_request_id' => $this->withdrawalRequest->id,
            'amount' => $this->withdrawalRequest->amount,
            'status' => $this->withdrawalRequest->status,
            'note' => $this->withdrawalRequest->admin_note,
        ];
    }
}