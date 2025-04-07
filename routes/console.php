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
