<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Planning\Http\Requests\PlannedEvents\UpdatePlannedSeriesRequest;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Planning\Services\SeriesPoster;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * A repeating plan as a whole: edit it from today on, pause, resume or delete it.
 */
class PlannedSeriesController extends Controller
{
    public function update(UpdatePlannedSeriesRequest $request, PlannedEventSeries $plannedEventSeries, PlannedEventService $service, SeriesPoster $poster): RedirectResponse
    {
        $service->updateSeries($plannedEventSeries, $request->validated());

        if ($request->boolean('auto_post')) {
            $poster->postDueForUser($request->user());
        }

        return back()->with('success', __('planned-events.series_updated'));
    }

    public function pause(PlannedEventSeries $plannedEventSeries, PlannedEventService $service): RedirectResponse
    {
        $this->authorize('update', $plannedEventSeries);

        $service->pauseSeries($plannedEventSeries);

        return back()->with('success', __('planned-events.series_paused'));
    }

    public function resume(PlannedEventSeries $plannedEventSeries, PlannedEventService $service): RedirectResponse
    {
        $this->authorize('update', $plannedEventSeries);

        $service->resumeSeries($plannedEventSeries);

        return back()->with('success', __('planned-events.series_resumed'));
    }

    public function destroy(PlannedEventSeries $plannedEventSeries, PlannedEventService $service): RedirectResponse
    {
        $this->authorize('delete', $plannedEventSeries);

        $service->deleteSeries($plannedEventSeries);

        return back()->with('success', __('planned-events.series_deleted'));
    }
}
