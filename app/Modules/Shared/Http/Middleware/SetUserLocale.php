<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the signed-in user's saved language for the request, so server-side strings
 * (flash messages, enum labels, exports) and the shared i18n props follow it.
 */
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (in_array($locale, ['en', 'es'], true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
