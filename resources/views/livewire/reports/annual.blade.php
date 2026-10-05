<?php

use App\Services\ReportService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $year;

    public function mount(): void
    {
        $this->year = now()->year;
    }

    public function previousYear(): void
    {
        $this->year--;
    }

    public function nextYear(): void
    {
        $this->year++;
    }

    public function with(ReportService $reports): array
    {
        return [
            'summary' => $reports->annualSummary(auth()->user(), $this->year),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Annual report') }}
            </h2>
            <div class="flex items-center gap-2">
                <button wire:click="previousYear" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                    <x-dynamic-icon name="chevron-right" class="h-4 w-4 -scale-x-100" />
                </button>
                <span class="min-w-[4rem] text-center text-sm font-medium text-gray-700 dark:text-gray-300">{{ $year }}</span>
                <button wire:click="nextYear" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                    <x-dynamic-icon name="chevron-right" class="h-4 w-4" />
                </button>
                <a href="{{ route('reports.annual.pdf', ['year' => $year]) }}" target="_blank" class="ms-2 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    <x-dynamic-icon name="arrow-down-tray" class="h-4 w-4" />
                    {{ __('Export PDF') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        @foreach ($summary as $currency => $currencySummary)
            <div class="space-y-6">
                @if ($summary->count() > 1)
                    <h3 class="border-t border-gray-200 pt-6 text-sm font-semibold uppercase tracking-wide text-gray-400 dark:border-gray-700">{{ $currency }}</h3>
                @endif

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <x-stat-card :label="__('Total income')" icon="arrow-up-circle" icon-color="text-green-600 dark:text-green-400" icon-bg="bg-green-50 dark:bg-green-500/10">
                        <x-money :amount="$currencySummary['income']" :currency="$currency" />
                    </x-stat-card>
                    <x-stat-card :label="__('Total expenses')" icon="arrow-down-circle" icon-color="text-red-600 dark:text-red-400" icon-bg="bg-red-50 dark:bg-red-500/10">
                        <x-money :amount="$currencySummary['expense']" :currency="$currency" />
                    </x-stat-card>
                    <x-stat-card :label="__('Total savings')" icon="chart-pie">
                        <x-money :amount="$currencySummary['savings']" :currency="$currency" />
                        <x-slot name="footer">{{ number_format($currencySummary['savingsRate'], 1) }}% {{ __('annual savings rate') }}</x-slot>
                    </x-stat-card>
                    <x-stat-card :label="__('Monthly average')" icon="calendar">
                        <x-money :amount="$currencySummary['avgMonthlyExpense']" :currency="$currency" />
                        <x-slot name="footer">{{ __('of spending') }}</x-slot>
                    </x-stat-card>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    @if ($currencySummary['bestIncomeMonth'])
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Month with the highest income') }}</p>
                            <p class="mt-1 text-lg font-semibold capitalize text-gray-900 dark:text-white">{{ $currencySummary['bestIncomeMonth']['label'] }}</p>
                            <p class="text-sm text-gray-500"><x-money :amount="$currencySummary['bestIncomeMonth']['income']" :currency="$currency" /></p>
                        </div>
                    @endif
                    @if ($currencySummary['worstExpenseMonth'])
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Month with the highest expenses') }}</p>
                            <p class="mt-1 text-lg font-semibold capitalize text-gray-900 dark:text-white">{{ $currencySummary['worstExpenseMonth']['label'] }}</p>
                            <p class="text-sm text-gray-500"><x-money :amount="$currencySummary['worstExpenseMonth']['expense']" :currency="$currency" /></p>
                        </div>
                    @endif
                    @if ($currencySummary['topCategory'])
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Category where you spent the most') }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $currencySummary['topCategory']['category']?->name }}</p>
                            <p class="text-sm text-gray-500">{{ number_format($currencySummary['topCategory']['percentage'], 1) }}% {{ __('of the total') }}</p>
                        </div>
                    @endif
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Month') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Income') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Expenses') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Savings') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($currencySummary['months'] as $row)
                                <tr>
                                    <td class="px-4 py-2.5 text-sm capitalize text-gray-700 dark:text-gray-300">{{ $row['label'] }}</td>
                                    <td class="px-4 py-2.5 text-right text-sm tabular-nums text-green-600 dark:text-green-400"><x-money :amount="$row['income']" :currency="$currency" /></td>
                                    <td class="px-4 py-2.5 text-right text-sm tabular-nums text-red-600 dark:text-red-400"><x-money :amount="$row['expense']" :currency="$currency" /></td>
                                    <td class="px-4 py-2.5 text-right text-sm font-medium tabular-nums text-gray-900 dark:text-white"><x-money :amount="$row['savings']" :currency="$currency" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</div>
