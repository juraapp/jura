<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a user who has neither finished nor skipped the setup wizard back to it when they
 * open a page. Only page visits are redirected: form submissions and JSON requests pass
 * through, because the wizard itself saves each step through the regular endpoints.
 */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && $user->onboarding_completed_at === null
            && $request->isMethod('GET')
            && ! $request->expectsJson()) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
