@props([
    'name',
    'title',
    'description' => null,
    'maxWidth' => 'lg',
])

<x-modal :name="$name" :max-width="$maxWidth">
    <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
            @if ($description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>

        <button type="button" x-on:click="show = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300">
            <x-dynamic-icon name="x-mark" class="h-5 w-5" />
            <span class="sr-only">{{ __('Close') }}</span>
        </button>
    </div>

    <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
            {{ $footer }}
        </div>
    @endisset
</x-modal>
