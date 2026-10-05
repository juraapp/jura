<?php

use App\Enums\BudgetPeriodType;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $category_id = null;

    public string $currency = 'COP';

    public string $amount = '';

    public string $period_type = 'monthly';

    public ?int $confirmingDeleteId = null;

    #[On('open-create-budget')]
    public function create(): void
    {
        $this->authorize('create', Budget::class);
        $this->resetForm();
        $this->currency = auth()->user()->currency_default;
        $this->showModal = true;
    }

    public function edit(int $budgetId): void
    {
        $budget = Budget::findOrFail($budgetId);
        $this->authorize('update', $budget);

        $this->editingId = $budget->id;
        $this->category_id = $budget->category_id;
        $this->currency = $budget->currency;
        $this->amount = (string) $budget->amount;
        $this->period_type = $budget->period_type->value;

        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'period_type' => ['required', 'in:monthly,yearly'],
        ]);

        $periodStart = $data['period_type'] === 'yearly'
            ? Carbon::now()->startOfYear()->toDateString()
            : Carbon::now()->startOfMonth()->toDateString();

        if ($this->editingId) {
            $budget = Budget::findOrFail($this->editingId);
            $this->authorize('update', $budget);
            $budget->update($data);
        } else {
            $this->authorize('create', Budget::class);
            auth()->user()->budgets()->updateOrCreate(
                ['category_id' => $data['category_id'], 'currency' => $data['currency'], 'period_type' => $data['period_type'], 'period_start' => $periodStart],
                ['amount' => $data['amount']],
            );
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('Budget saved successfully.'));
    }

    public function confirmDelete(int $budgetId): void
    {
        $this->authorize('delete', Budget::findOrFail($budgetId));
        $this->confirmingDeleteId = $budgetId;
    }

    public function delete(): void
    {
        $budget = Budget::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $budget);
        $budget->delete();

        $this->confirmingDeleteId = null;
        $this->dispatch('toast', type: 'success', message: __('Budget deleted.'));
    }

    #[On('finances-updated')]
    public function refresh(): void
    {
        //
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'category_id', 'amount']);
        $this->currency = auth()->user()->currency_default;
        $this->period_type = 'monthly';
    }

    public function with(BudgetService $service): array
    {
        $now = Carbon::now();

        $budgets = auth()->user()->budgets()
            ->whereDate('period_start', '<=', $now)
            ->get()
            ->filter(function (Budget $budget) use ($now) {
                return $budget->period_type->value === 'yearly'
                    ? $budget->period_start->isSameYear($now)
                    : $budget->period_start->isSameMonth($now);
            })
            ->map(fn (Budget $budget) => [
                'model' => $budget,
                'progress' => $service->progress($budget),
            ]);

        $currencies = auth()->user()->accounts()->pluck('currency')->unique()->sort()->values();

        return [
            'budgets' => $budgets,
            'expenseCategories' => Category::where('type', CategoryType::Expense)->orderBy('name')->get(),
            'periodTypes' => BudgetPeriodType::cases(),
            'currencies' => $currencies->isEmpty() ? collect([auth()->user()->currency_default]) : $currencies,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Budgets') }}
            </h2>
            <button onclick="Livewire.dispatch('open-create-budget')" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                <x-dynamic-icon name="plus" class="h-4 w-4" />
                {{ __('New budget') }}
            </button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        @if ($budgets->isEmpty())
            <x-empty-state
                icon="chart-pie"
                :title="__('You don\'t have any budgets yet')"
                :description="__('Set a spending limit per category for this month and we\'ll warn you as you approach it.')"
            >
                <x-slot name="action">
                    <button wire:click="create" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        <x-dynamic-icon name="plus" class="h-4 w-4" />
                        {{ __('Create my first budget') }}
                    </button>
                </x-slot>
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($budgets as $row)
                    @php
                        $progress = $row['progress'];
                        $barColor = match ($progress['status']) {
                            'exceeded' => 'bg-red-500',
                            'near_limit' => 'bg-orange-400',
                            default => 'bg-green-500',
                        };
                    @endphp
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <x-category-icon :category="$row['model']->category" />
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $row['model']->category->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['model']->period_type->label() }} &middot; {{ $row['model']->currency }}</p>
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

                        <div class="mt-4">
                            <div class="mb-1 flex items-baseline justify-between text-sm">
                                <span class="font-semibold text-gray-900 dark:text-white"><x-money :amount="$progress['spent']" :currency="$row['model']->currency" /></span>
                                <span class="text-gray-400">{{ __('of') }} <x-money :amount="$row['model']->amount" :currency="$row['model']->currency" /></span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                <div class="h-full {{ $barColor }} transition-all" style="width: {{ min($progress['percentage'], 100) }}%"></div>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-xs">
                                <span class="text-gray-400">{{ number_format($progress['percentage'], 0) }}% {{ __('used') }}</span>
                                @if ($progress['status'] === 'exceeded')
                                    <span class="font-medium text-red-600 dark:text-red-400">{{ __('Budget exceeded') }}</span>
                                @elseif ($progress['status'] === 'near_limit')
                                    <span class="font-medium text-orange-500">{{ __('Close to the limit') }}</span>
                                @else
                                    <span class="text-gray-400"><x-money :amount="$progress['remaining']" :currency="$row['model']->currency" /> {{ __('available') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div x-show="show" x-on:click="show = false" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>

        <div x-show="show" x-transition class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-16 sm:w-full sm:max-w-md dark:bg-gray-800">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $editingId ? __('Edit budget') : __('New budget') }}
                </h3>
                <button type="button" x-on:click="show = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <x-dynamic-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>

            <form wire:submit="save" class="space-y-4 px-6 py-5">
                <div>
                    <x-input-label for="category_id" :value="__('Category')" />
                    <select wire:model="category_id" id="category_id" @disabled($editingId) class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="">{{ __('Select a category') }}</option>
                        @foreach ($expenseCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('category_id')" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="amount" :value="__('Limit amount')" />
                        <x-text-input wire:model="amount" id="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <x-input-error class="mt-1" :messages="$errors->get('amount')" />
                    </div>
                    <div>
                        <x-input-label for="currency" :value="__('Currency')" />
                        <select wire:model="currency" id="currency" @disabled($editingId) class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            @foreach ($currencies as $currencyOption)
                                <option value="{{ $currencyOption }}">{{ $currencyOption }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('currency')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="period_type" :value="__('Period')" />
                    <select wire:model="period_type" id="period_type" @disabled($editingId) class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        @foreach ($periodTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">{{ __('Applies to the current period (this month or this year).') }}</p>
                </div>
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
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Delete this budget?') }}</h3>
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
