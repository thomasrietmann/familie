<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $origins = $request->routeIs('public.family', 'public.dashboard') && $response->isSuccessful()
            ? $this->publicFrameOrigins()
            : [];
        $frameAncestors = 'frame-ancestors '.($origins === [] ? "'none'" : implode(' ', $origins));
        // A local Vite development server needs its own script/WebSocket origins.
        if (! app()->isLocal()) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'", "base-uri 'self'", "object-src 'none'",
                $frameAncestors, "form-action 'self'",
                "script-src 'self' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/",
                "style-src 'self' 'unsafe-inline'", "img-src 'self' data:", "font-src 'self'",
                "connect-src 'self' https://www.google.com/recaptcha/",
                'frame-src https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/',
            ]));
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($origins === []) {
            $response->headers->set('X-Frame-Options', 'DENY');
        } else {
            // X-Frame-Options cannot express an origin allowlist; CSP enforces it.
            $response->headers->remove('X-Frame-Options');
            if (app()->isLocal()) {
                $response->headers->set('Content-Security-Policy', $frameAncestors);
            }
        }
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        if (app()->isProduction() && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function publicFrameOrigins(): array
    {
        $origins = array_values(array_filter(array_map('trim', explode(',', (string) config('security.public_frame_origins', ''))), fn (string $origin) => $origin !== ''));
        foreach ($origins as $origin) {
            // Accept exact HTTP(S) origins only, never CSP keywords, paths or wildcards.
            if (! preg_match('~\Ahttps?://(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:]+\])(?::[0-9]{1,5})?\z~i', $origin)
                || filter_var($origin, FILTER_VALIDATE_URL) === false
                || (($port = parse_url($origin, PHP_URL_PORT)) !== null && ($port < 1 || $port > 65535))) {
                return [];
            }
        }

        return array_values(array_unique($origins));
    }
}
