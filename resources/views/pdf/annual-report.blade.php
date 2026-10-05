<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $user->locale) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('pdf.annual_title', ['year' => $year], $user->locale) }}</title>
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
    <h1>{{ __('pdf.annual_heading', [], $user->locale) }}</h1>
    <p class="subtitle">{{ $user->name }} &middot; {{ $year }}</p>

    @foreach ($summary as $currency => $currencySummary)
        @if ($summary->count() > 1)
            <h2>{{ $currency }}</h2>
        @endif

        <table class="grid">
            <tr>
                <td>
                    <div class="label">{{ __('pdf.income_total', [], $user->locale) }}</div>
                    <div class="value green">{{ $currencySummary['income']->format($currency, $user->locale) }}</div>
                </td>
                <td>
                    <div class="label">{{ __('pdf.expense_total', [], $user->locale) }}</div>
                    <div class="value red">{{ $currencySummary['expense']->format($currency, $user->locale) }}</div>
                </td>
                <td>
                    <div class="label">{{ __('pdf.annual_savings', ['rate' => number_format($currencySummary['savingsRate'], 1)], $user->locale) }}</div>
                    <div class="value">{{ $currencySummary['savings']->format($currency, $user->locale) }}</div>
                </td>
                <td>
                    <div class="label">{{ __('pdf.avg_monthly_expense', [], $user->locale) }}</div>
                    <div class="value">{{ $currencySummary['avgMonthlyExpense']->format($currency, $user->locale) }}</div>
                </td>
            </tr>
        </table>

        @if ($currencySummary['topCategory'])
            <p>{{ __('pdf.top_category', ['category' => $currencySummary['topCategory']['category']?->name, 'percentage' => number_format($currencySummary['topCategory']['percentage'], 1)], $user->locale) }}</p>
        @endif
        @if ($currencySummary['bestIncomeMonth'])
            <p>{{ __('pdf.best_income_month', ['month' => ucfirst($currencySummary['bestIncomeMonth']['label']), 'amount' => $currencySummary['bestIncomeMonth']['income']->format($currency, $user->locale)], $user->locale) }}</p>
        @endif
        @if ($currencySummary['worstExpenseMonth'])
            <p>{{ __('pdf.worst_expense_month', ['month' => ucfirst($currencySummary['worstExpenseMonth']['label']), 'amount' => $currencySummary['worstExpenseMonth']['expense']->format($currency, $user->locale)], $user->locale) }}</p>
        @endif

        <h2>{{ __('pdf.monthly_comparison_heading', [], $user->locale) }}</h2>
        <table class="list">
            <thead>
                <tr><th>{{ __('pdf.month', [], $user->locale) }}</th><th style="text-align:right">{{ __('pdf.income', [], $user->locale) }}</th><th style="text-align:right">{{ __('pdf.expense', [], $user->locale) }}</th><th style="text-align:right">{{ __('pdf.savings_column', [], $user->locale) }}</th></tr>
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
