<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('familymanager:about', function (): void {
    $this->info('FamilyManager MVP is ready.');
})->purpose('Show FamilyManager application info');
