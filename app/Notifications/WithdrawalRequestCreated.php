<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WithdrawalRequestCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $paymentRequest;

    public function __construct(PaymentRequest $paymentRequest)
    {
        $this->paymentRequest = $paymentRequest;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'Nouvelle demande de retrait de ' . number_format($this->paymentRequest->amount, 2) . ' €',
            'payment_request_id' => $this->paymentRequest->id,
            'user_name' => $this->paymentRequest->user->name,
            'amount' => $this->paymentRequest->amount,
            'created_at' => $this->paymentRequest->created_at->format('d/m/Y H:i'),
        ];
    }
} 