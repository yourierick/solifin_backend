<?php

namespace App\Notifications;

use App\Models\Pack;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PackPurchased extends Notification implements ShouldQueue
{
    use Queueable;

    protected $pack;
    protected $buyer;

    public function __construct(Pack $pack, User $buyer)
    {
        $this->pack = $pack;
        $this->buyer = $buyer;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        // Si le notifiable est l'acheteur
        if ($notifiable->id === $this->buyer->id) {
            return [
                'message' => 'Vous avez acheté le pack ' . $this->pack->name,
                'pack_id' => $this->pack->id,
                'pack_name' => $this->pack->name,
                'amount' => $this->pack->price,
                'purchase_date' => now()->format('d/m/Y H:i'),
            ];
        }

        // Si le notifiable est un parrain qui reçoit une commission
        return [
            'message' => $this->buyer->name . ' a acheté le pack ' . $this->pack->name . ' - Commission reçue',
            'pack_id' => $this->pack->id,
            'pack_name' => $this->pack->name,
            'buyer_name' => $this->buyer->name,
            'purchase_date' => now()->format('d/m/Y H:i'),
        ];
    }
} 