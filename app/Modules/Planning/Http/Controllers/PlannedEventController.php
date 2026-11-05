<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Planning\Contracts\UpcomingPlans;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\PlannedEventStatus;
use App\Modules\Planning\Enums\RecurringFrequency;
use App\Modules\Planning\Http\Requests\PlannedEvents\LinkPlannedEventRequest;
use App\Modules\Planning\Http\Requests\PlannedEvents\RealizePlannedEventRequest;
use App\Modules\Planning\Http\Requests\PlannedEvents\StorePlannedEventRequest;
use App\Modules\Planning\Http\Requests\PlannedEvents\UpdatePlannedEventRequest;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\PlannedEvent;
use App\Modules\Planning\Models\PlannedEventSeries;
use App\Modules\Planning\Services\BucketProjection;
use App\Modules\Planning\Services\CashFlowProjector;
use App\Modules\Planning\Services\PlannedEventPresenter;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Planning\Services\SeriesPoster;
use App\Modules\Shared\Http\Controllers\Controller;
use App\Modules\Shared\Http\Requests\BulkIdsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PlannedEventController extends Controller
{
    /** Rows the review inbox shows on the cash flow page. */
    private const REVIEW_LIMIT = 50;

    public function index(Request $request, CashFlowProjector $projector, BucketProjection $buckets, PlannedEventService $service, PlannedEventPresenter $presenter, UpcomingPlans $upcoming): Response
    {
        $this->authorize('viewAny', PlannedEvent::class);

        $view = ForecastView::fromRequest($request->query('view'));
        $months = max(3, min(12, (int) $request->query('months', 6)));
        $monthStart = Carbon::instance($request->user()->today())->startOfMonth();
        $horizonEnd = $monthStart->copy()->addMonthsNoOverflow($months)->subDay();

        $events = PlannedEvent::with(['account', 'counterAccount', 'category'])
            ->where(function ($query) use ($monthStart) {
                $query->where('date', '>=', $monthStart->toDateString())
                    ->orWhere('status', PlannedEventStatus::Planned->value);
            })
            ->where('date', '<=', $horizonEnd->toDateString())
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $matches = $service->suggestedMatches($request->user(), $events);

        $budget = $this->budgetRows($request->user(), $view, $months, $projector, $buckets);
        $projection = $projector->project($request->user(), $view, $months)->map(fn (array $currency) => [
            'currency' => $currency['currency'],
            'opening' => $currency['opening']->toFloat(),
            'months' => collect($currency['months'])->map(fn (array $month, int $index) => [
                'month' => $month['month'],
                'income' => $month['income']->toFloat(),
                'expense' => $month['expense']->toFloat(),
                'interest' => $month['interest']->toFloat(),
                'net' => $month['net']->toFloat(),
                'closing' => $month['closing']->toFloat(),
                'onBudget' => $budget[$currency['currency']][$index]['onBudget'] ?? null,
                'readyToAssign' => $budget[$currency['currency']][$index]['readyToAssign'] ?? null,
            ])->values(),
        ])->values();

        $today = $request->user()->today();
        $rules = PlannedEventSeries::with(['account', 'counterAccount', 'category'])
            ->orderByDesc('is_active')
            ->orderBy('description')
            ->get();
        $nextDates = $service->nextDates($rules, $today);
        $series = $rules->map(fn (PlannedEventSeries $rule) => $presenter->series($rule, $nextDates[$rule->id]))->values();

        return Inertia::render('CashFlow/Index', [
            'events' => $events->map(fn (PlannedEvent $event) => $presenter->event($event, $matches[$event->id] ?? null))->values(),
            'series' => $series,
            'review' => $upcoming->needsReview($request->user(), self::REVIEW_LIMIT),
            'projection' => $projection,
            'view' => $view->value,
            'views' => collect(ForecastView::cases())->map(fn (ForecastView $case) => ['value' => $case->value, 'label' => $case->label()]),
            'months' => $months,
            'section' => in_array($request->query('section'), ['repeating', 'review'], true) ? $request->query('section') : null,
        ]);
    }

    /**
     * Projected cash in on-budget accounts at each month end (no interest) and what of it is ready to assign: that cash
     * minus what funded bucket envelopes are projected to hold. Projected only; nothing here is a real balance.
     *
     * @return array<string, list<array{onBudget: float, readyToAssign: float}>> keyed by currency, one row per month
     */
    private function budgetRows(User $user, ForecastView $view, int $months, CashFlowProjector $projector, BucketProjection $buckets): array
    {
        $rows = [];
        foreach ($projector->budgetMonthly($user, $view, $months) as $currency => $projection) {
            $ends = array_map(fn (array $month) => Carbon::parse($month['month'].'-01')->endOfMonth()->startOfDay(), $projection['months']);
            $earmarks = $buckets->earmarksAt($user, $currency, $view, $ends);

            $rows[$currency] = array_map(fn (array $month, Carbon $end) => [
                'onBudget' => $month['closing']->toFloat(),
                'readyToAssign' => $month['closing']->subtract($earmarks[$end->toDateString()])->toFloat(),
            ], $projection['months'], $ends);
        }

        return $rows;
    }

    /**
     * What the money-movement form needs once "Planned" is chosen (loaded on demand, like the transaction
     * form options).
     */
    public function formOptions(): JsonResponse
    {
        $this->authorize('viewAny', PlannedEvent::class);

        return response()->json([
            'frequencies' => collect(RecurringFrequency::cases())->map(fn (RecurringFrequency $case) => ['value' => $case->value, 'label' => $case->label()]),
            'certainties' => collect(Certainty::cases())->map(fn (Certainty $case) => ['value' => $case->value, 'label' => $case->label()]),
            'buckets' => Bucket::inPriorityOrder()->get(['id', 'name', 'currency']),
        ]);
    }

    public function store(StorePlannedEventRequest $request, PlannedEventService $service, SeriesPoster $poster): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['frequency'])) {
            $service->createSeries($request->user(), $data);
        } else {
            $service->createEvent($request->user(), $data);
        }

        if (! empty($data['auto_post'])) {
            $poster->postDueForUser($request->user());
        }

        return back()->with('success', __(! empty($data['frequency']) ? 'planned-events.series_saved' : 'planned-events.saved'));
    }

    public function update(UpdatePlannedEventRequest $request, PlannedEvent $plannedEvent, PlannedEventService $service, SeriesPoster $poster): RedirectResponse
    {
        if ($plannedEvent->status !== PlannedEventStatus::Planned) {
            return back()->with('error', __('planned-events.already_closed'));
        }

        $service->updateEvent($plannedEvent, $request->validated(), $request->input('scope', 'this'));

        if ($request->boolean('auto_post')) {
            $poster->postDueForUser($request->user());
        }

        return back()->with('success', __('planned-events.updated'));
    }

    public function destroy(Request $request, PlannedEvent $plannedEvent, PlannedEventService $service): RedirectResponse
    {
        $this->authorize('delete', $plannedEvent);

        $scope = $request->validate(['scope' => ['nullable', 'in:this,future']])['scope'] ?? 'this';

        $service->deleteEvent($plannedEvent, $scope);

        return back()->with('success', __('planned-events.deleted'));
    }

    public function bulkDestroy(BulkIdsRequest $request, PlannedEventService $service): RedirectResponse
    {
        $count = 0;

        // A realized plan is the record of where its transaction came from: bulk delete leaves it alone.
        foreach (PlannedEvent::whereIn('id', $request->validated('ids'))->where('status', '!=', PlannedEventStatus::Realized->value)->get() as $item) {
            $this->authorize('delete', $item);
            $service->deleteEvent($item, 'this');
            $count++;
        }

        return back()->with('success', __('messages.bulk.deleted', ['count' => $count]));
    }

    public function realize(RealizePlannedEventRequest $request, PlannedEvent $plannedEvent, PlannedEventService $service): RedirectResponse
    {
        if ($plannedEvent->status !== PlannedEventStatus::Planned) {
            return back()->with('error', __('planned-events.already_closed'));
        }

        if ($service->realize($plannedEvent, $request->validated()) === null) {
            return back()->with('error', __('planned-events.already_closed'));
        }

        return back()->with('success', __('planned-events.realized'));
    }

    public function skip(Request $request, PlannedEvent $plannedEvent, PlannedEventService $service): RedirectResponse
    {
        $this->authorize('update', $plannedEvent);

        if ($plannedEvent->status !== PlannedEventStatus::Planned) {
            return back()->with('error', __('planned-events.already_closed'));
        }

        $service->skip($plannedEvent);

        return back()->with('success', __('planned-events.skipped'));
    }

    public function reopen(PlannedEvent $plannedEvent, PlannedEventService $service): RedirectResponse
    {
        $this->authorize('update', $plannedEvent);

        if ($plannedEvent->status !== PlannedEventStatus::Skipped) {
            return back()->with('error', __('planned-events.not_skipped'));
        }

        $service->reopen($plannedEvent);

        return back()->with('success', __('planned-events.reopened'));
    }

    public function link(LinkPlannedEventRequest $request, PlannedEvent $plannedEvent, PlannedEventService $service): RedirectResponse
    {
        if ($plannedEvent->status !== PlannedEventStatus::Planned) {
            return back()->with('error', __('planned-events.already_closed'));
        }

        $service->link($plannedEvent, (int) $request->validated('transaction_id'));

        return back()->with('success', __('planned-events.linked'));
    }
}
