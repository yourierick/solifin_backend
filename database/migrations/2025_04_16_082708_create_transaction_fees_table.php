<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_fees', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method')->comment('Nom du moyen de paiement');
            $table->string('payment_type')->comment('Type de paiement');
            $table->decimal('transfer_fee_percentage', 8, 4)->default(0)->comment('Pourcentage des frais de transfert');
            $table->decimal('withdrawal_fee_percentage', 8, 4)->default(0)->comment('Pourcentage des frais de retrait');
            $table->decimal('fee_fixed', 10, 2)->default(0)->comment('Montant fixe des frais');
            $table->decimal('fee_cap', 10, 2)->nullable()->comment('Montant maximum des frais (optionnel)');
            $table->boolean('is_active')->default(true)->comment('Indique si ce moyen de paiement est actif');
            $table->timestamp('last_api_update')->nullable()->comment('Date de la dernière mise à jour depuis l\'API');
            $table->json('api_response')->nullable()->comment('Dernière réponse brute de l\'API');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_fees');
    }
};
