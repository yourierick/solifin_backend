<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('content');
            $table->integer('rating')->nullable();
            $table->string('position')->nullable(); // Poste ou rôle de l'utilisateur
            $table->string('company')->nullable(); // Entreprise de l'utilisateur
            $table->boolean('status')->default(false); // Pour modération
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
}; 