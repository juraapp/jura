<?php

namespace App\Modules\Reporting\Http\Controllers\Reports;

use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Reporting\Services\PlanningReportService;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsReportController extends Controller
{
    public function __invoke(Request $request, PlanningReportService $reports): Response
    {
        $view = ForecastView::tryFrom((string) $request->query('view')) ?? ForecastView::Expected;

        return Inertia::render('Reports/Projects', [
            'projects' => $reports->projects($request->user(), $view),
            'view' => $view->value,
            'views' => collect(ForecastView::cases())->map(fn (ForecastView $candidate) => ['value' => $candidate->value, 'label' => $candidate->label()]),
        ]);
    }
}
