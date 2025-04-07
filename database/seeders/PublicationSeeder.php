<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use App\Models\Publicite;
use App\Models\OffreEmploi;
use App\Models\OpportuniteAffaire;
use Illuminate\Database\Seeder;

class PublicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Récupérer tous les utilisateurs non-admin
        $users = User::where('is_admin', false)->get();

        foreach ($users as $user) {
            // Créer une page pour chaque utilisateur
            $page = Page::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'nombre_abonnes' => rand(0, 100),
                    'nombre_likes' => rand(0, 200)
                ]
            );

            // Créer des publicités pour chaque page
            for ($i = 0; $i < rand(1, 3); $i++) {
                Publicite::create([
                    'page_id' => $page->id,
                    'categorie' => rand(0, 1) ? 'produit' : 'service',
                    'titre' => 'Publicité ' . ($i + 1) . ' de ' . $user->name,
                    'description' => 'Description de la publicité ' . ($i + 1) . '. Ceci est un texte généré pour tester la fonctionnalité.',
                    'contacts' => $user->whatsapp ?? $user->phone ?? '+221 77 123 45 67',
                    'email' => $user->email,
                    'adresse' => $user->address ?? 'Adresse de test',
                    'besoin_livreurs' => rand(0, 1) ? 'OUI' : 'NON',
                    'point_vente' => 'Point de vente exemple',
                    'quantite_disponible' => rand(1, 100),
                    'prix_unitaire_vente' => rand(500, 50000),
                    'devise' => 'XOF',
                    'commission_livraison' => rand(0, 1) ? 'OUI' : 'NON',
                    'prix_unitaire_livraison' => rand(0, 1) ? rand(500, 5000) : null,
                    'statut' => ['en_attente', 'approuvé', 'rejeté'][rand(0, 2)],
                    'etat' => rand(0, 1) ? 'disponible' : 'terminé',
                    'duree_affichage' => $user->pack_de_publication ? $user->pack_de_publication->duree_publication_en_jour : 30,
                ]);
            }

            // Créer des offres d'emploi pour chaque page
            for ($i = 0; $i < rand(1, 2); $i++) {
                OffreEmploi::create([
                    'page_id' => $page->id,
                    'titre' => 'Offre d\'emploi ' . ($i + 1) . ' de ' . $user->name,
                    'entreprise' => 'Entreprise de ' . $user->name,
                    'lieu' => ['Dakar', 'Thiès', 'Saint-Louis', 'Touba'][rand(0, 3)],
                    'type_contrat' => ['CDI', 'CDD', 'Stage', 'Freelance'][rand(0, 3)],
                    'description' => 'Description du poste ' . ($i + 1) . '. Nous recherchons un profil dynamique et motivé.',
                    'competences_requises' => 'Compétences requises: communication, travail d\'équipe, autonomie',
                    'experience_requise' => ['Débutant', '1-2 ans', '2-5 ans', '+5 ans'][rand(0, 3)],
                    'niveau_etudes' => ['Bac', 'Bac+2', 'Licence', 'Master', 'Doctorat'][rand(0, 4)],
                    'salaire' => rand(150000, 1000000),
                    'devise' => 'XOF',
                    'avantages' => 'Transport, Restauration, Assurance maladie',
                    'date_limite' => now()->addDays(rand(5, 30)),
                    'email_contact' => $user->email,
                    'statut' => ['en_attente', 'approuvé', 'rejeté'][rand(0, 2)],
                    'etat' => rand(0, 1) ? 'disponible' : 'terminé',
                ]);
            }

            // Créer des opportunités d'affaires pour chaque page
            for ($i = 0; $i < rand(1, 2); $i++) {
                OpportuniteAffaire::create([
                    'page_id' => $page->id,
                    'titre' => 'Opportunité d\'affaire ' . ($i + 1) . ' de ' . $user->name,
                    'secteur' => ['Agriculture', 'Commerce', 'Technologie', 'Immobilier', 'Éducation'][rand(0, 4)],
                    'description' => 'Description de l\'opportunité ' . ($i + 1) . '. Un projet innovant avec un fort potentiel de croissance.',
                    'benefices_attendus' => 'Rentabilité élevée, expansion rapide, marché en croissance',
                    'investissement_requis' => rand(100000, 10000000),
                    'devise' => 'XOF',
                    'duree_retour_investissement' => ['6 mois', '1 an', '2 ans', '3-5 ans'][rand(0, 3)],
                    'localisation' => ['Dakar', 'Thiès', 'Saint-Louis', 'Touba'][rand(0, 3)],
                    'contacts' => $user->whatsapp ?? $user->phone ?? '+221 77 123 45 67',
                    'email' => $user->email,
                    'conditions_participation' => 'Implication active, apport financier, réseau professionnel',
                    'date_limite' => now()->addDays(rand(10, 60)),
                    'statut' => ['en_attente', 'approuvé', 'rejeté'][rand(0, 2)],
                    'etat' => rand(0, 1) ? 'disponible' : 'terminé',
                ]);
            }
        }
    }
}
