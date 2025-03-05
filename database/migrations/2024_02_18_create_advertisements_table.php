<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('image_path')->nullable();
            $table->string('url')->nullable();
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->string('validation_status')->default('pending'); // pending, approved, rejected
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->text('validation_note')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
}; 