<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Http\Requests\Buckets\AdjustBucketFundsRequest;
use App\Modules\Planning\Http\Requests\Buckets\CycleLimitRequest;
use App\Modules\Planning\Http\Requests\Buckets\MoveBucketFundsRequest;
use App\Modules\Planning\Http\Requests\Buckets\StoreBucketRequest;
use App\Modules\Planning\Http\Requests\Buckets\UpdateBucketRequest;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\BucketCycle;
use App\Modules\Planning\Services\BucketCycleService;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Planning\Services\BucketManager;
use App\Modules\Planning\Services\BucketService;
use App\Modules\Planning\Services\CycleProjector;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Shared\Http\Controllers\Controller;
use App\Modules\Shared\Http\Requests\BulkIdsRequest;
use App\Modules\Shared\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BucketController extends Controller
{
    public function index(BucketService $limits, BucketLedger $funds, PlannedEventService $planned, LedgerQuery $ledger, CycleProjector $projector): Response
    {
        $this->authorize('viewAny', Bucket::class);

        $accounts = $ledger->activeAccountOptions();
        $accountNames = $accounts->pluck('name', 'id');

        $buckets = Bucket::with('category')->inPriorityOrder()->get();
        $recent = $this->recentAllocations($buckets);
        $projections = $buckets->pluck('currency')->unique()->mapWithKeys(fn (string $currency) => [$currency => $projector->project(Auth::user(), $currency)]);

        $cards = $buckets->map(function (Bucket $bucket) use ($limits, $funds, $planned, $accountNames, $recent, $projections) {
            $card = [
                'id' => $bucket->id,
                'name' => $bucket->name,
                'kind' => $bucket->kind->value,
                'kindLabel' => $bucket->kind->label(),
                'currency' => $bucket->currency,
                'priority' => $bucket->priority,
                'category' => $bucket->category ? [
                    'id' => $bucket->category->id,
                    'name' => $bucket->category->name,
                    'icon' => $bucket->category->icon,
                    'color' => $bucket->category->color,
                ] : null,
                'cycle' => $bucket->cycle?->value,
                'cycleLabel' => $bucket->cycle?->label(),
                'cycleEvery' => $bucket->cycle_every,
                'cycleAnchor' => $bucket->cycle_anchor?->toDateString(),
                'cycleDescription' => $bucket->cyclePeriod()?->describe(),
                'cycleGoal' => $bucket->cycle_goal?->toFloat(),
                'cap' => $bucket->cap_amount?->toFloat(),
                'fillRule' => $bucket->fill_rule?->value,
                'fillRuleLabel' => $bucket->fill_rule?->label(),
                'fillValue' => $bucket->fill_value !== null ? (float) $bucket->fill_value : null,
                'stepUpValue' => $bucket->step_up_value !== null ? (float) $bucket->step_up_value : null,
                'stepUpAfterBucketId' => $bucket->step_up_after_bucket_id,
                'completedOn' => $bucket->completed_on?->toDateString(),
                'completesOnPurchase' => $bucket->completes_on_purchase,
                'carryOver' => $bucket->carry_over->value,
                'carryOverLabel' => $bucket->carry_over->label(),
                'overflowBucketId' => $bucket->overflow_bucket_id,
                'allowedAccountIds' => $bucket->allowed_account_ids ?? [],
                'allowedAccounts' => collect($bucket->allowed_account_ids ?? [])
                    ->map(fn (int $id) => ['id' => $id, 'name' => $accountNames->get($id, '?')])->values(),
                'isDefault' => $bucket->is_default,
                'balance' => $funds->balance($bucket)->toFloat(),
                'openingBalance' => $funds->openingBalance($bucket)->toFloat(),
                'available' => $funds->available($bucket)->toFloat(),
                'hasFunding' => $funds->hasFunding($bucket),
                'tracksLimit' => $bucket->tracksLimit(),
                'recentAllocations' => $recent->get($bucket->id, collect())->values(),
                'cycleView' => $this->cycleView($bucket, $projections->get($bucket->currency, [])[$bucket->id] ?? null),
            ];

            if (! $bucket->tracksLimit()) {
                return $card + ['spent' => 0.0, 'remaining' => 0.0, 'percentage' => 0.0, 'status' => 'on_track', 'projected' => 0.0, 'effectiveRemaining' => 0.0];
            }

            $progress = $limits->progress($bucket);
            [$start, $end] = $limits->periodBounds($bucket);
            $projected = $planned->plannedExpenseTotal($bucket->category_id, $bucket->currency, $start, $end);

            return $card + [
                'spent' => $progress['spent']->toFloat(),
                'remaining' => $progress['remaining']->toFloat(),
                'percentage' => $progress['percentage'],
                'status' => $progress['status'],
                'projected' => $projected->toFloat(),
                'effectiveRemaining' => $progress['remaining']->subtract($projected)->toFloat(),
            ];
        })->values();

        $currencies = Auth::user()->accounts()->pluck('currency')->unique()->sort()->values();

        return Inertia::render('Buckets/Index', [
            'buckets' => $cards,
            'categories' => $ledger->categoryOptions(CategoryType::Expense),
            'accounts' => $accounts,
            'currencies' => $currencies->isEmpty() ? collect([Auth::user()->currency_default]) : $currencies,
            'kinds' => $this->options(BucketKind::cases()),
            'cycles' => $this->options(BudgetPeriodType::cases()),
            'fillRules' => $this->options(FillRule::cases()),
            'carryOvers' => $this->options(CarryOverPolicy::cases()),
        ]);
    }

    public function store(StoreBucketRequest $request, BucketManager $manager): RedirectResponse
    {
        $manager->save($request->user(), $request->validated());

        return back()->with('success', __('buckets.saved'));
    }

    public function update(UpdateBucketRequest $request, Bucket $bucket, BucketManager $manager): RedirectResponse
    {
        $manager->save($request->user(), $request->validated(), $bucket);

        return back()->with('success', __('buckets.saved'));
    }

    public function destroy(Bucket $bucket, BucketManager $manager): RedirectResponse
    {
        $this->authorize('delete', $bucket);

        $manager->delete($bucket);

        return back()->with('success', __('buckets.deleted'));
    }

    public function bulkDestroy(BulkIdsRequest $request, BucketManager $manager): RedirectResponse
    {
        $count = 0;

        foreach (Bucket::whereIn('id', $request->validated('ids'))->get() as $item) {
            $this->authorize('delete', $item);
            $manager->delete($item);
            $count++;
        }

        return back()->with('success', __('messages.bulk.deleted', ['count' => $count]));
    }

    public function move(MoveBucketFundsRequest $request, Bucket $bucket, BucketLedger $funds): RedirectResponse
    {
        $amount = Money::of($request->validated('amount'));

        if ($funds->available($bucket)->lessThan($amount)) {
            return back()->withErrors(['amount' => __('buckets.not_enough_funds')]);
        }

        $target = Bucket::findOrFail($request->validated('to_bucket_id'));
        $funds->move($bucket, $target, $amount, $request->validated('description'));

        return back()->with('success', __('buckets.moved'));
    }

    public function adjust(AdjustBucketFundsRequest $request, Bucket $bucket, BucketLedger $funds): RedirectResponse
    {
        $amount = Money::of($request->validated('amount'));

        if ($amount->isNegative() && $funds->balance($bucket)->lessThan($amount->abs())) {
            return back()->withErrors(['amount' => __('buckets.not_enough_funds')]);
        }

        $funds->credit($bucket, $amount, AllocationSource::Adjustment, null, null, $request->validated('description'));

        return back()->with('success', __('buckets.adjusted'));
    }

    /**
     * @param  array<string, mixed>|null  $row  the projector's row for this bucket
     * @return array<string, mixed>|null
     */
    private function cycleView(Bucket $bucket, ?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'start' => $row['start']->toDateString(),
            'end' => $row['end']->toDateString(),
            'provisional' => $row['provisional']->toFloat(),
            'projectedRelease' => $row['projectedRelease']->toFloat(),
            'projectedIn' => $row['projectedIn']->toFloat(),
            'projectedClose' => $row['projectedClose']->toFloat(),
            'receivedSoFar' => $this->receivedSoFar($bucket, $row),
            'capOverride' => $bucket->cyclePeriod() !== null
                ? BucketCycle::where('bucket_id', $bucket->id)->where('starts_on', $row['start']->toDateString())->first()?->cap_override?->toFloat()
                : null,
        ];
    }

    /**
     * Money that came into the bucket during the current cycle so far (for a contribution goal).
     *
     * @param  array<string, mixed>  $row
     */
    private function receivedSoFar(Bucket $bucket, array $row): float
    {
        $figures = app(BucketCycleService::class)->figures($bucket, $row['start'], today());

        return $figures['filled']->add($figures['moved_in'])->toFloat();
    }

    public function history(Bucket $bucket, BucketLedger $funds, BucketCycleService $cycles, CycleProjector $projector): Response
    {
        $this->authorize('view', $bucket);

        $closed = BucketCycle::where('bucket_id', $bucket->id)->where('status', BucketCycle::CLOSED)->orderByDesc('starts_on')->get()
            ->map(fn (BucketCycle $cycle) => $this->presentCycle($cycle->starts_on, $cycle->ends_on, [
                'opening' => $cycle->opening, 'filled' => $cycle->filled, 'moved_in' => $cycle->moved_in, 'moved_out' => $cycle->moved_out,
                'spent' => $cycle->spent, 'released' => $cycle->released, 'closing' => $cycle->closing,
            ], $cycle->cap, false));

        $period = $bucket->cyclePeriod();
        $current = null;
        if ($period !== null) {
            $now = today();
            $start = Carbon::instance($period->startOf($now->toDateTimeImmutable()));
            $end = Carbon::instance($period->endOf($now->toDateTimeImmutable()));
            $row = $projector->project(Auth::user(), $bucket->currency)[$bucket->id] ?? null;
            $figures = $cycles->figures($bucket, $start, $now);
            $current = $this->presentCycle($start, $end, $figures + [
                'released' => $row['projectedRelease'] ?? Money::zero(),
                'closing' => $row['projectedClose'] ?? Money::zero(),
            ], $bucket->effectiveCap($now), true) + [
                'provisional' => ($row['provisional'] ?? Money::zero())->toFloat(),
                'capOverride' => BucketCycle::where('bucket_id', $bucket->id)->where('starts_on', $start->toDateString())->first()?->cap_override?->toFloat(),
            ];
        }

        return Inertia::render('Buckets/History', [
            'bucket' => [
                'id' => $bucket->id,
                'name' => $bucket->name,
                'currency' => $bucket->currency,
                'kind' => $bucket->kind->value,
                'cap' => $bucket->cap_amount?->toFloat(),
                'cycleGoal' => $bucket->cycle_goal?->toFloat(),
                'cycleDescription' => $period?->describe(),
                'carryOverLabel' => $bucket->carry_over->label(),
                'overflowBucket' => $bucket->overflowBucket?->name,
                'balance' => $funds->balance($bucket)->toFloat(),
            ],
            'current' => $current,
            'cycles' => $closed->values(),
        ]);
    }

    public function cycleLimit(CycleLimitRequest $request, Bucket $bucket): RedirectResponse
    {
        $period = $bucket->cyclePeriod();
        abort_if($period === null || $bucket->kind !== BucketKind::Spending, 404);

        $start = $period->startOf(today()->toDateTimeImmutable())->format('Y-m-d');
        $end = $period->endOf(today()->toDateTimeImmutable())->format('Y-m-d');

        $cycle = BucketCycle::where('bucket_id', $bucket->id)->where('starts_on', $start)->first()
            ?? new BucketCycle(['bucket_id' => $bucket->id, 'starts_on' => $start, 'ends_on' => $end, 'status' => BucketCycle::OPEN]);
        $cycle->user_id = $bucket->user_id;
        $cycle->cap_override = $request->filled('cap_amount') ? Money::of($request->validated('cap_amount')) : null;
        $cycle->save();

        return back()->with('success', __('buckets.cycle_limit_saved'));
    }

    /**
     * @param  array<string, Money>  $figures
     * @return array<string, mixed>
     */
    private function presentCycle($start, $end, array $figures, ?Money $cap, bool $isOpen): array
    {
        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'isOpen' => $isOpen,
            'cap' => $cap?->toFloat(),
            'opening' => $figures['opening']->toFloat(),
            'filled' => $figures['filled']->toFloat(),
            'movedIn' => $figures['moved_in']->toFloat(),
            'movedOut' => $figures['moved_out']->toFloat(),
            'spent' => $figures['spent']->toFloat(),
            'released' => $figures['released']->toFloat(),
            'closing' => $figures['closing']->toFloat(),
        ];
    }

    public function complete(Bucket $bucket, BucketLedger $funds): RedirectResponse
    {
        $this->authorize('update', $bucket);

        $funds->complete($bucket, today());

        return back()->with('success', __('buckets.completed'));
    }

    public function reopen(Bucket $bucket, BucketLedger $funds): RedirectResponse
    {
        $this->authorize('update', $bucket);

        $funds->reopen($bucket);

        return back()->with('success', __('buckets.reopened'));
    }

    public function reset(Bucket $bucket, BucketLedger $funds): RedirectResponse
    {
        $this->authorize('update', $bucket);

        $balance = $funds->balance($bucket);
        if (! $balance->isZero()) {
            $funds->credit($bucket, $balance->negate(), AllocationSource::Adjustment, null, null, __('buckets.reset_note'));
        }

        return back()->with('success', __('buckets.reset'));
    }

    public function allocations(Bucket $bucket, BucketLedger $funds): JsonResponse
    {
        $this->authorize('view', $bucket);

        return response()->json([
            'allocations' => Allocation::where('bucket_id', $bucket->id)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Allocation $allocation) => [
                    'id' => $allocation->id,
                    'amount' => $allocation->amount->toFloat(),
                    'date' => $allocation->date->toDateString(),
                    'source' => $allocation->source->value,
                    'sourceLabel' => $allocation->source->label(),
                    'description' => $allocation->description,
                    'deletable' => $funds->isRemovable($allocation),
                ])->values(),
        ]);
    }

    public function destroyAllocation(Bucket $bucket, Allocation $allocation, BucketLedger $funds): RedirectResponse
    {
        $this->authorize('update', $bucket);
        abort_unless($allocation->bucket_id === $bucket->id, 404);

        if (! $funds->isRemovable($allocation)) {
            return back()->with('error', __('buckets.allocation_locked'));
        }

        $funds->remove($allocation);

        return back()->with('success', __('buckets.allocation_removed'));
    }

    /**
     * @param  Collection<int, Bucket>  $buckets
     * @return Collection<int, Collection<int, array<string, mixed>>> keyed by bucket id
     */
    private function recentAllocations(Collection $buckets): Collection
    {
        if ($buckets->isEmpty()) {
            return collect();
        }

        return Allocation::whereIn('bucket_id', $buckets->pluck('id'))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('bucket_id')
            ->map(fn (Collection $rows) => $rows->take(5)->map(fn (Allocation $allocation) => [
                'id' => $allocation->id,
                'amount' => $allocation->amount->toFloat(),
                'date' => $allocation->date->toDateString(),
                'source' => $allocation->source->value,
                'sourceLabel' => $allocation->source->label(),
                'description' => $allocation->description,
            ]));
    }

    /**
     * @param  list<\BackedEnum&object{label: callable}>  $cases
     * @return Collection<int, array{value: string, label: string}>
     */
    private function options(array $cases): Collection
    {
        return collect($cases)->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]);
    }
}
