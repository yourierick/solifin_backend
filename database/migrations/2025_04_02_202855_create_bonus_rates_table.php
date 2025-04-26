<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBonusRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bonus_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained()->onDelete('cascade');
            $table->enum('frequence', ['daily', 'weekly', 'monthly', 'yearly'])->default('weekly');
            $table->integer('nombre_filleuls')->comment('Nombre de filleuls pour obtenir 1 point (seuil)');
            $table->integer('points_attribues')->default(1)->comment('Nombre de points attribués par palier');
            $table->decimal('valeur_point', 10, 2)->comment('Valeur d\'un point en devise');
            $table->timestamps();
        });

        Schema::create('user_bonus_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('pack_id')->constrained()->onDelete('cascade');
            $table->integer('points_disponibles')->default(0);
            $table->integer('points_utilises')->default(0);
            $table->timestamps();
            
            // Clé unique pour éviter les doublons
            $table->unique(['user_id', 'pack_id']);
        });

        Schema::create('user_bonus_points_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('pack_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points');
            $table->enum('type', ['gain', 'conversion'])->default('gain');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_bonus_points_history');
        Schema::dropIfExists('user_bonus_points');
        Schema::dropIfExists('bonus_rates');
    }
}
