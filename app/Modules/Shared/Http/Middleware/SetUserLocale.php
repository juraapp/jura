<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the request's language (`Locale::for`: the signed-in user's saved language, else the `locale` cookie, else
 * the browser's), so server-side strings (flash messages, enum labels, exports) and the shared i18n props follow it.
 * For a signed-in user it also keeps the cookie in step with their saved language.
 */
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::for($request);
        App::setLocale($locale);

        if (Locale::isSupported($request->user()?->locale)) {
            Locale::remember($request, $locale);
        }

        return $next($request);
    }
}
