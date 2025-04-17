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
