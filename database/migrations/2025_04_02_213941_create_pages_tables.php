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
            $table->string('photo_de_couverture', 300)->nullable();
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
            $table->string('pays');
            $table->string('ville');
            $table->enum('type', ['publicité', 'annonce']);
            $table->enum('categorie', ['produit', 'service']);
            $table->enum('sous_categorie', ['location de véhicule', 'location de maison', 'réservation', 'livraison', 'vente', 'sous-traitance', 'autre à préciser']);
            $table->string('autre_sous_categorie')->nullable();
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
            $table->string('devise', 5)->default('FC');
            $table->enum('commission_livraison', ['OUI', 'NON'])->default('NON');
            $table->decimal('prix_unitaire_livraison', 15, 2)->nullable();
            $table->string('lien')->nullable();
            $table->enum('statut', ['en_attente', 'approuvé', 'rejeté', 'expiré'])->default('en_attente');
            $table->string('raison_rejet')->nullable();
            $table->enum('etat', ['disponible', 'terminé'])->default('disponible');
            $table->integer('duree_affichage')->comment('Durée en jours'); 
            $table->timestamps();
        });

        // Table pour les offres d'emploi
        Schema::create('offres_emploi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['offre_emploi', 'appel_manifestation_intéret']);
            $table->string('pays');
            $table->string('ville');
            $table->string('secteur');
            $table->string('entreprise');
            $table->string('titre');
            $table->string('reference');
            $table->text('description');
            $table->string('type_contrat');
            $table->date('date_limite');
            $table->string('email_contact');
            $table->string('contacts');
            $table->string('offer_file');
            $table->string('lien')->nullable();
            $table->enum('statut', ['en_attente', 'approuvé', 'rejeté', 'expiré'])->default('en_attente');
            $table->string('raison_rejet')->nullable();
            $table->enum('etat', ['disponible', 'terminé'])->default('disponible');
            $table->integer('duree_affichage')->comment('Durée en jours'); 
            $table->timestamps();
        });

        // Table pour les opportunités d'affaires
        Schema::create('opportunites_affaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['partenariat', 'appel_projet', 'opportunité']);
            $table->string('pays');
            $table->string('ville');
            $table->string('secteur');
            $table->string('entreprise');
            $table->string('titre');
            $table->string('reference');
            $table->text('description');
            $table->string('contacts');
            $table->string('email');
            $table->string('opportunity_file');
            $table->string('lien')->nullable();
            $table->date('date_limite');
            $table->enum('statut', ['en_attente', 'approuvé', 'rejeté', 'expiré'])->default('en_attente');
            $table->string('raison_rejet')->nullable();
            $table->enum('etat', ['disponible', 'terminé'])->default('disponible');
            $table->integer('duree_affichage')->comment('Durée en jours'); 
            $table->timestamps();
        });

        Schema::create('social_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->string('image')->nullable();
            $table->string('video')->nullable();
            $table->string('description', 1000)->nullable();
            $table->enum('statut', ['en_attente', 'approuvé', 'rejeté', 'expiré'])->default('en_attente');
            $table->string('raison_rejet')->nullable();
            $table->enum('etat', ['disponible', 'terminé'])->default('disponible');
            $table->integer('duree_affichage')->comment('Durée en jours'); 
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
        Schema::dropIfExists('social_events');
        Schema::dropIfExists('opportunites_affaires');
        Schema::dropIfExists('offres_emploi');
        Schema::dropIfExists('publicites');
        Schema::dropIfExists('page_abonnes');
        Schema::dropIfExists('pages');
    }
};
