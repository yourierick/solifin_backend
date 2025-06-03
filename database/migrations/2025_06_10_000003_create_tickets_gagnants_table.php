<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class CreateTicketsGagnantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tickets_gagnants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('cadeau_id')->constrained('cadeaux')->onDelete('cascade');
            $table->string('code_jeton')->unique()->comment('Code unique du jeton Esengo utilisé');
            $table->dateTime('date_expiration')->default(Carbon::now()->addHours(48));
            $table->boolean('consomme')->default(false);
            $table->dateTime('date_consommation')->nullable();
            $table->string('code_verification')->unique()->comment('Code à présenter pour récupérer le cadeau');
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
        Schema::dropIfExists('tickets_gagnants');
    }
}
