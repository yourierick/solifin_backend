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
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('faq_categories')->onDelete('cascade');
            $table->string('question');
            $table->text('answer');
            $table->boolean('is_featured')->default(false);
            $table->integer('order')->default(0);
            $table->integer('helpful_votes')->default(0);
            $table->integer('unhelpful_votes')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
