<?php

use App\Enums\SavingsGoalStatus;
use App\Models\SavingsGoal;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $target_amount = '';

    public string $currency = 'COP';

    public ?string $target_date = null;

    public string $icon = 'flag';

    public string $color = '#2563eb';

    public ?int $confirmingDeleteId = null;

    // Modal de aporte
    public bool $showContributionModal = false;

    public ?int $contributingGoalId = null;

    public string $contribution_amount = '';

    public string $contribution_date = '';

    #[On('open-create-goal')]
    public function create(): void
    {
        $this->authorize('create', SavingsGoal::class);
        $this->resetForm();
        $this->currency = auth()->user()->currency_default;
        $this->showModal = true;
    }

    public function edit(int $goalId): void
    {
        $goal = SavingsGoal::findOrFail($goalId);
        $this->authorize('update', $goal);

        $this->editingId = $goal->id;
        $this->name = $goal->name;
        $this->target_amount = (string) $goal->target_amount;
        $this->currency = $goal->currency;
        $this->target_date = $goal->target_date?->toDateString();
        $this->icon = $goal->icon;
        $this->color = $goal->color;

        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'target_amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'target_date' => ['nullable', 'date', 'after:today'],
            'icon' => ['required', 'string', 'max:32'],
            'color' => ['required', 'string', 'max:7'],
        ]);

        if ($this->editingId) {
            $goal = SavingsGoal::findOrFail($this->editingId);
            $this->authorize('update', $goal);
            $goal->update($data);
        } else {
            $this->authorize('create', SavingsGoal::class);
            auth()->user()->savingsGoals()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('Goal saved successfully.'));
    }

    public function confirmDelete(int $goalId): void
    {
        $this->authorize('delete', SavingsGoal::findOrFail($goalId));
        $this->confirmingDeleteId = $goalId;
    }

    public function delete(): void
    {
        $goal = SavingsGoal::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $goal);
        $goal->delete();

        $this->confirmingDeleteId = null;
        $this->dispatch('toast', type: 'success', message: __('Goal deleted.'));
    }

    public function openContribution(int $goalId): void
    {
        $this->authorize('update', SavingsGoal::findOrFail($goalId));
        $this->contributingGoalId = $goalId;
        $this->contribution_amount = '';
        $this->contribution_date = Carbon::today()->toDateString();
        $this->showContributionModal = true;
    }

    public function saveContribution(): void
    {
        $data = $this->validate([
            'contribution_amount' => ['required', 'numeric', 'gt:0'],
            'contribution_date' => ['required', 'date'],
        ]);

        $goal = SavingsGoal::findOrFail($this->contributingGoalId);
        $this->authorize('update', $goal);

        $goal->contributions()->create([
            'amount' => $data['contribution_amount'],
            'date' => $data['contribution_date'],
        ]);

        $saved = $goal->contributions()->sum('amount');
        if ($saved >= $goal->target_amount->toFloat() && $goal->status === SavingsGoalStatus::Active) {
            $goal->update(['status' => SavingsGoalStatus::Completed]);
        }

        $this->showContributionModal = false;
        $this->dispatch('toast', type: 'success', message: __('Contribution recorded.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'target_amount', 'target_date']);
        $this->currency = auth()->user()->currency_default;
        $this->icon = 'flag';
        $this->color = '#2563eb';
    }

    public function with(): array
    {
        $currencies = auth()->user()->accounts()->pluck('currency')->unique()->sort()->values();
        $currencies = $currencies->isEmpty() ? collect([auth()->user()->currency_default]) : $currencies;

        $goals = auth()->user()->savingsGoals()
            ->whereIn('status', [SavingsGoalStatus::Active, SavingsGoalStatus::Completed])
            ->orderBy('status')
            ->orderBy('target_date')
            ->get()
            ->map(function (SavingsGoal $goal) {
                $saved = Money::of($goal->contributions()->sum('amount'));
                $percentage = $saved->percentageOf($goal->target_amount);

                $suggestedMonthly = null;
                $schedule = null;

                if ($goal->target_date && $goal->status === SavingsGoalStatus::Active) {
                    $monthsRemaining = max(1, (int) ceil(Carbon::now()->floatDiffInMonths($goal->target_date, false)));
                    $remaining = $goal->target_amount->subtract($saved);
                    $suggestedMonthly = $remaining->isPositive() ? $remaining->divide($monthsRemaining) : Money::zero();

                    $totalMonths = max(1, $goal->created_at->floatDiffInMonths($goal->target_date));
                    $elapsedMonths = min($totalMonths, max(0, $goal->created_at->floatDiffInMonths(Carbon::now())));
                    $expectedPercentage = ($elapsedMonths / $totalMonths) * 100;
                    $schedule = $percentage + 2 >= $expectedPercentage ? 'ahead' : 'behind';
                }

                return [
                    'model' => $goal,
                    'saved' => $saved,
                    'percentage' => min($percentage, 100),
                    'suggestedMonthly' => $suggestedMonthly,
                    'schedule' => $schedule,
                ];
            });

        return ['goals' => $goals, 'currencies' => $currencies];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Savings goals') }}
            </h2>
            <button onclick="Livewire.dispatch('open-create-goal')" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                <x-dynamic-icon name="plus" class="h-4 w-4" />
                {{ __('New goal') }}
            </button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        @if ($goals->isEmpty())
            <x-empty-state
                icon="flag"
                :title="__('Define your first savings goal')"
                :description="__('Give it a name, a target amount, and a date, and we\'ll tell you how much you should save each month.')"
            >
                <x-slot name="action">
                    <button wire:click="create" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        <x-dynamic-icon name="plus" class="h-4 w-4" />
                        {{ __('Create my first goal') }}
                    </button>
                </x-slot>
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($goals as $row)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: {{ $row['model']->color }}1a; color: {{ $row['model']->color }};">
                                    <x-dynamic-icon :name="$row['model']->icon" class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $row['model']->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        @if ($row['model']->target_date)
                                            {{ __('Goal') }}: {{ $row['model']->target_date->translatedFormat('d M Y') }} &middot;
                                        @endif
                                        {{ $row['model']->currency }}
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

                        <div class="mt-4">
                            <div class="mb-1 flex items-baseline justify-between text-sm">
                                <span class="font-semibold text-gray-900 dark:text-white"><x-money :amount="$row['saved']" :currency="$row['model']->currency" /></span>
                                <span class="text-gray-400">{{ __('of') }} <x-money :amount="$row['model']->target_amount" :currency="$row['model']->currency" /></span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $row['percentage'] }}%"></div>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-xs">
                                <span class="text-gray-400">{{ number_format($row['percentage'], 0) }}%</span>
                                @if ($row['model']->status->value === 'completed')
                                    <span class="font-medium text-green-600 dark:text-green-400">{{ __('Goal completed!') }}</span>
                                @elseif ($row['schedule'] === 'behind')
                                    <span class="font-medium text-orange-500">{{ __('You\'re behind') }}</span>
                                @elseif ($row['schedule'] === 'ahead')
                                    <span class="font-medium text-green-600 dark:text-green-400">{{ __('You\'re on track') }}</span>
                                @endif
                            </div>

                            @if ($row['suggestedMonthly'])
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('Suggested monthly contribution') }}: <span class="font-medium text-gray-700 dark:text-gray-300"><x-money :amount="$row['suggestedMonthly']" :currency="$row['model']->currency" /></span>
                                </p>
                            @endif
                        </div>

                        @if ($row['model']->status->value !== 'completed')
                            <button wire:click="openContribution({{ $row['model']->id }})" type="button" class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-100 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-400">
                                <x-dynamic-icon name="plus" class="h-3.5 w-3.5" />
                                {{ __('Record contribution') }}
                            </button>
                        @endif
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
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $editingId ? __('Edit goal') : __('New goal') }}</h3>
                <button type="button" x-on:click="show = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <x-dynamic-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>
            <form wire:submit="save" class="space-y-4 px-6 py-5">
                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" placeholder="Comprar laptop" />
                    <x-input-error class="mt-1" :messages="$errors->get('name')" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="target_amount" :value="__('Target amount')" />
                        <x-text-input wire:model="target_amount" id="target_amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                        <x-input-error class="mt-1" :messages="$errors->get('target_amount')" />
                    </div>
                    <div>
                        <x-input-label for="goal_currency" :value="__('Currency')" />
                        <select wire:model="currency" id="goal_currency" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            @foreach ($currencies as $currencyOption)
                                <option value="{{ $currencyOption }}">{{ $currencyOption }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('currency')" />
                    </div>
                </div>
                <div>
                    <x-input-label for="target_date" :value="__('Target date (optional)')" />
                    <x-text-input wire:model="target_date" id="target_date" type="date" class="mt-1 block w-full" />
                    <x-input-error class="mt-1" :messages="$errors->get('target_date')" />
                </div>
                <div>
                    <x-input-label for="color" :value="__('Color')" />
                    <input wire:model="color" id="color" type="color" class="mt-1 block h-10 w-full rounded-lg border-gray-300 dark:border-gray-600" />
                </div>
            </form>
            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                <button type="button" x-on:click="show = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">{{ __('Cancel') }}</button>
                <button wire:click="save" type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save') }}</button>
            </div>
        </div>
    </div>

    {{-- Modal aporte --}}
    <div x-data="{ show: @entangle('showContributionModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div x-show="show" x-on:click="show = false" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>
        <div x-show="show" x-transition class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-24 sm:w-full sm:max-w-sm dark:bg-gray-800">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Record contribution') }}</h3>
                <button type="button" x-on:click="show = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <x-dynamic-icon name="x-mark" class="h-5 w-5" />
                </button>
            </div>
            <form wire:submit="saveContribution" class="space-y-4 px-6 py-5">
                <div>
                    <x-input-label for="contribution_amount" :value="__('Value')" />
                    <x-text-input wire:model="contribution_amount" id="contribution_amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                    <x-input-error class="mt-1" :messages="$errors->get('contribution_amount')" />
                </div>
                <div>
                    <x-input-label for="contribution_date" :value="__('Date')" />
                    <x-text-input wire:model="contribution_date" id="contribution_date" type="date" class="mt-1 block w-full" />
                </div>
            </form>
            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                <button type="button" x-on:click="show = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">{{ __('Cancel') }}</button>
                <button wire:click="saveContribution" type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save') }}</button>
            </div>
        </div>
    </div>

    {{-- Confirmación de borrado --}}
    <div x-data="{ show: @entangle('confirmingDeleteId').live }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
        <div x-show="show" x-on:click="$wire.confirmingDeleteId = null" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>
        <div x-show="show" x-transition class="relative mx-auto mb-6 overflow-hidden rounded-xl bg-white shadow-xl sm:mx-auto sm:mt-24 sm:w-full sm:max-w-md dark:bg-gray-800">
            <div class="px-6 py-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Delete this goal?') }}</h3>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                <button type="button" x-on:click="$wire.confirmingDeleteId = null" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">{{ __('Cancel') }}</button>
                <button wire:click="delete" type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('Delete') }}</button>
            </div>
        </div>
    </div>
</div>
