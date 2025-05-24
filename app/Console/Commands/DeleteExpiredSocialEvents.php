<?php

namespace App\Console\Commands;

use App\Models\SocialEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteExpiredSocialEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-expired-social-events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete social events that are older than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to delete expired social events...');
        
        // Trouver tous les statuts sociaux approuvés qui ont plus de 24 heures
        $expiredEvents = SocialEvent::where('status', 'approuve')->where('created_at', '<', now()->subHours(24))->get();
        
        $count = $expiredEvents->count();
        $this->info("Found {$count} expired social events to delete.");
        
        foreach ($expiredEvents as $event) {
            try {
                // Supprimer les fichiers associés (images, vidéos)
                if ($event->image) {
                    Storage::disk('public')->delete($event->image);
                }
                
                if ($event->video) {
                    Storage::disk('public')->delete($event->video);
                }
                
                // Supprimer les signalements associés
                $event->reports()->delete();
                
                // Supprimer le statut social
                $event->delete();
                
                $this->info("Deleted social event ID: {$event->id}");
            } catch (\Exception $e) {
                $this->error("Error deleting social event ID: {$event->id} - {$e->getMessage()}");
                Log::error("Error deleting social event: {$e->getMessage()}", [
                    'event_id' => $event->id,
                    'exception' => $e,
                ]);
            }
        }
        
        $this->info('Finished deleting expired social events.');
        
        return 0;
    }
}
