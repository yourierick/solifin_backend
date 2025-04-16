<?php

namespace App\Console\Commands;

use App\Models\TransactionFee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateTransactionFees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transaction-fees:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Met à jour les frais de transaction depuis l\'API externe';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Démarrage de la mise à jour des frais de transaction...');
        
        try {
            $result = TransactionFee::updateFeesFromApi();
            
            if ($result) {
                $this->info('Frais de transaction mis à jour avec succès!');
                
                // Afficher un résumé des frais mis à jour
                $fees = TransactionFee::where('last_api_update', '>=', now()->subMinutes(5))->get();
                
                if ($fees->count() > 0) {
                    $this->info("Nombre de moyens de paiement mis à jour: {$fees->count()}");
                    
                    $this->table(
                        ['Moyen de paiement', 'Fournisseur', 'Frais de transfert (%)', 'Frais de retrait (%)', 'Frais d\'achat (%)'],
                        $fees->map(function ($fee) {
                            return [
                                $fee->payment_method,
                                $fee->provider,
                                $fee->transfer_fee_percentage,
                                $fee->withdrawal_fee_percentage,
                                $fee->purchase_fee_percentage
                            ];
                        })
                    );
                } else {
                    $this->info('Aucun moyen de paiement n\'a été mis à jour.');
                }
                
                return Command::SUCCESS;
            } else {
                $this->error('Erreur lors de la mise à jour des frais de transaction.');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Exception lors de la mise à jour des frais de transaction: ' . $e->getMessage());
            Log::error('Exception lors de la mise à jour des frais de transaction via la commande', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
