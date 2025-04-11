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
        Schema::create('publicite_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('publicite_id');
            $table->text('comment')->nullable();
            $table->timestamps();
            
            // Ajouter les contraintes de clé étrangère manuellement
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('publicite_id')->references('id')->on('publicites')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publicite_shares');
    }
};
