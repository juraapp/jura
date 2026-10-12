<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Enums\CategoryType;
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
use App\Modules\Planning\Services\CashFlowProjector;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlannedEventController extends Controller
{
    public function index(Request $request, CashFlowProjector $projector, PlannedEventService $service, LedgerQuery $ledger): Response
    {
        $this->authorize('viewAny', PlannedEvent::class);

        $view = ForecastView::tryFrom((string) $request->query('view')) ?? ForecastView::Expected;
        $months = max(3, min(12, (int) $request->query('months', 6)));
        $monthStart = today()->startOfMonth();
        $horizonEnd = $monthStart->copy()->addMonthsNoOverflow($months)->subDay();

        $events = PlannedEvent::with(['account', 'category'])
            ->where(function ($query) use ($monthStart) {
                $query->where('date', '>=', $monthStart->toDateString())
                    ->orWhere('status', PlannedEventStatus::Planned->value);
            })
            ->where('date', '<=', $horizonEnd->toDateString())
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $matches = $service->suggestedMatches($events);

        $projection = $projector->project($request->user(), $view, $months)->map(fn (array $currency) => [
            'currency' => $currency['currency'],
            'opening' => $currency['opening']->toFloat(),
            'months' => collect($currency['months'])->map(fn (array $month) => [
                'month' => $month['month'],
                'income' => $month['income']->toFloat(),
                'expense' => $month['expense']->toFloat(),
                'net' => $month['net']->toFloat(),
                'closing' => $month['closing']->toFloat(),
            ])->values(),
        ])->values();

        return Inertia::render('CashFlow/Index', [
            'events' => $events->map(fn (PlannedEvent $event) => $this->present($event, $matches[$event->id] ?? null))->values(),
            'projection' => $projection,
            'view' => $view->value,
            'views' => collect(ForecastView::cases())->map(fn (ForecastView $case) => ['value' => $case->value, 'label' => $case->label()]),
            'months' => $months,
            'accounts' => $ledger->activeAccountOptions(),
            'expenseCategories' => $ledger->categoryOptions(CategoryType::Expense, ['id', 'name', 'icon', 'color']),
            'incomeCategories' => $ledger->categoryOptions(CategoryType::Income, ['id', 'name', 'icon', 'color']),
            'frequencies' => collect(RecurringFrequency::cases())->map(fn (RecurringFrequency $case) => ['value' => $case->value, 'label' => $case->label()]),
            'buckets' => Bucket::inPriorityOrder()->get(['id', 'name', 'currency']),
            'certainties' => collect(Certainty::cases())->map(fn (Certainty $case) => ['value' => $case->value, 'label' => $case->label()]),
        ]);
    }

    public function store(StorePlannedEventRequest $request, PlannedEventService $service): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['frequency'])) {
            $service->createSeries($request->user(), $data);

            return back()->with('success', __('planned-events.series_saved'));
        }

        $service->createEvent($request->user(), $data);

        return back()->with('success', __('planned-events.saved'));
    }

    public function update(UpdatePlannedEventRequest $request, PlannedEvent $plannedEvent, PlannedEventService $service): RedirectResponse
    {
        if ($plannedEvent->status !== PlannedEventStatus::Planned) {
            return back()->with('error', __('planned-events.already_closed'));
        }

        $service->updateEvent($plannedEvent, $request->validated(), $request->input('scope', 'this'));

        return back()->with('success', __('planned-events.updated'));
    }

    public function destroy(Request $request, PlannedEvent $plannedEvent, PlannedEventService $service): RedirectResponse
    {
        $this->authorize('delete', $plannedEvent);

        $scope = $request->validate(['scope' => ['nullable', 'in:this,future']])['scope'] ?? 'this';

        $service->deleteEvent($plannedEvent, $scope);

        return back()->with('success', __('planned-events.deleted'));
    }

    public function realize(RealizePlannedEventRequest $request, PlannedEvent $plannedEvent, PlannedEventService $service): RedirectResponse
    {
        if ($plannedEvent->status !== PlannedEventStatus::Planned) {
            return back()->with('error', __('planned-events.already_closed'));
        }

        $service->realize($plannedEvent, $request->validated());

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

    /**
     * @return array<string, mixed>
     */
    private function present(PlannedEvent $event, ?\stdClass $match): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type->value,
            'description' => $event->description,
            'amount' => $event->amount->toFloat(),
            'currency' => $event->currency,
            'date' => $event->date->toDateString(),
            'status' => $event->status->value,
            'statusLabel' => $event->status->label(),
            'certainty' => $event->certainty->value,
            'certaintyLabel' => $event->certainty->label(),
            'seriesId' => $event->series_id,
            'notes' => $event->notes,
            'allocationOverrides' => $event->allocation_overrides ?? [],
            'account' => $event->account ? ['id' => $event->account->id, 'name' => $event->account->name] : null,
            'category' => $event->category ? [
                'id' => $event->category->id,
                'name' => $event->category->name,
                'icon' => $event->category->icon,
                'color' => $event->category->color,
            ] : null,
            'suggestedMatch' => $match,
        ];
    }
}
