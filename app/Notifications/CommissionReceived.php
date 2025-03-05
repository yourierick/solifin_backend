<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommissionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    protected $commission;
    protected $pack;
    protected $generation;

    public function __construct($commission, $pack, $generation)
    {
        $this->commission = $commission;
        $this->pack = $pack;
        $this->generation = $generation;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Nouvelle Commission Reçue!')
            ->greeting('Bonjour ' . $notifiable->name)
            ->line('Vous avez reçu une nouvelle commission!')
            ->line('Détails de la commission:')
            ->line('- Montant: ' . number_format($this->commission, 2) . '$')
            ->line('- Pack: ' . $this->pack->name)
            ->line('- Génération: ' . $this->generation)
            ->action('Voir mon tableau de bord', url('/dashboard'))
            ->line('Merci de votre participation au programme de parrainage!');
    }

    public function toArray($notifiable)
    {
        return [
            'amount' => $this->commission,
            'pack_id' => $this->pack->id,
            'pack_name' => $this->pack->name,
            'generation' => $this->generation,
            'type' => 'commission_received'
        ];
    }
}
