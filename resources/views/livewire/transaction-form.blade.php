<div
    x-data="{ show: @entangle('show') }"
    x-show="show"
    x-cloak
    x-on:keydown.escape.window="show = false"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
>
    <div
        x-show="show"
        x-on:click="show = false"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-900/60"
    ></div>

    <div
        x-show="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
        class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-16 sm:w-full sm:max-w-lg dark:bg-gray-800"
    >
        <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $editingId ? __('Edit transaction') : __('New transaction') }}
            </h3>
            <button type="button" x-on:click="show = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                <x-dynamic-icon name="x-mark" class="h-5 w-5" />
            </button>
        </div>

        @unless ($editingId)
            <div class="flex gap-1 border-b border-gray-200 px-6 pt-4 dark:border-gray-700">
                @foreach (['expense' => 'Gasto', 'income' => 'Ingreso', 'transfer' => 'Transferencia'] as $tab => $label)
                    <button
                        type="button"
                        wire:click="$set('activeTab', '{{ $tab }}')"
                        @class([
                            'rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition',
                            'border-primary-600 text-primary-600 dark:text-primary-400' => $activeTab === $tab,
                            'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $activeTab !== $tab,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endunless

        <form wire:submit="save" class="max-h-[65vh] space-y-4 overflow-y-auto px-6 py-5">
            @if ($activeTab === 'transfer')
                <div>
                    <x-input-label for="from_account_id" :value="__('Source account')" />
                    <select wire:model="from_account_id" id="from_account_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="">{{ __('Select an account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('from_account_id')" />
                </div>

                <div>
                    <x-input-label for="to_account_id" :value="__('Destination account')" />
                    <select wire:model="to_account_id" id="to_account_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="">{{ __('Select an account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('to_account_id')" />
                </div>
            @else
                <div>
                    <x-input-label for="account_id" :value="__('Account')" />
                    <select wire:model="account_id" id="account_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="">{{ __('Select an account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('account_id')" />
                </div>

                <div>
                    <x-input-label for="category_id" :value="__('Category')" />
                    <select wire:model="category_id" id="category_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="">{{ __('Select a category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('category_id')" />
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="amount" :value="__('Value')" />
                    <x-text-input wire:model="amount" id="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                    <x-input-error class="mt-1" :messages="$errors->get('amount')" />
                </div>

                <div>
                    <x-input-label for="date" :value="__('Date')" />
                    <x-text-input wire:model="date" id="date" type="date" class="mt-1 block w-full" />
                    <x-input-error class="mt-1" :messages="$errors->get('date')" />
                </div>
            </div>

            @if ($activeTab === 'expense')
                <div>
                    <x-input-label for="payment_method" :value="__('Payment method')" />
                    <select wire:model="payment_method" id="payment_method" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="">{{ __('Not specified') }}</option>
                        @foreach (\App\Enums\PaymentMethod::cases() as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <x-input-label for="description" :value="__('Description')" />
                <x-text-input wire:model="description" id="description" type="text" class="mt-1 block w-full" />
                <x-input-error class="mt-1" :messages="$errors->get('description')" />
            </div>

            <div>
                <x-input-label for="notes" :value="__('Notes')" />
                <textarea wire:model="notes" id="notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"></textarea>
                <x-input-error class="mt-1" :messages="$errors->get('notes')" />
            </div>
        </form>

        <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
            <button type="button" x-on:click="show = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </button>
            <button wire:click="save" wire:loading.attr="disabled" type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50">
                {{ __('Save') }}
            </button>
        </div>
    </div>
</div>
