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
        Schema::create('user_jeton_esengo_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('jeton_id')->nullable()->constrained('user_jeton_esengos')->onDelete('set null');
            $table->foreignId('cadeau_id')->nullable()->constrained('cadeaux')->onDelete('set null');
            $table->string('code_unique')->nullable();
            $table->string('action_type')->comment('attribution, utilisation, expiration');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Index pour optimiser les recherches
            $table->index(['user_id', 'action_type']);
            $table->index('jeton_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_jeton_esengo_histories');
    }
};
