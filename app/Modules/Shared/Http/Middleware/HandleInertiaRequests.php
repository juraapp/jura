<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Contracts\UpcomingPlans;
use App\Modules\Shared\Support\UiMessages;
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
            'i18n' => [
                'locale' => app()->getLocale(),
                'messages' => UiMessages::for(app()->getLocale()),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'today' => fn () => $request->user()?->today()->toDateString(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'undo' => fn () => $request->session()->get('undo'),
            ],
            'netWorth' => Inertia::defer(function () use ($request) {
                if (! $request->user()) {
                    return [];
                }

                return app(Balances::class)
                    ->netWorthByCurrency($request->user())
                    ->map(fn ($money) => $money->toFloat())
                    ->all();
            }, 'topbar'),
            'moneyMap' => Inertia::defer(function () use ($request) {
                if (! $request->user()) {
                    return [];
                }

                return array_values(array_map(fn (array $row) => [
                    'currency' => $row['currency'],
                    'inAccounts' => $row['inAccounts']->toFloat(),
                    'inBuckets' => $row['inBuckets']->toFloat(),
                    'readyToAssign' => $row['readyToAssign']->toFloat(),
                    'overAssigned' => $row['overAssigned'],
                ], app(MoneyMap::class)->summary($request->user())));
            }, 'topbar'),
            'reviewCount' => Inertia::defer(fn () => $request->user() ? app(UpcomingPlans::class)->needsReviewCount($request->user()) : 0, 'topbar'),
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
