<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class AnnualReportPdfController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): PdfBuilder
    {
        $year = (int) $request->integer('year', now()->year);
        $user = $request->user();

        return Pdf::view('pdf.annual-report', [
            'user' => $user,
            'year' => $year,
            'summary' => $reports->annualSummary($user, $year),
        ])->inline("annual-report-{$year}.pdf");
    }
}
