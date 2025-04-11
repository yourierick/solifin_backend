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
        Schema::create('offre_emploi_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('offre_emploi_id');
            $table->text('content');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            
            // Ajouter les contraintes de clé étrangère manuellement
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('offre_emploi_id')->references('id')->on('offres_emploi')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('offre_emploi_comments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offre_emploi_comments');
    }
};
