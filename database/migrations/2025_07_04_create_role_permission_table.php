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
        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->primary(['role_id', 'permission_id']);
            $table->timestamps();
        });

        // Attribuer les permissions aux rôles par défaut
        $superAdminRole = DB::table('roles')->where('slug', 'super-admin')->first();
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        $gestionnaireRole = DB::table('roles')->where('slug', 'gestionnaire')->first();

        // Récupérer toutes les permissions
        $permissions = DB::table('permissions')->get();
        $viewReportsPermission = DB::table('permissions')->where('slug', 'view-reports')->first();
        $verifyTicketsPermission = DB::table('permissions')->where('slug', 'verify-tickets')->first();
        $manageGiftsPermission = DB::table('permissions')->where('slug', 'manage-gifts')->first();

        // Super Admin a toutes les permissions
        foreach ($permissions as $permission) {
            DB::table('role_permission')->insert([
                'role_id' => $superAdminRole->id,
                'permission_id' => $permission->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Admin a toutes les permissions sauf gérer le système
        foreach ($permissions as $permission) {
            if ($permission->slug !== 'manage-system') {
                DB::table('role_permission')->insert([
                    'role_id' => $adminRole->id,
                    'permission_id' => $permission->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Gestionnaire a des permissions limitées
        $gestionnairePermissions = ['view-reports', 'verify-tickets', 'manage-gifts'];
        foreach ($permissions as $permission) {
            if (in_array($permission->slug, $gestionnairePermissions)) {
                DB::table('role_permission')->insert([
                    'role_id' => $gestionnaireRole->id,
                    'permission_id' => $permission->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permission');
    }
};
