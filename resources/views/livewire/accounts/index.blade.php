<?php

use App\Enums\AccountType;
use App\Models\Account;
use App\Services\BalanceCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'bank';

    public ?string $institution = null;

    public string $initial_balance = '0';

    public string $currency = 'COP';

    public string $color = '#2563eb';

    public ?string $masked_number = null;

    public bool $is_active = true;

    public ?string $notes = null;

    public ?int $confirmingDeleteId = null;

    #[On('open-create-account')]
    public function create(): void
    {
        $this->authorize('create', Account::class);
        $this->resetForm();
        $this->currency = Auth::user()->currency_default;
        $this->showModal = true;
    }

    public function edit(int $accountId): void
    {
        $account = Account::findOrFail($accountId);
        $this->authorize('update', $account);

        $this->editingId = $account->id;
        $this->name = $account->name;
        $this->type = $account->type->value;
        $this->institution = $account->institution;
        $this->initial_balance = (string) $account->initial_balance;
        $this->currency = $account->currency;
        $this->color = $account->color;
        $this->masked_number = $account->masked_number;
        $this->is_active = $account->is_active;
        $this->notes = $account->notes;

        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:'.implode(',', array_column(AccountType::cases(), 'value'))],
            'institution' => ['nullable', 'string', 'max:255'],
            'initial_balance' => ['required', 'numeric'],
            'currency' => ['required', 'string', 'size:3'],
            'color' => ['required', 'string', 'max:7'],
            'masked_number' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($this->editingId) {
            $account = Account::findOrFail($this->editingId);
            $this->authorize('update', $account);
            $account->update($data);
        } else {
            $this->authorize('create', Account::class);
            Auth::user()->accounts()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('finances-updated');
        $this->dispatch('toast', type: 'success', message: __('Account saved successfully.'));
    }

    public function confirmDelete(int $accountId): void
    {
        $this->authorize('delete', Account::findOrFail($accountId));
        $this->confirmingDeleteId = $accountId;
    }

    public function delete(): void
    {
        $account = Account::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $account);

        if ($account->transactions()->exists() || $account->recurringTransactions()->exists()) {
            $account->update(['is_active' => false]);
            $this->dispatch('toast', type: 'warning', message: __('The account has associated transactions or recurring items, so it was archived instead of deleted.'));
        } else {
            $account->delete();
            $this->dispatch('toast', type: 'success', message: __('Account deleted.'));
        }

        $this->confirmingDeleteId = null;
        $this->dispatch('finances-updated');
    }

    #[On('finances-updated')]
    public function refresh(): void
    {
        // Livewire re-render ya recalcula los saldos en el próximo ciclo.
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'institution', 'masked_number', 'notes']);
        $this->type = 'bank';
        $this->initial_balance = '0';
        $this->color = '#2563eb';
        $this->is_active = true;
    }

    public function with(): array
    {
        $calculator = app(BalanceCalculator::class);

        return [
            'accounts' => Account::orderBy('is_active', 'desc')->orderBy('name')->get()
                ->map(fn (Account $account) => [
                    'model' => $account,
                    'balance' => $calculator->accountBalance($account),
                ]),
            'accountTypes' => AccountType::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Banks & wallets') }}
            </h2>
            <button onclick="Livewire.dispatch('open-create-account')" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                <x-dynamic-icon name="plus" class="h-4 w-4" />
                {{ __('New account') }}
            </button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if ($accounts->isEmpty())
            <x-empty-state
                icon="wallet"
                :title="__('You don\'t have any accounts yet')"
                :description="__('Add your banks, digital wallets, or cash to start recording transactions.')"
            >
                <x-slot name="action">
                    <button wire:click="create" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        <x-dynamic-icon name="plus" class="h-4 w-4" />
                        {{ __('Create my first account') }}
                    </button>
                </x-slot>
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($accounts as $row)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 {{ ! $row['model']->is_active ? 'opacity-60' : '' }}">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: {{ $row['model']->color }}1a; color: {{ $row['model']->color }};">
                                    <x-dynamic-icon :name="$row['model']->type->icon()" class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $row['model']->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['model']->type->label() }}
                                        @if ($row['model']->masked_number) &middot; {{ $row['model']->masked_number }} @endif
                                        @unless ($row['model']->is_active) &middot; {{ __('Archived') }} @endunless
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                <button wire:click="edit({{ $row['model']->id }})" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">
                                    <x-dynamic-icon name="pencil" class="h-4 w-4" />
                                </button>
                                <button wire:click="confirmDelete({{ $row['model']->id }})" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10">
                                    <x-dynamic-icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <p class="mt-4 text-2xl font-semibold text-gray-900 dark:text-white">
                            <x-money :amount="$row['balance']" :currency="$row['model']->currency" />
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div x-show="show" x-on:click="show = false" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>

        <div x-show="show" x-transition class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-16 sm:w-full sm:max-w-lg dark:bg-gray-800">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $editingId ? __('Edit account') : __('New account') }}
                </h3>
                <button type="button" x-on:click="show = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <x-dynamic-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>

            <form wire:submit="save" class="max-h-[65vh] space-y-4 overflow-y-auto px-6 py-5">
                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" placeholder="Bancolombia Ahorros" />
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="type" :value="__('Type')" />
                        <select wire:model="type" id="type" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            @foreach ($accountTypes as $accountType)
                                <option value="{{ $accountType->value }}">{{ $accountType->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="currency" :value="__('Currency')" />
                        <x-text-input wire:model="currency" id="currency" type="text" maxlength="3" class="mt-1 block w-full uppercase" />
                    </div>
                </div>

                <div>
                    <x-input-label for="institution" :value="__('Bank or institution')" />
                    <x-text-input wire:model="institution" id="institution" type="text" class="mt-1 block w-full" placeholder="Bancolombia, Nu, Nequi..." />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="initial_balance" :value="__('Initial balance')" />
                        <x-text-input wire:model="initial_balance" id="initial_balance" type="number" step="0.01" class="mt-1 block w-full" />
                        <x-input-error class="mt-1" :messages="$errors->get('initial_balance')" />
                    </div>

                    <div>
                        <x-input-label for="color" :value="__('Color')" />
                        <input wire:model="color" id="color" type="color" class="mt-1 block h-10 w-full rounded-lg border-gray-300 dark:border-gray-600" />
                    </div>
                </div>

                <div>
                    <x-input-label for="masked_number" :value="__('Number (optional, partially hidden)')" />
                    <x-text-input wire:model="masked_number" id="masked_number" type="text" class="mt-1 block w-full" placeholder="****1234" />
                </div>

                <div>
                    <x-input-label for="notes" :value="__('Notes')" />
                    <textarea wire:model="notes" id="notes" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"></textarea>
                </div>

                <label class="flex items-center gap-2">
                    <input wire:model="is_active" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600">
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Active account') }}</span>
                </label>
            </form>

            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                <button type="button" x-on:click="show = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                    {{ __('Cancel') }}
                </button>
                <button wire:click="save" type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    {{ __('Save') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Confirmación de borrado --}}
    <div x-data="{ show: @entangle('confirmingDeleteId').live }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div x-show="show" x-on:click="$wire.confirmingDeleteId = null" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>

        <div x-show="show" x-transition class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-24 sm:w-full sm:max-w-md dark:bg-gray-800">
            <div class="px-6 py-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Delete this account?') }}</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('If it has recorded transactions, it will be archived instead of deleted to preserve your history.') }}
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
