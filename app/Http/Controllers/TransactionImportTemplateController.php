<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionImportTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            // BOM para que Excel detecte UTF-8 correctamente.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [__('Date'), __('Description'), __('Category'), __('Account'), __('Type'), __('Notes'), __('Amount')]);
            fputcsv($handle, ['2026-08-01', 'August salary', 'Salary', 'Your account name', 'Income', '', '3500000']);
            fputcsv($handle, ['2026-08-03', 'Monthly groceries', 'Food', 'Your account name', 'Expense', 'Supermarket purchase', '285000']);
            fclose($handle);
        }, 'transactions-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
