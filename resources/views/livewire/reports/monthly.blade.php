<?php

use App\Services\InsightService;
use App\Services\ReportService;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $year;

    public int $month;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonthNoOverflow();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonthNoOverflow();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function with(ReportService $reports, InsightService $insights): array
    {
        $user = auth()->user();
        $summary = $reports->monthlySummary($user, $this->year, $this->month);

        $fixedExpenseByCurrency = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereYear('transactions.date', $this->year)->whereMonth('transactions.date', $this->month)
            ->where('transactions.is_recurring_generated', true)
            ->selectRaw('accounts.currency as currency, SUM(transactions.amount) as total')
            ->groupBy('accounts.currency')
            ->get()
            ->keyBy('currency');

        $fixedExpense = $summary->mapWithKeys(fn ($currencySummary, $currency) => [
            $currency => \App\Support\Money::of($fixedExpenseByCurrency->get($currency)->total ?? 0),
        ]);

        return [
            'summary' => $summary,
            'highlights' => $summary->map(
                fn (array $currencySummary) => $insights->monthlyHighlights($currencySummary, $user, $this->year, $this->month)
            ),
            'expenseByCategory' => $reports->expenseByCategory($user, $this->year, $this->month),
            'fixedExpense' => $fixedExpense,
            'variableExpense' => $summary->mapWithKeys(fn ($currencySummary, $currency) => [
                $currency => $currencySummary['expense']->subtract($fixedExpense->get($currency)),
            ]),
            'periodLabel' => Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Monthly report') }}
            </h2>
            <div class="flex items-center gap-2">
                <button wire:click="previousMonth" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                    <x-dynamic-icon name="chevron-right" class="h-4 w-4 -scale-x-100" />
                </button>
                <span class="min-w-[9rem] text-center text-sm font-medium capitalize text-gray-700 dark:text-gray-300">{{ $periodLabel }}</span>
                <button wire:click="nextMonth" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                    <x-dynamic-icon name="chevron-right" class="h-4 w-4" />
                </button>
                <a href="{{ route('reports.monthly.pdf', ['year' => $year, 'month' => $month]) }}" target="_blank" class="ms-2 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
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

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <x-stat-card :label="__('Income')" icon="arrow-up-circle" icon-color="text-green-600 dark:text-green-400" icon-bg="bg-green-50 dark:bg-green-500/10">
                        <x-money :amount="$currencySummary['income']" :currency="$currency" />
                    </x-stat-card>
                    <x-stat-card :label="__('Expenses')" icon="arrow-down-circle" icon-color="text-red-600 dark:text-red-400" icon-bg="bg-red-50 dark:bg-red-500/10">
                        <x-money :amount="$currencySummary['expense']" :currency="$currency" />
                    </x-stat-card>
                    <x-stat-card :label="__('Savings')" icon="chart-pie">
                        <x-money :amount="$currencySummary['savings']" :currency="$currency" />
                        <x-slot name="footer">{{ number_format($currencySummary['savingsRate'], 1) }}% {{ __('savings rate') }}</x-slot>
                    </x-stat-card>
                    <x-stat-card :label="__('Fixed expenses')" icon="arrow-path">
                        <x-money :amount="$fixedExpense[$currency]" :currency="$currency" />
                        <x-slot name="footer">{{ __('generated by recurring transactions') }}</x-slot>
                    </x-stat-card>
                    <x-stat-card :label="__('Variable expenses')" icon="wallet">
                        <x-money :amount="$variableExpense[$currency]" :currency="$currency" />
                    </x-stat-card>
                    <x-stat-card :label="__('Current net worth')" icon="banknotes">
                        <x-money :amount="$currencySummary['netWorth']" :currency="$currency" />
                    </x-stat-card>
                </div>

                @if (! empty($highlights[$currency]))
                    <div class="rounded-xl border border-primary-100 bg-primary-50/60 p-5 dark:border-primary-500/20 dark:bg-primary-500/5">
                        <h3 class="mb-2 text-sm font-semibold text-primary-800 dark:text-primary-300">{{ __('Analysis of the month') }}</h3>
                        <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                            @foreach ($highlights[$currency] as $line)
                                <li class="flex gap-2"><span class="text-primary-500">&bull;</span>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php
                    $categoryRows = $expenseByCategory->get($currency, collect());
                @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('Expense distribution by category') }}</h3>
                    @if ($categoryRows->isEmpty())
                        <p class="py-8 text-center text-sm text-gray-400">{{ __('No expenses recorded in this period.') }}</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($categoryRows as $row)
                                <div>
                                    <div class="mb-1 flex items-center justify-between text-sm">
                                        <span class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                            <x-category-icon :category="$row['category']" size="h-6 w-6" />
                                            {{ $row['category']?->name ?? __('No category') }}
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white"><x-money :amount="$row['total']" :currency="$currency" /> ({{ number_format($row['percentage'], 1) }}%)</span>
                                    </div>
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div class="h-full rounded-full" style="width: {{ $row['percentage'] }}%; background-color: {{ $row['category']?->color ?? '#6b7280' }};"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
