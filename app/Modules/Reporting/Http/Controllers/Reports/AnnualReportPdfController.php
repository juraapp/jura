<?php

namespace App\Modules\Reporting\Http\Controllers\Reports;

use App\Modules\Reporting\Services\ReportService;
use App\Modules\Shared\Http\Controllers\Controller;
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
