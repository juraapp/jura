<?php

namespace App\Modules\Reporting\Http\Controllers\Reports;

use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Reporting\Services\PlanningReportService;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BucketsReportController extends Controller
{
    public function __invoke(Request $request, PlanningReportService $reports): Response
    {
        $view = ForecastView::fromRequest($request->query('view'));

        return Inertia::render('Reports/Buckets', [
            'currencies' => $reports->buckets($request->user(), $view),
            'view' => $view->value,
            'views' => collect(ForecastView::cases())->map(fn (ForecastView $candidate) => ['value' => $candidate->value, 'label' => $candidate->label()]),
        ]);
    }
}
