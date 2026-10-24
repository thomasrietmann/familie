<?php

use App\Http\Middleware\EnforceHttps;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(at: fn () => ['^'.preg_quote((string) parse_url(config('app.url'), PHP_URL_HOST), '/').'$'], subdomains: false);
        $middleware->append([
            SecurityHeaders::class,
            EnforceHttps::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception) {
            if (app()->isProduction()) {
                Log::error('Application error', [
                    'exception_class' => $exception::class,
                    'route' => app()->runningInConsole() ? null : request()->route()?->getName(),
                ]);

                return false;
            }

            return null;
        });
        $exceptions->dontFlash(['current_password', 'recaptcha_token', 'notes', 'description']);
    })->create();
