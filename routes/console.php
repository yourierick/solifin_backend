<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Définition des tâches planifiées pour Laravel 11
Schedule::command('packs:check-expiration')
    ->daily()
    ->description('Vérifie les packs expirés tous les jours à minuit');

Schedule::command('publications:update-status')
    ->daily()
    ->description('Met à jour le statut des publications expirées tous les jours à minuit');

Schedule::command('transaction-fees:update')
    ->daily()
    ->at('01:00')
    ->description('Met à jour les frais de transaction depuis l\'API externe tous les jours à 1h du matin');

Schedule::command('exchange:update')
    ->daily()
    ->at('01:30')
    ->description('Met à jour les taux de change des devises depuis une API externe tous les jours à 1h30 du matin');

// Planification de l'attribution des points bonus pour chaque fréquence
// Points bonus journaliers (tous les jours à minuit)
Schedule::command('solifin:process-bonus-points daily')
    ->dailyAt('00:15')
    ->appendOutputTo(storage_path('logs/bonus-points-daily.log'))
    ->description('Attribue les points bonus journaliers aux utilisateurs');

// Points bonus hebdomadaires (chaque lundi à 00:30)
Schedule::command('solifin:process-bonus-points weekly')
    ->weeklyOn(1, '00:30') // 1 = Lundi
    ->appendOutputTo(storage_path('logs/bonus-points-weekly.log'))
    ->description('Attribue les points bonus hebdomadaires aux utilisateurs');

// Points bonus mensuels (le premier jour de chaque mois à 00:45)
Schedule::command('solifin:process-bonus-points monthly')
    ->monthlyOn(1, '00:45')
    ->appendOutputTo(storage_path('logs/bonus-points-monthly.log'))
    ->description('Attribue les points bonus mensuels aux utilisateurs');

// Points bonus annuels (le 1er janvier à 01:00)
Schedule::command('solifin:process-bonus-points yearly')
    ->yearlyOn(1, 1, '01:00') // 1er janvier
    ->appendOutputTo(storage_path('logs/bonus-points-yearly.log'))
    ->description('Attribue les points bonus annuels aux utilisateurs');

// Planification du traitement des invitations à témoigner
// Vérification quotidienne des utilisateurs éligibles (tous les jours à 02:00)
Schedule::command('testimonials:process-prompts --expire')
    ->everyMinute()
    ->appendOutputTo(storage_path('logs/testimonial-prompts.log'))
    ->description('Vérifie les utilisateurs éligibles et crée des invitations à témoigner');

// Vérification hebdomadaire plus approfondie (chaque dimanche à 03:00)
Schedule::command('testimonials:process-prompts --batch=500 --expire')
    ->weeklyOn(0, '03:00') // 0 = Dimanche
    ->appendOutputTo(storage_path('logs/testimonial-prompts-weekly.log'))
    ->description('Vérification hebdomadaire approfondie des invitations à témoigner');
