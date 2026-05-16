<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://ajax.googleapis.com https://cdnjs.cloudflare.com https://maxcdn.bootstrapcdn.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://maxcdn.bootstrapcdn.com https://www.w3schools.com",
            "font-src 'self' https://fonts.gstatic.com https://maxcdn.bootstrapcdn.com",
            "img-src 'self' data: https:",
            "connect-src 'self' ws://localhost:6001 ws://127.0.0.1:6001 http://localhost:6001 http://127.0.0.1:6001",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
        ]));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
