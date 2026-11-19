<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\Locale\UpdateLocaleRequest;
use App\Modules\Shared\Http\Controllers\Controller;
use App\Modules\Shared\Support\Locale;
use Illuminate\Http\RedirectResponse;

/**
 * The language switcher on the pages shown before signing in. It remembers the choice in the `locale` cookie and,
 * when someone is signed in (the setup wizard), saves it as their language too.
 */
class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated('locale');

        $request->user()?->update(['locale' => $locale]);
        Locale::remember($request, $locale);

        return back();
    }
}
