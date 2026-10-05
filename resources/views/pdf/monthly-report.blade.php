<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Monthly report') }} — {{ $periodLabel }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #1f2937; font-size: 12px; }
        h1 { font-size: 20px; margin-bottom: 0; }
        .subtitle { color: #6b7280; margin-top: 2px; margin-bottom: 20px; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .grid td { width: 33%; padding: 10px; border: 1px solid #e5e7eb; }
        .label { color: #6b7280; font-size: 10px; text-transform: uppercase; }
        .value { font-size: 16px; font-weight: bold; margin-top: 4px; }
        .green { color: #16a34a; }
        .red { color: #dc2626; }
        h2 { font-size: 14px; margin-top: 24px; margin-bottom: 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 6px 4px; }
        table.list td { padding: 6px 4px; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
        ul.insights { padding-left: 16px; }
        ul.insights li { margin-bottom: 4px; }
    </style>
</head>
<body>
    <h1>{{ __('Monthly financial report') }}</h1>
    <p class="subtitle">{{ $user->name }} &middot; {{ ucfirst($periodLabel) }}</p>

    @foreach ($summary as $currency => $currencySummary)
        @if ($summary->count() > 1)
            <h2>{{ $currency }}</h2>
        @endif

        <table class="grid">
            <tr>
                <td>
                    <div class="label">{{ __('Income') }}</div>
                    <div class="value green">{{ $currencySummary['income']->format($currency, $user->locale) }}</div>
                </td>
                <td>
                    <div class="label">{{ __('Expenses') }}</div>
                    <div class="value red">{{ $currencySummary['expense']->format($currency, $user->locale) }}</div>
                </td>
                <td>
                    <div class="label">{{ __('Savings (:rate%)', ['rate' => number_format($currencySummary['savingsRate'], 1)]) }}</div>
                    <div class="value">{{ $currencySummary['savings']->format($currency, $user->locale) }}</div>
                </td>
            </tr>
        </table>

        @if (! empty($highlights[$currency]))
            <h2>{{ __('Analysis of the month') }}</h2>
            <ul class="insights">
                @foreach ($highlights[$currency] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @endif

        <h2>{{ __('Expenses by category') }}</h2>
        @php $categoryRows = $expenseByCategory->get($currency, collect()); @endphp
        @if ($categoryRows->isEmpty())
            <p>{{ __('No expenses recorded in this period.') }}</p>
        @else
            <table class="list">
                <thead>
                    <tr><th>{{ __('Category') }}</th><th style="text-align:right">{{ __('Value') }}</th><th style="text-align:right">%</th></tr>
                </thead>
                <tbody>
                    @foreach ($categoryRows as $row)
                        <tr>
                            <td>{{ $row['category']?->name ?? __('No category') }}</td>
                            <td style="text-align:right">{{ $row['total']->format($currency, $user->locale) }}</td>
                            <td style="text-align:right">{{ number_format($row['percentage'], 1) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</body>
</html>
