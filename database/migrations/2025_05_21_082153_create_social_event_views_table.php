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
        Schema::create('social_event_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('social_event_id');
            $table->timestamp('viewed_at');
            $table->timestamps();
            
            // Ajouter les contraintes de clé étrangère manuellement
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('social_event_id')->references('id')->on('social_events')->onDelete('cascade');
            
            // Ajouter un index unique pour éviter les vues dupliquées du même utilisateur
            $table->unique(['user_id', 'social_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_event_views');
    }
};
