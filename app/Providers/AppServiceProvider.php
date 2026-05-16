<?php

namespace App\Providers;

use App\Models\Child;
use App\Models\DocumentImport;
use App\Models\Family;
use App\Models\FamilyEvent;
use App\Models\ImportedEventSuggestion;
use App\Policies\ChildPolicy;
use App\Policies\DocumentImportPolicy;
use App\Policies\FamilyEventPolicy;
use App\Policies\FamilyPolicy;
use App\Policies\ImportedEventSuggestionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Family::class, FamilyPolicy::class);
        Gate::policy(Child::class, ChildPolicy::class);
        Gate::policy(FamilyEvent::class, FamilyEventPolicy::class);
        Gate::policy(DocumentImport::class, DocumentImportPolicy::class);
        Gate::policy(ImportedEventSuggestion::class, ImportedEventSuggestionPolicy::class);
    }
}
