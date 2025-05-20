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
        Schema::create('faq_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained('faqs')->onDelete('cascade');
            $table->foreignId('related_faq_id')->constrained('faqs')->onDelete('cascade');
            $table->unique(['faq_id', 'related_faq_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faq_related');
    }
};
