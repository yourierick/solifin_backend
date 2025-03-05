<?php

namespace App\Notifications;

use App\Models\Advertisement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdvertisementValidated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $advertisement;

    public function __construct(Advertisement $advertisement)
    {
        $this->advertisement = $advertisement;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $status = ucfirst($this->advertisement->validation_status);
        $subject = "Your Advertisement has been {$status}";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name}")
            ->line("Your advertisement '{$this->advertisement->title}' has been {$this->advertisement->validation_status}.")
            ->when($this->advertisement->validation_note, function ($message) {
                return $message->line("Note from administrator: {$this->advertisement->validation_note}");
            })
            ->when($this->advertisement->isApproved(), function ($message) {
                return $message->line("Your advertisement will be published according to the scheduled dates.");
            })
            ->when($this->advertisement->isRejected(), function ($message) {
                return $message->line("You can submit a new advertisement or contact support for more information.");
            })
            ->action('View Advertisement', url("/advertisements/{$this->advertisement->id}"));
    }

    public function toArray($notifiable)
    {
        return [
            'advertisement_id' => $this->advertisement->id,
            'title' => $this->advertisement->title,
            'status' => $this->advertisement->validation_status,
            'note' => $this->advertisement->validation_note,
            'validated_by' => $this->advertisement->validator->name,
            'validated_at' => $this->advertisement->validated_at->toISOString()
        ];
    }
}
