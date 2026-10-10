<?php

namespace App\Http\Middleware;

use App\Services\Ads\AdManager;
use App\Services\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defensive HTTP headers to every response.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('pdftoolkit.security.headers', true)) {
            return $response;
        }

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()', false);
        // "allow-popups" keeps the protection against cross-origin openers while
        // letting ad formats such as popunders and direct links open windows.
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups', false);

        if (config('pdftoolkit.security.hsts') && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        if (config('pdftoolkit.security.csp') && ! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->thirdPartyCode()
                ? $this->relaxedPolicy()
                : $this->strictPolicy());
        }

        $admin = trim(config('pdftoolkit.admin.path', 'admin'), '/');

        if ($request->is($admin, $admin.'/*', 'api/*', 'download/*')) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /**
     * Ads, analytics or custom code from the admin load scripts from other
     * domains, which a strict policy would block.
     */
    private function thirdPartyCode(): bool
    {
        $settings = app(SettingsRepository::class);

        foreach (['ga4_id', 'gtm_id', 'analytics_code', 'head_code', 'body_code'] as $key) {
            if ($settings->get($key)) {
                return true;
            }
        }

        return app(AdManager::class)->active();
    }

    private function strictPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'wasm-unsafe-eval' blob:",
            "worker-src 'self' blob:",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self' blob: data: https://cdn.jsdelivr.net",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }

    private function relaxedPolicy(): string
    {
        return implode('; ', [
            "default-src 'self' https:",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' 'wasm-unsafe-eval' blob: https:",
            "worker-src 'self' blob:",
            "style-src 'self' 'unsafe-inline' https:",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https:",
            "connect-src 'self' blob: data: https:",
            'frame-src https:',
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
