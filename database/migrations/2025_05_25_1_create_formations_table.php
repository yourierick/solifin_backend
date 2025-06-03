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
        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category');
            $table->text('description');
            $table->text('thumbnail')->nullable(); // URL de l'image de couverture
            $table->enum('status', ['draft', 'pending', 'published', 'rejected'])->default('draft');
            $table->enum('type', ['admin', 'user'])->default('admin'); // Si créée par admin ou utilisateur
            $table->foreignId('created_by')->constrained('users'); // ID de l'utilisateur ou admin qui a créé la formation
            $table->boolean('is_paid')->default(false); // Si la formation est payante (pour les formations créées par les utilisateurs)
            $table->decimal('price', 10, 2)->nullable(); // Prix si la formation est payante
            $table->string('currency', 3)->default('USD'); // Devise du prix
            $table->text('rejection_reason')->nullable(); // Raison du rejet si status = rejected
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formations');
    }
};
