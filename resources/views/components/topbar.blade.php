<header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-gray-200 bg-white/80 px-4 backdrop-blur sm:px-6 dark:border-gray-700 dark:bg-gray-800/80">
    <button
        type="button"
        x-on:click="sidebarOpen = ! sidebarOpen"
        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden dark:text-gray-400 dark:hover:bg-gray-700"
    >
        <x-dynamic-icon name="bars-3" class="h-6 w-6" />
        <span class="sr-only">{{ __('Open menu') }}</span>
    </button>

    <livewire:net-worth-badge />

    <div class="ms-auto flex items-center gap-2">
        <button
            type="button"
            x-on:click="$dispatch('open-transaction-form', { type: 'expense' })"
            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-800"
        >
            <x-dynamic-icon name="plus" class="h-4 w-4" />
            <span class="hidden sm:inline">{{ __('New transaction') }}</span>
        </button>

        <button
            type="button"
            x-on:click="darkMode = ! darkMode"
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
        >
            <x-dynamic-icon name="sun" class="h-5 w-5" x-show="darkMode" x-cloak />
            <x-dynamic-icon name="moon" class="h-5 w-5" x-show="!darkMode" x-cloak />
            <span class="sr-only">{{ __('Toggle theme') }}</span>
        </button>

        <livewire:notification-bell />

        <div x-data="{ userMenuOpen: false }" class="relative">
            <button
                type="button"
                x-on:click="userMenuOpen = ! userMenuOpen"
                x-on:click.outside="userMenuOpen = false"
                class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700"
            >
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                    {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                </span>
                <x-dynamic-icon name="chevron-down" class="hidden h-4 w-4 text-gray-400 sm:block" />
            </button>

            <div
                x-show="userMenuOpen"
                x-transition
                x-cloak
                class="absolute right-0 z-30 mt-2 w-56 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->email }}</p>
                </div>

                <a href="{{ route('profile') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    {{ __('Profile & security') }}
                </a>
                <a href="{{ route('preferences.edit') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                    {{ __('Preferences') }}
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-start text-sm text-red-600 hover:bg-gray-100 dark:text-red-400 dark:hover:bg-gray-700">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
