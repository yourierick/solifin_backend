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
        Schema::create('opportunite_affaire_likes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('opportunite_affaire_id');
            $table->timestamps();
            
            // Assurer qu'un utilisateur ne peut liker qu'une seule fois une opportunité d'affaire
            $table->unique(['user_id', 'opportunite_affaire_id']);
            
            // Ajouter les contraintes de clé étrangère manuellement
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('opportunite_affaire_id')->references('id')->on('opportunites_affaires')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunite_affaire_likes');
    }
};
