<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCadeauxTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cadeaux', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pack_id');
            $table->foreign('pack_id')->references('id')->on('packs')->onDelete('cascade');
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('valeur', 10, 2)->default(0);
            $table->decimal('probabilite', 5, 2)->default(10.00)->comment('Probabilité en pourcentage (%)');
            $table->integer('stock')->default(0);
            $table->boolean('actif')->default(true);
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
        Schema::dropIfExists('cadeaux');
    }
}
