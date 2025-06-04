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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insérer les rôles par défaut
        DB::table('roles')->insert([
            [
                'nom' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Accès complet au système',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gestionnaire',
                'slug' => 'gestionnaire',
                'description' => 'Accès limité aux rapports et à la maintenance',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Utilisateur',
                'slug' => 'user',
                'description' => 'Utilisateur standard',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
