<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $this->pageTitle() }}
        </h2>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        {{-- Filtros --}}
        <div class="mb-4 grid grid-cols-2 gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="col-span-2 sm:col-span-1 lg:col-span-2">
                <input wire:model.live.debounce.400ms="search" type="text" placeholder="{{ __('Search description...') }}" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" />
            </div>

            <select wire:model.live="accountFilter" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                <option value="">{{ __('All accounts') }}</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="categoryFilter" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>

            <input wire:model.live="dateFrom" type="date" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" />
            <input wire:model.live="dateTo" type="date" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" />
        </div>

        @if ($transactions->isEmpty())
            <x-empty-state
                icon="list-bullet"
                :title="__('There are no transactions for these filters')"
                :description="__('Record your first transaction or adjust the search filters.')"
            >
                <x-slot name="action">
                    <button type="button" x-on:click="$dispatch('open-transaction-form', { type: 'expense' })" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        <x-dynamic-icon name="plus" class="h-4 w-4" />
                        {{ __('New transaction') }}
                    </button>
                </x-slot>
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Description') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Account') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Value') }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($transactions as $transaction)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $transaction->date->translatedFormat('d M Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            @if ($transaction->category)
                                                <x-category-icon :category="$transaction->category" size="h-7 w-7" />
                                            @else
                                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                                                    <x-dynamic-icon name="arrows-right-left" class="h-3.5 w-3.5" />
                                                </span>
                                            @endif
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $transaction->description ?: ($transaction->category->name ?? __('Transfer')) }}
                                                </p>
                                                <p class="text-xs text-gray-400">
                                                    {{ $transaction->category->name ?? __('Transfer between accounts') }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $transaction->account->name }}
                                        @if ($this->typeFilter === 'transfer' && $transferDestinations->has($transaction->transfer_group_id))
                                            <span class="text-gray-400">&rarr; {{ $transferDestinations[$transaction->transfer_group_id]->account->name }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold tabular-nums
                                        {{ $transaction->type->value === 'income' ? 'text-green-600 dark:text-green-400' : ($transaction->type->value === 'expense' ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400') }}">
                                        @if ($transaction->type->value === 'income') + @elseif ($transaction->type->value === 'expense') &minus; @endif
                                        <x-money :amount="$transaction->amount" :currency="$transaction->account->currency" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button wire:click="edit({{ $transaction->id }})" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">
                                                <x-dynamic-icon name="pencil" class="h-4 w-4" />
                                            </button>
                                            <button wire:click="confirmDelete({{ $transaction->id }})" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10">
                                                <x-dynamic-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>

    {{-- Confirmación de borrado --}}
    <div x-data="{ show: @entangle('confirmingDeleteId').live }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div x-show="show" x-on:click="$wire.confirmingDeleteId = null" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>

        <div x-show="show" x-transition class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-24 sm:w-full sm:max-w-md dark:bg-gray-800">
            <div class="px-6 py-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Delete this transaction?') }}</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('If it\'s a transfer, both transactions (source and destination) will be deleted. This action cannot be undone.') }}
                </p>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                <button type="button" x-on:click="$wire.confirmingDeleteId = null" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                    {{ __('Cancel') }}
                </button>
                <button wire:click="delete" type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    {{ __('Delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
