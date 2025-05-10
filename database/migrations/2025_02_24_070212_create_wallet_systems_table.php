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
        Schema::create('wallet_systems', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('total_in', 10, 2)->default(0);
            $table->decimal('total_out', 10, 2)->default(0);
            $table->timestamps();
        });

        // Créer le wallet système par défaut
        DB::table('wallet_systems')->insert([
            [
                'balance' => 0,
                'total_in' => 0,
                'total_out' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        Schema::create('wallet_system_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_system_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['sales', 'payment', 'withdrawal', 'frais de retrait', 'frais de transfert', 'commission de retrait', 'commission de parrainage']);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_system_transactions');
        Schema::dropIfExists('wallet_systems');
    }
};
