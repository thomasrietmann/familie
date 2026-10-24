<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && config('security.force_https') && ! $request->secure()) {
            $baseUrl = preg_replace('/\Ahttp:\/\//i', 'https://', rtrim((string) config('app.url'), '/'));

            return redirect()->to($baseUrl.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
