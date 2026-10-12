<?php

namespace App\Modules\Planning\Http\Controllers;

use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Ledger\Enums\CategoryType;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\BudgetPeriodType;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Enums\AllocationSource;
use App\Modules\Planning\Http\Requests\Buckets\AdjustBucketFundsRequest;
use App\Modules\Planning\Http\Requests\Buckets\MoveBucketFundsRequest;
use App\Modules\Planning\Http\Requests\Buckets\StoreBucketRequest;
use App\Modules\Planning\Http\Requests\Buckets\UpdateBucketRequest;
use App\Modules\Planning\Models\Allocation;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Services\BucketLedger;
use App\Modules\Planning\Services\BucketManager;
use App\Modules\Planning\Services\BucketService;
use App\Modules\Planning\Services\PlannedEventService;
use App\Modules\Shared\Http\Controllers\Controller;
use App\Modules\Shared\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BucketController extends Controller
{
    public function index(BucketService $limits, BucketLedger $funds, PlannedEventService $planned, LedgerQuery $ledger): Response
    {
        $this->authorize('viewAny', Bucket::class);

        $accounts = $ledger->activeAccountOptions();
        $accountNames = $accounts->pluck('name', 'id');

        $buckets = Bucket::with('category')->inPriorityOrder()->get();
        $recent = $this->recentAllocations($buckets);

        $cards = $buckets->map(function (Bucket $bucket) use ($limits, $funds, $planned, $accountNames, $recent) {
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
                'cap' => $bucket->cap_amount?->toFloat(),
                'fillRule' => $bucket->fill_rule?->value,
                'fillRuleLabel' => $bucket->fill_rule?->label(),
                'fillValue' => $bucket->fill_value !== null ? (float) $bucket->fill_value : null,
                'carryOver' => $bucket->carry_over->value,
                'carryOverLabel' => $bucket->carry_over->label(),
                'overflowBucketId' => $bucket->overflow_bucket_id,
                'allowedAccountIds' => $bucket->allowed_account_ids ?? [],
                'allowedAccounts' => collect($bucket->allowed_account_ids ?? [])
                    ->map(fn (int $id) => ['id' => $id, 'name' => $accountNames->get($id, '?')])->values(),
                'isDefault' => $bucket->is_default,
                'balance' => $funds->balance($bucket)->toFloat(),
                'available' => $funds->available($bucket)->toFloat(),
                'hasFunding' => $funds->hasFunding($bucket),
                'tracksLimit' => $bucket->tracksLimit(),
                'recentAllocations' => $recent->get($bucket->id, collect())->values(),
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

        if ($amount->isNegative() && $funds->available($bucket)->lessThan($amount->abs())) {
            return back()->withErrors(['amount' => __('buckets.not_enough_funds')]);
        }

        $funds->credit($bucket, $amount, AllocationSource::Adjustment, null, null, $request->validated('description'));

        return back()->with('success', __('buckets.adjusted'));
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
