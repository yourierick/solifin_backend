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
        Schema::create('user_jeton_esengos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pack_id')->constrained('packs')->onDelete('cascade');
            $table->string('code_unique')->unique();
            $table->boolean('is_used')->default(false);
            $table->timestamp('date_expiration')->nullable();
            $table->timestamp('date_utilisation')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Index pour optimiser les recherches
            $table->index(['user_id', 'is_used']);
            $table->index('code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_jeton_esengos');
    }
};
