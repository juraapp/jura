<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\InsightService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class MonthlyReportPdfController extends Controller
{
    public function __invoke(Request $request, ReportService $reports, InsightService $insights): PdfBuilder
    {
        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);
        $user = $request->user();

        $summary = $reports->monthlySummary($user, $year, $month);

        return Pdf::view('pdf.monthly-report', [
            'user' => $user,
            'summary' => $summary,
            'highlights' => $summary->map(
                fn (array $currencySummary) => $insights->monthlyHighlights($currencySummary, $user, $year, $month)
            ),
            'expenseByCategory' => $reports->expenseByCategory($user, $year, $month),
            'periodLabel' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
        ])->inline("monthly-report-{$year}-{$month}.pdf");
    }
}
