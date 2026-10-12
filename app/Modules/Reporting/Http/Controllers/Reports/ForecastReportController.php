<?php

namespace App\Modules\Reporting\Http\Controllers\Reports;

use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Reporting\Services\PlanningReportService;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForecastReportController extends Controller
{
    public function __invoke(Request $request, PlanningReportService $reports): Response
    {
        return Inertia::render('Reports/Forecast', [
            'currencies' => $reports->forecast($request->user()),
            'views' => collect(ForecastView::cases())->map(fn (ForecastView $view) => ['value' => $view->value, 'label' => $view->label()]),
        ]);
    }
}
