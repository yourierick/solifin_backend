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
        Schema::create('formation_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->text('content')->nullable(); // Contenu du module (peut être du HTML, du texte riche, etc.)
            $table->string('type')->default('text'); // Type de contenu: text, video, pdf, quiz, etc.
            $table->string('video_url')->nullable(); // URL de la vidéo si type = video
            $table->string('file_url')->nullable(); // URL du fichier si type = pdf ou autre
            $table->integer('duration')->nullable(); // Durée estimée en minutes
            $table->integer('order')->default(0); // Ordre d'affichage dans la formation
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formation_modules');
    }
};
