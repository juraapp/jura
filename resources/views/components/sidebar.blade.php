@php
    $sections = [
        [
            'items' => [
                ['label' => __('Dashboard'), 'route' => 'dashboard', 'icon' => 'home'],
            ],
        ],
        [
            'title' => __('Finances'),
            'items' => [
                ['label' => __('Transactions'), 'route' => 'transactions.index', 'icon' => 'list-bullet'],
                ['label' => __('Import CSV'), 'route' => 'transactions.import', 'icon' => 'arrow-up-tray'],
                ['label' => __('Income'), 'route' => 'incomes.index', 'icon' => 'arrow-up-circle'],
                ['label' => __('Expenses'), 'route' => 'expenses.index', 'icon' => 'arrow-down-circle'],
                ['label' => __('Transfers'), 'route' => 'transfers.index', 'icon' => 'arrows-right-left'],
            ],
        ],
        [
            'title' => __('Accounts'),
            'items' => [
                ['label' => __('Banks & wallets'), 'route' => 'accounts.index', 'icon' => 'wallet'],
            ],
        ],
        [
            'title' => __('Planning'),
            'items' => [
                ['label' => __('Budgets'), 'route' => 'budgets.index', 'icon' => 'chart-pie'],
                ['label' => __('Savings goals'), 'route' => 'savings-goals.index', 'icon' => 'flag'],
                ['label' => __('Recurring expenses'), 'route' => 'recurring-transactions.index', 'icon' => 'arrow-path'],
            ],
        ],
        [
            'title' => __('Reports'),
            'items' => [
                ['label' => __('Monthly'), 'route' => 'reports.monthly', 'icon' => 'document-chart-bar'],
                ['label' => __('Annual'), 'route' => 'reports.annual', 'icon' => 'document-chart-bar'],
                ['label' => __('Custom'), 'route' => 'reports.custom', 'icon' => 'document-chart-bar'],
            ],
        ],
        [
            'title' => __('Settings'),
            'items' => [
                ['label' => __('Profile & security'), 'route' => 'profile', 'icon' => 'user-circle'],
                ['label' => __('Categories'), 'route' => 'categories.index', 'icon' => 'tag'],
                ['label' => __('Preferences'), 'route' => 'preferences.edit', 'icon' => 'adjustments'],
                ['label' => __('Export my data'), 'route' => 'data-export.index', 'icon' => 'arrow-down-tray'],
            ],
        ],
    ];
@endphp

<aside
    x-cloak
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 transform border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:static lg:translate-x-0 dark:border-gray-700 dark:bg-gray-800"
>
    <div class="flex h-16 items-center gap-2 border-b border-gray-200 px-5 dark:border-gray-700">
        <x-application-logo class="h-8 w-8 fill-current text-primary-600 dark:text-primary-400" />
        <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ config('app.name') }}</span>
    </div>

    <nav class="h-[calc(100%-4rem)] space-y-6 overflow-y-auto px-3 py-5">
        @foreach ($sections as $section)
            <div>
                @if (! empty($section['title']))
                    <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        {{ $section['title'] }}
                    </p>
                @endif

                <ul class="space-y-1">
                    @foreach ($section['items'] as $item)
                        @php $active = request()->routeIs($item['route']); @endphp
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                @class([
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                    'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $active,
                                    'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700/50' => ! $active,
                                ])
                            >
                                <x-dynamic-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
</aside>

<div
    x-cloak
    x-show="sidebarOpen"
    x-on:click="sidebarOpen = false"
    x-transition.opacity
    class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
></div>
