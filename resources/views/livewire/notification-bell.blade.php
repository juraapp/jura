<div x-data="{ open: @entangle('open') }" class="relative">
    <button type="button" x-on:click="open = ! open" x-on:click.outside="open = false" class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
        <x-dynamic-icon name="bell" class="h-5 w-5" />
        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-semibold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
        <span class="sr-only">{{ __('Notifications') }}</span>
    </button>

    <div
        x-show="open"
        x-transition
        x-cloak
        class="absolute right-0 z-30 mt-2 w-80 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
            <p class="shrink-0 text-sm font-semibold text-gray-900 dark:text-white">{{ __('Notifications') }}</p>
            @if ($unreadCount > 0)
                <button wire:click="markAllAsRead" class="shrink-0 whitespace-nowrap text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                    {{ __('Mark all') }}
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($notifications as $notification)
                @php
                    $severity = $notification->data['severity'] ?? 'warning';
                    $iconColor = match ($severity) {
                        'critical' => 'text-red-500 bg-red-50 dark:bg-red-500/10',
                        default => 'text-orange-500 bg-orange-50 dark:bg-orange-500/10',
                    };
                @endphp
                <a
                    href="{{ $notification->data['url'] ?? '#' }}"
                    wire:navigate
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="flex gap-3 border-b border-gray-50 px-4 py-3 last:border-0 hover:bg-gray-50 dark:border-gray-700/50 dark:hover:bg-gray-700/40 {{ $notification->read_at ? 'opacity-60' : '' }}"
                >
                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $iconColor }}">
                        <x-dynamic-icon name="exclamation-triangle" class="h-3.5 w-3.5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            {{ isset($notification->data['message_key'])
                                ? __($notification->data['message_key'], $notification->data['message_params'] ?? [])
                                : ($notification->data['message'] ?? '') }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-gray-400">{{ __('You have no notifications.') }}</p>
            @endforelse
        </div>
    </div>
</div>
