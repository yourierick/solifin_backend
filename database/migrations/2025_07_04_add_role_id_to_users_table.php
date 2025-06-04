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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('is_admin')->constrained();
        });

        // Migrer les utilisateurs existants vers le nouveau système de rôles
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        $userRoleId = DB::table('roles')->where('slug', 'user')->value('id');

        // Attribuer le rôle admin aux utilisateurs qui ont is_admin = true
        DB::table('users')->where('is_admin', true)->update(['role_id' => $adminRoleId]);
        
        // Attribuer le rôle user aux utilisateurs qui ont is_admin = false
        DB::table('users')->where('is_admin', false)->update(['role_id' => $userRoleId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
