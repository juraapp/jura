<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomReportCsvController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth();

        $transactions = Transaction::query()
            ->with(['account', 'category'])
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $filename = "transactions-{$from->toDateString()}-to-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            // BOM para que Excel detecte UTF-8 correctamente.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [__('Date'), __('Type'), __('Description'), __('Category'), __('Account'), __('Currency'), __('Value')]);

            foreach ($transactions as $transaction) {
                fputcsv($handle, [
                    $transaction->date->format('Y-m-d'),
                    $transaction->type->label(),
                    $transaction->description,
                    $transaction->category?->name,
                    $transaction->account->name,
                    $transaction->account->currency,
                    $transaction->amount->toDecimalString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
