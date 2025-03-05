<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->boolean('status')->default(true);
            $table->json('avantages');
            $table->string('formations')->nullable();
            $table->timestamps();
        });

        Schema::create('user_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('pack_id')->constrained()->onDelete('cascade');
            $table->foreignId('sponsor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->timestamp('purchase_date');
            $table->timestamp('expiry_date')->nullable();
            $table->boolean('is_admin_pack')->default(false);
            $table->enum('payment_status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('referral_prefix');
            $table->string('referral_pack_name');
            $table->string('referral_letter', 1);
            $table->string('referral_number', 4);
            $table->string('referral_code')->unique();
            $table->timestamps();

            $table->unique(['user_id', 'pack_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_packs');
        Schema::dropIfExists('packs');
    }
}; 