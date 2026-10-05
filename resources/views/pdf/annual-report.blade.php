<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Annual report') }} — {{ $year }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #1f2937; font-size: 12px; }
        h1 { font-size: 20px; margin-bottom: 0; }
        .subtitle { color: #6b7280; margin-top: 2px; margin-bottom: 20px; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .grid td { width: 25%; padding: 10px; border: 1px solid #e5e7eb; }
        .label { color: #6b7280; font-size: 10px; text-transform: uppercase; }
        .value { font-size: 15px; font-weight: bold; margin-top: 4px; }
        .green { color: #16a34a; }
        .red { color: #dc2626; }
        h2 { font-size: 14px; margin-top: 24px; margin-bottom: 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 6px 4px; }
        table.list td { padding: 6px 4px; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
    </style>
</head>
<body>
    <h1>{{ __('Annual financial report') }}</h1>
    <p class="subtitle">{{ $user->name }} &middot; {{ $year }}</p>

    @foreach ($summary as $currency => $currencySummary)
        @if ($summary->count() > 1)
            <h2>{{ $currency }}</h2>
        @endif

        <table class="grid">
            <tr>
                <td>
                    <div class="label">{{ __('Total income') }}</div>
                    <div class="value green">{{ $currencySummary['income']->format($currency, $user->locale) }}</div>
                </td>
                <td>
                    <div class="label">{{ __('Total expenses') }}</div>
                    <div class="value red">{{ $currencySummary['expense']->format($currency, $user->locale) }}</div>
                </td>
                <td>
                    <div class="label">{{ __('Annual savings (:rate%)', ['rate' => number_format($currencySummary['savingsRate'], 1)]) }}</div>
                    <div class="value">{{ $currencySummary['savings']->format($currency, $user->locale) }}</div>
                </td>
                <td>
                    <div class="label">{{ __('Monthly average expense') }}</div>
                    <div class="value">{{ $currencySummary['avgMonthlyExpense']->format($currency, $user->locale) }}</div>
                </td>
            </tr>
        </table>

        @if ($currencySummary['topCategory'])
            <p><strong>{{ __('Category where you spent the most') }}:</strong> {{ $currencySummary['topCategory']['category']?->name }} ({{ number_format($currencySummary['topCategory']['percentage'], 1) }}% {{ __('of the total') }})</p>
        @endif
        @if ($currencySummary['bestIncomeMonth'])
            <p><strong>{{ __('Month with the highest income') }}:</strong> {{ ucfirst($currencySummary['bestIncomeMonth']['label']) }} ({{ $currencySummary['bestIncomeMonth']['income']->format($currency, $user->locale) }})</p>
        @endif
        @if ($currencySummary['worstExpenseMonth'])
            <p><strong>{{ __('Month with the highest expenses') }}:</strong> {{ ucfirst($currencySummary['worstExpenseMonth']['label']) }} ({{ $currencySummary['worstExpenseMonth']['expense']->format($currency, $user->locale) }})</p>
        @endif

        <h2>{{ __('Month-by-month comparison') }}</h2>
        <table class="list">
            <thead>
                <tr><th>{{ __('Month') }}</th><th style="text-align:right">{{ __('Income') }}</th><th style="text-align:right">{{ __('Expenses') }}</th><th style="text-align:right">{{ __('Savings') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($currencySummary['months'] as $row)
                    <tr>
                        <td>{{ ucfirst($row['label']) }}</td>
                        <td style="text-align:right">{{ $row['income']->format($currency, $user->locale) }}</td>
                        <td style="text-align:right">{{ $row['expense']->format($currency, $user->locale) }}</td>
                        <td style="text-align:right">{{ $row['savings']->format($currency, $user->locale) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
