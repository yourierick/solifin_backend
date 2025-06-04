<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insérer les permissions par défaut
        DB::table('permissions')->insert([
            [
                'nom' => 'Gérer les utilisateurs',
                'slug' => 'manage-users',
                'description' => 'Créer, modifier et supprimer des utilisateurs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer les administrateurs',
                'slug' => 'manage-admins',
                'description' => 'Gérer les comptes administrateurs et leurs permissions',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [   
                'nom' => 'Gérer les retraits',
                'slug' => 'manage-withdrawals',
                'description' => 'Gérer les demandes de retrait',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer les portefeuilles',
                'slug' => 'manage-wallets',
                'description' => 'Gérer les portefeuilles des utilisateurs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer les packs',
                'slug' => 'manage-packs',
                'description' => 'Créer, modifier et supprimer des packs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer ses packs',
                'slug' => 'manage-own-packs',
                'description' => 'Gérer ses propres packs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer les commissions',
                'slug' => 'manage-commissions',
                'description' => 'Gérer les commissions',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Consulter les finances',
                'slug' => 'view-finances',
                'description' => 'Accéder aux rapports financiers',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer les cadeaux',
                'slug' => 'manage-gifts',
                'description' => 'Créer, modifier et supprimer des cadeaux',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer les tickets',
                'slug' => 'verify-tickets',
                'description' => 'Vérifier et valider les tickets gagnants',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer les témoignages',
                'slug' => 'manage-testimonials',
                'description' => 'Gérer les témoignages des utilisateurs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer les FAQ',
                'slug' => 'manage-faqs',
                'description' => 'Gérer les questions fréquemment posées',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer les formations',
                'slug' => 'manage-courses',
                'description' => 'Créer, modifier et publier des formations',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer les validations',
                'slug' => 'manage-validations',
                'description' => 'Gérer les validations de contenu',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Gérer le système',
                'slug' => 'manage-system',
                'description' => 'Configurer les paramètres système',
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
        Schema::dropIfExists('permissions');
    }
};
