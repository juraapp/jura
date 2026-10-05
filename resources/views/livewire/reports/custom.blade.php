<?php

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $from;

    public string $to;

    public function mount(): void
    {
        $this->from = Carbon::now()->startOfMonth()->toDateString();
        $this->to = Carbon::now()->endOfMonth()->toDateString();
    }

    public function with(): array
    {
        $transactions = Transaction::query()
            ->with(['account', 'category'])
            ->whereBetween('date', [$this->from, $this->to])
            ->orderBy('date')
            ->get();

        $byCurrency = $transactions->groupBy(fn (Transaction $t) => $t->account->currency);

        $totals = $byCurrency->map(function (Collection $rows) {
            $income = $rows->where('type', TransactionType::Income)->reduce(fn (Money $c, Transaction $t) => $c->add($t->amount), Money::zero());
            $expense = $rows->where('type', TransactionType::Expense)->reduce(fn (Money $c, Transaction $t) => $c->add($t->amount), Money::zero());

            return [
                'income' => $income,
                'expense' => $expense,
                'savings' => $income->subtract($expense),
            ];
        });

        return [
            'transactions' => $transactions,
            'totals' => $totals,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Custom report') }}
        </h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div>
                <x-input-label for="from" :value="__('From')" />
                <x-text-input wire:model.live="from" id="from" type="date" class="mt-1" />
            </div>
            <div>
                <x-input-label for="to" :value="__('To')" />
                <x-text-input wire:model.live="to" id="to" type="date" class="mt-1" />
            </div>
            <a href="{{ route('reports.custom.csv', ['from' => $from, 'to' => $to]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                <x-dynamic-icon name="arrow-down-tray" class="h-4 w-4" />
                {{ __('Export CSV') }}
            </a>
        </div>

        @foreach ($totals as $currency => $currencyTotals)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-stat-card :label="__('Income for the period').' ('.$currency.')'" icon="arrow-up-circle" icon-color="text-green-600 dark:text-green-400" icon-bg="bg-green-50 dark:bg-green-500/10">
                    <x-money :amount="$currencyTotals['income']" :currency="$currency" />
                </x-stat-card>
                <x-stat-card :label="__('Expenses for the period').' ('.$currency.')'" icon="arrow-down-circle" icon-color="text-red-600 dark:text-red-400" icon-bg="bg-red-50 dark:bg-red-500/10">
                    <x-money :amount="$currencyTotals['expense']" :currency="$currency" />
                </x-stat-card>
                <x-stat-card :label="__('Savings for the period').' ('.$currency.')'" icon="chart-pie">
                    <x-money :amount="$currencyTotals['savings']" :currency="$currency" />
                </x-stat-card>
            </div>
        @endforeach

        @if ($transactions->isEmpty())
            <x-empty-state icon="list-bullet" :title="__('No transactions in this date range')" />
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($transactions as $transaction)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">{{ $transaction->date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $transaction->description ?: $transaction->category?->name }}</td>
                                    <td class="px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">{{ $transaction->account->name }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right text-sm font-medium tabular-nums {{ $transaction->type->value === 'income' ? 'text-green-600 dark:text-green-400' : ($transaction->type->value === 'expense' ? 'text-red-600 dark:text-red-400' : 'text-gray-500') }}">
                                        <x-money :amount="$transaction->amount" :currency="$transaction->account->currency" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
