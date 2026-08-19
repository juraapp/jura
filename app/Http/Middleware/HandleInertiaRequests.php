<?php

namespace App\Http\Middleware;

use App\Services\BalanceCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'netWorth' => Inertia::defer(function () use ($request) {
                if (! $request->user()) {
                    return [];
                }

                return app(BalanceCalculator::class)
                    ->netWorthByCurrency($request->user())
                    ->map(fn ($money) => $money->toFloat())
                    ->all();
            }, 'topbar'),
            'notifications' => Inertia::defer(function () use ($request) {
                if (! $request->user()) {
                    return ['items' => [], 'unreadCount' => 0];
                }

                return [
                    'items' => $request->user()->notifications()->latest()->limit(10)->get()
                        ->map(fn ($notification) => [
                            'id' => $notification->id,
                            'message' => $notification->data['message'] ?? '',
                            'severity' => $notification->data['severity'] ?? 'warning',
                            'url' => $notification->data['url'] ?? '#',
                            'readAt' => $notification->read_at,
                            'createdAt' => $notification->created_at,
                        ]),
                    'unreadCount' => $request->user()->unreadNotifications()->count(),
                ];
            }, 'topbar'),
        ];
    }
}
