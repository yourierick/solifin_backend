<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('sector');
            $table->decimal('investment_min', 10, 2)->nullable();
            $table->decimal('investment_max', 10, 2)->nullable();
            $table->text('requirements');
            $table->text('benefits');
            $table->string('location');
            $table->date('deadline')->nullable();
            $table->string('contact_email');
            $table->string('url')->nullable();
            $table->string('contact_phone')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_opportunities');
    }
}; 