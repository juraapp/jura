<?php

namespace App\Http\Controllers;

use App\Http\Requests\Preferences\UpdatePreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PreferenceController extends Controller
{
    private const TIMEZONES = [
        'America/Bogota', 'America/Mexico_City', 'America/Lima',
        'America/Santiago', 'America/Argentina/Buenos_Aires', 'UTC',
    ];

    private const CURRENCIES = [
        ['value' => 'COP', 'label' => 'COP — Colombian peso'],
        ['value' => 'USD', 'label' => 'USD — US dollar'],
        ['value' => 'EUR', 'label' => 'EUR — Euro'],
        ['value' => 'MXN', 'label' => 'MXN — Mexican peso'],
    ];

    public function edit(): Response
    {
        return Inertia::render('Preferences/Edit', [
            'currencies' => self::CURRENCIES,
            'timezones' => self::TIMEZONES,
        ]);
    }

    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        Auth::user()->update($request->validated());

        return back()->with('success', __('preferences.saved'));
    }
}
