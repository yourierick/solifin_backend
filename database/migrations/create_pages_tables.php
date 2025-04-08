<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Table pour les pages utilisateurs
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('nombre_abonnes')->default(0);
            $table->integer('nombre_likes')->default(0);
            $table->timestamps();
        });

        // Table pour les abonnés d'une page
        Schema::create('page_abonnes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['page_id', 'user_id']);
        });

        // Table pour les publicités
        Schema::create('publicites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->enum('categorie', ['produit', 'service']);
            $table->string('titre');
            $table->text('description');
            $table->string('image')->nullable();
            $table->string('video')->nullable();
            $table->string('contacts');
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            $table->enum('besoin_livreurs', ['OUI', 'NON'])->default('NON');
            $table->json('conditions_livraison')->nullable();
            $table->string('point_vente')->nullable();
            $table->integer('quantite_disponible')->nullable();
            $table->decimal('prix_unitaire_vente', 15, 2);
            $table->string('devise', 5)->default('XOF');
            $table->enum('commission_livraison', ['OUI', 'NON'])->default('NON');
            $table->decimal('prix_unitaire_livraison', 15, 2)->nullable();
            $table->string('lien')->nullable();
            $table->enum('statut', ['en_attente', 'approuvé', 'rejeté', 'expiré'])->default('en_attente');
            $table->enum('etat', ['disponible', 'terminé'])->default('disponible');
            $table->integer('duree_affichage')->nullable()->comment('Durée en jours'); 
            $table->timestamps();
        });

        // Table pour les offres d'emploi
        Schema::create('offres_emploi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->string('titre');
            $table->string('entreprise');
            $table->string('lieu');
            $table->string('type_contrat');
            $table->text('description');
            $table->text('competences_requises');
            $table->string('experience_requise');
            $table->string('niveau_etudes')->nullable();
            $table->string('salaire')->nullable();
            $table->string('devise', 5)->nullable()->default('FC');
            $table->text('avantages')->nullable();
            $table->date('date_limite')->nullable();
            $table->string('email_contact');
            $table->string('contacts')->nullable();
            $table->string('offer_file')->nullable();
            $table->string('lien')->nullable();
            $table->enum('statut', ['en_attente', 'approuvé', 'rejeté', 'expiré'])->default('en_attente');
            $table->enum('etat', ['disponible', 'terminé'])->default('disponible');
            $table->timestamps();
        });

        // Table pour les opportunités d'affaires
        Schema::create('opportunites_affaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->string('titre');
            $table->string('secteur');
            $table->text('description');
            $table->text('benefices_attendus');
            $table->decimal('investissement_requis', 15, 2)->nullable();
            $table->string('devise', 5)->nullable()->default('XOF');
            $table->string('duree_retour_investissement')->nullable();
            $table->string('image')->nullable();
            $table->string('localisation')->nullable();
            $table->string('contacts');
            $table->string('email')->nullable();
            $table->text('conditions_participation')->nullable();
            $table->date('date_limite')->nullable();
            $table->enum('statut', ['en_attente', 'approuvé', 'rejeté', 'expiré'])->default('en_attente');
            $table->enum('etat', ['disponible', 'terminé'])->default('disponible');
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
        Schema::dropIfExists('opportunites_affaires');
        Schema::dropIfExists('offres_emploi');
        Schema::dropIfExists('publicites');
        Schema::dropIfExists('page_abonnes');
        Schema::dropIfExists('pages');
    }
};
