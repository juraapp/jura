<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionImportTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            // BOM so Excel detects UTF-8 correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                __('messages.csv.date'),
                __('messages.csv.description'),
                __('messages.csv.category'),
                __('messages.csv.account'),
                __('messages.csv.type'),
                __('messages.csv.notes'),
                __('messages.csv.amount'),
            ]);
            fputcsv($handle, ['2026-08-01', __('messages.import.template_income_description'), __('messages.import.template_income_category'), __('messages.import.template_account_name'), TransactionType::Income->label(), '', '3500000']);
            fputcsv($handle, ['2026-08-03', __('messages.import.template_expense_description'), __('messages.import.template_expense_category'), __('messages.import.template_account_name'), TransactionType::Expense->label(), __('messages.import.template_expense_notes'), '285000']);
            fclose($handle);
        }, 'transaction-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
