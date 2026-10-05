<?php

use App\Enums\CategoryType;
use App\Enums\RecurringFrequency;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $type = 'expense';

    public ?int $account_id = null;

    public ?int $category_id = null;

    public string $amount = '';

    public ?string $description = null;

    public string $frequency = 'monthly';

    public string $next_due_date = '';

    public ?string $end_date = null;

    public ?int $confirmingDeleteId = null;

    #[On('open-create-recurring')]
    public function create(): void
    {
        $this->authorize('create', RecurringTransaction::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $recurring = RecurringTransaction::findOrFail($id);
        $this->authorize('update', $recurring);

        $this->editingId = $recurring->id;
        $this->type = $recurring->type->value;
        $this->account_id = $recurring->account_id;
        $this->category_id = $recurring->category_id;
        $this->amount = (string) $recurring->amount;
        $this->description = $recurring->description;
        $this->frequency = $recurring->frequency->value;
        $this->next_due_date = $recurring->next_due_date->toDateString();
        $this->end_date = $recurring->end_date?->toDateString();

        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'type' => ['required', 'in:income,expense'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'in:weekly,biweekly,monthly,yearly'],
            'next_due_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:next_due_date'],
        ]);

        if ($this->editingId) {
            $recurring = RecurringTransaction::findOrFail($this->editingId);
            $this->authorize('update', $recurring);
            $recurring->update($data);
        } else {
            $this->authorize('create', RecurringTransaction::class);
            auth()->user()->recurringTransactions()->create([...$data, 'is_active' => true]);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('Recurring transaction saved.'));
    }

    public function toggleActive(int $id): void
    {
        $recurring = RecurringTransaction::findOrFail($id);
        $this->authorize('update', $recurring);
        $recurring->update(['is_active' => ! $recurring->is_active]);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('delete', RecurringTransaction::findOrFail($id));
        $this->confirmingDeleteId = $id;
    }

    public function delete(): void
    {
        $recurring = RecurringTransaction::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $recurring);
        $recurring->delete();

        $this->confirmingDeleteId = null;
        $this->dispatch('toast', type: 'success', message: __('Recurring transaction deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'account_id', 'category_id', 'amount', 'description', 'end_date']);
        $this->type = 'expense';
        $this->frequency = 'monthly';
        $this->next_due_date = Carbon::today()->toDateString();
    }

    public function with(): array
    {
        return [
            'recurrences' => auth()->user()->recurringTransactions()->with(['account' => fn ($query) => $query->withTrashed(), 'category'])->orderBy('next_due_date')->get(),
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::where('type', $this->type)->orderBy('name')->get(),
            'frequencies' => RecurringFrequency::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Recurring expenses') }}
            </h2>
            <button onclick="Livewire.dispatch('open-create-recurring')" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                <x-dynamic-icon name="plus" class="h-4 w-4" />
                {{ __('New recurring transaction') }}
            </button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        @if ($recurrences->isEmpty())
            <x-empty-state
                icon="arrow-path"
                :title="__('Record your recurring payments')"
                :description="__('Netflix, rent, utilities... record them once and we\'ll generate them automatically when they\'re due.')"
            >
                <x-slot name="action">
                    <button wire:click="create" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        <x-dynamic-icon name="plus" class="h-4 w-4" />
                        {{ __('Create my first recurring transaction') }}
                    </button>
                </x-slot>
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Description') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Frequency') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Next payment') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Value') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($recurrences as $recurring)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 {{ ! $recurring->is_active ? 'opacity-50' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <x-category-icon :category="$recurring->category" size="h-7 w-7" />
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $recurring->description ?: $recurring->category->name }}</p>
                                            <p class="text-xs text-gray-400">{{ $recurring->account->name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $recurring->frequency->label() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $recurring->next_due_date->translatedFormat('d M Y') }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums {{ $recurring->type->value === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    <x-money :amount="$recurring->amount" :currency="$recurring->account->currency" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button wire:click="toggleActive({{ $recurring->id }})" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700" title="{{ $recurring->is_active ? __('Pause') : __('Reactivate') }}">
                                            <x-dynamic-icon :name="$recurring->is_active ? 'check-circle' : 'arrow-path'" class="h-4 w-4" />
                                        </button>
                                        <button wire:click="edit({{ $recurring->id }})" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">
                                            <x-dynamic-icon name="pencil" class="h-4 w-4" />
                                        </button>
                                        <button wire:click="confirmDelete({{ $recurring->id }})" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10">
                                            <x-dynamic-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div x-show="show" x-on:click="show = false" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>
        <div x-show="show" x-transition class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-10 sm:w-full sm:max-w-lg dark:bg-gray-800">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $editingId ? __('Edit recurring transaction') : __('New recurring transaction') }}</h3>
                <button type="button" x-on:click="show = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <x-dynamic-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>

            <form wire:submit="save" class="max-h-[65vh] space-y-4 overflow-y-auto px-6 py-5">
                <div class="flex gap-2">
                    @foreach (['expense' => 'Gasto', 'income' => 'Ingreso'] as $value => $label)
                        <button type="button" wire:click="$set('type', '{{ $value }}')" @class([
                            'flex-1 rounded-lg border px-3 py-2 text-sm font-medium',
                            'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $type === $value,
                            'border-gray-300 text-gray-600 dark:border-gray-600 dark:text-gray-300' => $type !== $value,
                        ])>{{ __($label) }}</button>
                    @endforeach
                </div>

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

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="amount" :value="__('Value')" />
                        <x-text-input wire:model="amount" id="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <x-input-error class="mt-1" :messages="$errors->get('amount')" />
                    </div>
                    <div>
                        <x-input-label for="frequency" :value="__('Frequency')" />
                        <select wire:model="frequency" id="frequency" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            @foreach ($frequencies as $freq)
                                <option value="{{ $freq->value }}">{{ $freq->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="next_due_date" :value="__('Next payment')" />
                        <x-text-input wire:model="next_due_date" id="next_due_date" type="date" class="mt-1 block w-full" />
                        <x-input-error class="mt-1" :messages="$errors->get('next_due_date')" />
                    </div>
                    <div>
                        <x-input-label for="end_date" :value="__('Ends (optional)')" />
                        <x-text-input wire:model="end_date" id="end_date" type="date" class="mt-1 block w-full" />
                    </div>
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <x-text-input wire:model="description" id="description" type="text" class="mt-1 block w-full" placeholder="Netflix, Arriendo..." />
                </div>
            </form>

            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                <button type="button" x-on:click="show = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">{{ __('Cancel') }}</button>
                <button wire:click="save" type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save') }}</button>
            </div>
        </div>
    </div>

    {{-- Confirmación de borrado --}}
    <div x-data="{ show: @entangle('confirmingDeleteId').live }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div x-show="show" x-on:click="$wire.confirmingDeleteId = null" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>
        <div x-show="show" x-transition class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-24 sm:w-full sm:max-w-md dark:bg-gray-800">
            <div class="px-6 py-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Delete this recurring transaction?') }}</h3>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                <button type="button" x-on:click="$wire.confirmingDeleteId = null" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">{{ __('Cancel') }}</button>
                <button wire:click="delete" type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('Delete') }}</button>
            </div>
        </div>
    </div>
</div>
