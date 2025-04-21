<?php

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WithdrawalRequestCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $withdrawalRequest;

    public function __construct(WithdrawalRequest $withdrawalRequest)
    {
        $this->withdrawalRequest = $withdrawalRequest;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'Titre' => 'Demande de retrait',
            'message' => 'Nouvelle demande de retrait de ' . number_format($this->withdrawalRequest->amount, 2) . ' $',
            'withdrawal_request_id' => $this->withdrawalRequest->id,
            'link' => '/admin/withdrawal-requests',
            'user_name' => $this->withdrawalRequest->user->name,
            'amount' => $this->withdrawalRequest->amount,
            'created_at' => $this->withdrawalRequest->created_at->format('d/m/Y H:i'),
        ];
    }
} 