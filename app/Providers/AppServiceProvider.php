<?php

namespace App\Providers;

use App\Models\Child;
use App\Models\DocumentImport;
use App\Models\Family;
use App\Models\FamilyEvent;
use App\Models\ImportedEventSuggestion;
use App\Observers\AuditableObserver;
use App\Policies\ChildPolicy;
use App\Policies\DocumentImportPolicy;
use App\Policies\FamilyEventPolicy;
use App\Policies\FamilyPolicy;
use App\Policies\ImportedEventSuggestionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ([Family::class, Child::class, FamilyEvent::class, DocumentImport::class, ImportedEventSuggestion::class] as $model) {
            $model::observe(AuditableObserver::class);
        }
        foreach (['login' => [20, 1], 'registration' => [5, 10], 'public-links' => [120, 1], 'sensitive' => [10, 1], 'uploads' => [5, 10]] as $name => [$attempts, $minutes]) {
            RateLimiter::for($name, function (Request $request) use ($attempts, $minutes, $name) {
                $identity = in_array($name, ['uploads', 'sensitive'], true) ? ($request->user()?->id ?? $request->ip()) : $request->ip();

                return Limit::perMinutes($minutes, $attempts)
                    ->by(hash_hmac('sha256', $name.'|'.$identity, (string) config('app.key')));
            });
        }
        if (app()->isProduction() && config('security.force_https')) {
            URL::forceScheme('https');
        }
        Gate::policy(Family::class, FamilyPolicy::class);
        Gate::policy(Child::class, ChildPolicy::class);
        Gate::policy(FamilyEvent::class, FamilyEventPolicy::class);
        Gate::policy(DocumentImport::class, DocumentImportPolicy::class);
        Gate::policy(ImportedEventSuggestion::class, ImportedEventSuggestionPolicy::class);
    }
}
