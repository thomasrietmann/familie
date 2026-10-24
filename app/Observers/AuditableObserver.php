<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function created(Model $model): void
    {
        app(AuditLogger::class)->record('created', $model);
    }

    public function updated(Model $model): void
    {
        app(AuditLogger::class)->record('updated', $model, array_keys($model->getChanges()));
    }

    public function deleting(Model $model): void
    {
        app(AuditLogger::class)->record('deleted', $model);
    }
}
