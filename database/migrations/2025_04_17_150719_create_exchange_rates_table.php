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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3)->comment('Code de la devise source (ex: USD)');
            $table->string('target_currency', 3)->comment('Code de la devise cible (ex: EUR)');
            $table->decimal('rate', 15, 6)->comment('Taux de conversion');
            $table->timestamp('last_api_update')->nullable()->comment('Date de la dernière mise à jour depuis l\'API');
            $table->json('api_response')->nullable()->comment('Réponse brute de l\'API');
            $table->timestamps();
            
            // Index unique pour éviter les doublons de paires de devises
            $table->unique(['currency', 'target_currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
