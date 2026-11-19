<?php

namespace App\Modules\Shared\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * The app's languages and the `locale` cookie that remembers the last one used in this browser, so pages shown
 * before signing in (welcome, sign in, register) keep it. A signed-in user's saved language always wins and is
 * copied into the cookie, so signing out keeps it too.
 */
final class Locale
{
    public const SUPPORTED = ['en', 'es'];

    public const COOKIE = 'locale';

    public static function isSupported(mixed $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    /**
     * The language for a request: the signed-in user's, then the cookie's, then the browser's, then the app default.
     */
    public static function for(Request $request): string
    {
        $userLocale = $request->user()?->locale;
        if (self::isSupported($userLocale)) {
            return $userLocale;
        }

        $cookieLocale = $request->cookie(self::COOKIE);
        if (self::isSupported($cookieLocale)) {
            return $cookieLocale;
        }

        if ($request->hasHeader('Accept-Language')) {
            return $request->getPreferredLanguage(self::SUPPORTED) ?? config('app.locale');
        }

        return config('app.locale');
    }

    /**
     * Sends the cookie with the response, unless the request already carries the same value.
     */
    public static function remember(Request $request, string $locale): void
    {
        if ($request->cookie(self::COOKIE) !== $locale) {
            Cookie::queue(Cookie::forever(self::COOKIE, $locale));
        }
    }
}
