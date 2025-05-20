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
        Schema::create('testimonial_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Type de déclencheur qui a généré cette invitation
            $table->string('trigger_type')->comment('Type d\'événement qui a déclenché l\'invitation (earnings, referrals, membership_duration, etc.)');
            
            // Données contextuelles liées au déclencheur (JSON)
            $table->json('trigger_data')->nullable()->comment('Données contextuelles liées au déclencheur (montant gagné, nombre de filleuls, etc.)');
            
            // Message personnalisé à afficher à l\'utilisateur
            $table->text('message')->nullable()->comment('Message personnalisé pour l\'invitation');
            
            // Statut de l\'invitation
            $table->enum('status', ['pending', 'displayed', 'clicked', 'submitted', 'declined', 'expired'])
                  ->default('pending')
                  ->comment('État de l\'invitation à témoigner');
            
            // Date d\'expiration de l\'invitation
            $table->timestamp('expires_at')->nullable()->comment('Date d\'expiration de l\'invitation');
            
            // ID du témoignage si l\'utilisateur en a soumis un
            $table->foreignId('testimonial_id')->nullable()->constrained()->nullOnDelete();
            
            // Dates de création et mise à jour
            $table->timestamps();
            
            // Date à laquelle l\'invitation a été affichée à l\'utilisateur
            $table->timestamp('displayed_at')->nullable();
            
            // Date à laquelle l\'utilisateur a cliqué sur l\'invitation
            $table->timestamp('clicked_at')->nullable();
            
            // Date à laquelle l\'utilisateur a soumis un témoignage ou décliné l\'invitation
            $table->timestamp('responded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonial_prompts');
    }
};
