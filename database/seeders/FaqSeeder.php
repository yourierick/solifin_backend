<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Création des catégories
        $generalCategory = FaqCategory::create([
            'name' => 'Général',
            'slug' => 'general',
            'icon' => 'information-circle',
            'order' => 1
        ]);

        $parrainageCategory = FaqCategory::create([
            'name' => 'Parrainage',
            'slug' => 'parrainage',
            'icon' => 'user-group',
            'order' => 2
        ]);

        $commissionsCategory = FaqCategory::create([
            'name' => 'Commissions',
            'slug' => 'commissions',
            'icon' => 'currency-dollar',
            'order' => 3
        ]);

        $paiementsCategory = FaqCategory::create([
            'name' => 'Paiements',
            'slug' => 'paiements',
            'icon' => 'credit-card',
            'order' => 4
        ]);

        // Création des FAQ pour la catégorie Général
        $faq1 = Faq::create([
            'category_id' => $generalCategory->id,
            'question' => 'Qu\'est-ce que SOLIFIN ?',
            'answer' => '<p>SOLIFIN est une plateforme de parrainage et de marketing relationnel qui permet à ses membres de générer des revenus en recommandant de nouveaux membres. Notre système est basé sur la solidarité financière et l\'entraide.</p>',
            'is_featured' => true,
            'order' => 1
        ]);

        $faq2 = Faq::create([
            'category_id' => $generalCategory->id,
            'question' => 'Comment puis-je créer un compte sur SOLIFIN ?',
            'answer' => '<p>Pour créer un compte sur SOLIFIN, suivez ces étapes :</p><ol><li>Cliquez sur le bouton "S\'inscrire" en haut à droite de la page d\'accueil</li><li>Remplissez le formulaire avec vos informations personnelles</li><li>Choisissez un pack d\'adhésion</li><li>Effectuez le paiement</li><li>Confirmez votre adresse e-mail en cliquant sur le lien reçu</li></ol>',
            'is_featured' => true,
            'order' => 2
        ]);

        // Création des FAQ pour la catégorie Parrainage
        $faq3 = Faq::create([
            'category_id' => $parrainageCategory->id,
            'question' => 'Comment fonctionne le système de parrainage ?',
            'answer' => '<p>Notre système de parrainage fonctionne sur plusieurs niveaux :</p><ul><li>Niveau 1 : Vos filleuls directs</li><li>Niveau 2 : Les filleuls de vos filleuls</li><li>Niveau 3 : Les filleuls des filleuls de vos filleuls</li></ul><p>Vous recevez des commissions à chaque fois qu\'une personne s\'inscrit en utilisant votre code de parrainage, ainsi que sur les inscriptions réalisées par vos filleuls.</p>',
            'is_featured' => true,
            'order' => 1
        ]);

        $faq4 = Faq::create([
            'category_id' => $parrainageCategory->id,
            'question' => 'Comment obtenir mon lien de parrainage ?',
            'answer' => '<p>Votre lien de parrainage est disponible dans votre tableau de bord, sous la section "Mon réseau". Vous pouvez le copier et le partager via différents canaux : réseaux sociaux, e-mail, messageries, etc.</p>',
            'is_featured' => false,
            'order' => 2
        ]);

        // Création des FAQ pour la catégorie Commissions
        $faq5 = Faq::create([
            'category_id' => $commissionsCategory->id,
            'question' => 'Quels sont les différents niveaux de commission ?',
            'answer' => '<p>SOLIFIN propose différents niveaux de commission selon la profondeur de votre réseau :</p><ul><li>Niveau 1 : 10% du montant de l\'inscription</li><li>Niveau 2 : 5% du montant de l\'inscription</li><li>Niveau 3 : 2% du montant de l\'inscription</li></ul><p>Plus votre réseau s\'agrandit, plus vos commissions augmentent.</p>',
            'is_featured' => true,
            'order' => 1
        ]);

        $faq6 = Faq::create([
            'category_id' => $commissionsCategory->id,
            'question' => 'Quand mes commissions sont-elles créditées ?',
            'answer' => '<p>Les commissions sont créditées instantanément sur votre compte SOLIFIN dès qu\'un filleul complète son inscription et effectue son paiement. Vous pouvez suivre toutes vos transactions dans la section "Mes commissions" de votre tableau de bord.</p>',
            'is_featured' => false,
            'order' => 2
        ]);

        // Création des FAQ pour la catégorie Paiements
        $faq7 = Faq::create([
            'category_id' => $paiementsCategory->id,
            'question' => 'Comment puis-je retirer mes gains ?',
            'answer' => '<p>Pour retirer vos gains, rendez-vous dans la section "Portefeuille" de votre tableau de bord et cliquez sur "Retrait". Vous pouvez choisir parmi plusieurs méthodes de paiement : virement bancaire, mobile money, ou crypto-monnaies. Le délai de traitement varie selon la méthode choisie, généralement entre 24h et 72h.</p>',
            'is_featured' => true,
            'order' => 1
        ]);

        $faq8 = Faq::create([
            'category_id' => $paiementsCategory->id,
            'question' => 'Y a-t-il des frais pour les retraits ?',
            'answer' => '<p>Des frais de transaction peuvent s\'appliquer selon la méthode de retrait choisie :</p><ul><li>Virement bancaire : 2% du montant (minimum 5$)</li><li>Mobile Money : 1% du montant</li><li>Crypto-monnaies : frais de réseau variables</li></ul><p>Ces frais sont clairement indiqués avant la confirmation de votre demande de retrait.</p>',
            'is_featured' => false,
            'order' => 2
        ]);

        // Création des relations entre FAQ
        $faq1->relatedFaqs()->attach([$faq2->id, $faq3->id]);
        $faq3->relatedFaqs()->attach([$faq4->id, $faq5->id]);
        $faq5->relatedFaqs()->attach([$faq6->id]);
        $faq7->relatedFaqs()->attach([$faq8->id]);
    }
}
