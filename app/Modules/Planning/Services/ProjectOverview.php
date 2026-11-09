<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Enums\ForecastView;
use App\Modules\Planning\Enums\WishlistStatus;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Models\Project;
use App\Modules\Planning\Models\WishlistItem;
use App\Modules\Planning\Support\FundingSplit;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The numbers of every project (and of the items that are in none), per currency: what is planned (wanted items),
 * what it cost so far (what the purchases really cost), how much of the plan its buckets cover, what is left of the
 * budget, when the last wanted item could be bought and what to save each month to make the target date.
 *
 * Two rules keep the numbers honest. Buckets shared by several items cover them once (`FundingSplit`), and the
 * dates come from ONE planner run over all the wanted items of a currency, so projects compete for the same free
 * money instead of each assuming it has it to itself.
 */
class ProjectOverview
{
    public function __construct(
        private readonly BucketLedger $funds,
        private readonly PlannerService $planner,
        private readonly LedgerQuery $ledger,
        private readonly FundingSplit $split,
    ) {}

    /**
     * @return list<array{
     *     id: int|null, name: string|null, kind: string|null, kindLabel: string|null, color: string|null, icon: string|null,
     *     notes: string|null, currency: string|null, budget: float|null, targetDate: string|null,
     *     bucket: array{id: int, name: string}|null,
     *     counts: array{wanted: int, purchased: int, dropped: int},
     *     totals: list<array{currency: string, wanted: float, purchased: float, funded: float, fundedPercent: float, budgetLeft: float|null, completion: string|null, onTime: bool|null, neededPerMonth: float|null}>
     * }> projects by name, then the items without a project
     */
    public function index(User $user, ?ForecastView $view = null, int $months = 24): array
    {
        $view ??= ForecastView::default();

        $projects = Project::forUser($user)->with('bucket')->orderBy('name')->get();
        $items = WishlistItem::forUser($user)->with(['bucket', 'project.bucket'])->orderBy('id')->get();
        $wanted = $items->where('status', WishlistStatus::Wanted);

        $covered = $this->coverage($wanted);
        $paid = $this->ledger->amounts($user->id, $items->where('status', WishlistStatus::Purchased)->pluck('purchased_transaction_id')->filter()->values()->all());
        $dates = $this->purchaseDates($user, $wanted, $view, $months);
        $today = $user->today();

        $byProject = $items->groupBy(fn (WishlistItem $item) => $item->project_id ?? 0);

        $entries = [];
        foreach ($projects as $project) {
            $entries[] = $this->entry($project, $byProject->get($project->id, collect()), $covered, $paid, $dates, $today);
        }

        if ($byProject->has(0)) {
            $entries[] = $this->entry(null, $byProject->get(0), $covered, $paid, $dates, $today);
        }

        return $entries;
    }

    /**
     * What each wanted item's bucket covers: its own, or else its project's, used once by the items that share it.
     * The items need their `bucket` and `project.bucket` loaded.
     *
     * @param  Collection<int, WishlistItem>  $wanted
     * @return array<int, Money> by item id
     */
    public function coverage(Collection $wanted): array
    {
        $buckets = $wanted->map(fn (WishlistItem $item) => $item->fundingBucket)->filter()->unique('id')->values();
        $envelopes = $this->funds->availableMany(new EloquentCollection($buckets->all()));

        return $this->split->split($envelopes, $wanted->map(fn (WishlistItem $item) => [
            'id' => $item->id,
            'bucketId' => $item->fundingBucket?->id,
            'price' => $item->price,
            'importance' => (int) $item->importance,
        ])->values()->all());
    }

    /**
     * The date each wanted item is bought on in the planner's best order for ALL the wanted items of its currency
     * (null: not within the horizon).
     *
     * @param  Collection<int, WishlistItem>  $wanted
     * @return array<int, string|null> by item id
     */
    private function purchaseDates(User $user, Collection $wanted, ForecastView $view, int $months): array
    {
        $dates = [];

        foreach ($wanted->groupBy('currency') as $currency => $items) {
            $context = $this->planner->context($user, (string) $currency, $view, $months);
            $lines = $this->planner->lines($user, (string) $currency, $context, $items->map(fn (WishlistItem $item) => ['item_id' => $item->id])->values()->all());
            $best = $this->planner->suggest($context, $lines, 1)[0] ?? null;

            foreach ($items as $item) {
                $dates[$item->id] = null;
            }

            foreach ($best['lines'] ?? [] as $line) {
                $dates[$line['itemId']] = $line['purchaseDate'];
            }
        }

        return $dates;
    }

    /**
     * @param  Collection<int, WishlistItem>  $items  every item of the project (or of none)
     * @param  array<int, Money>  $covered
     * @param  array<int, Money>  $paid  transaction id => what the purchase cost
     * @param  array<int, string|null>  $dates
     * @return array<string, mixed>
     */
    private function entry(?Project $project, Collection $items, array $covered, array $paid, array $dates, CarbonInterface $today): array
    {
        $currencies = $items->pluck('currency')->when($project?->currency !== null, fn (Collection $all) => $all->push($project->currency))->unique()->sort()->values();

        return [
            'id' => $project?->id,
            'name' => $project?->name,
            'kind' => $project?->kind->value,
            'kindLabel' => $project?->kind->label(),
            'color' => $project?->color,
            'icon' => $project?->icon,
            'notes' => $project?->notes,
            'currency' => $project?->currency,
            'budget' => $project?->budget_amount?->toFloat(),
            'targetDate' => $project?->target_date?->toDateString(),
            'bucket' => $project?->bucket !== null ? ['id' => $project->bucket->id, 'name' => $project->bucket->name] : null,
            'counts' => [
                'wanted' => $items->where('status', WishlistStatus::Wanted)->count(),
                'purchased' => $items->where('status', WishlistStatus::Purchased)->count(),
                'dropped' => $items->where('status', WishlistStatus::Dropped)->count(),
            ],
            'totals' => $currencies->map(fn (string $currency) => $this->totals($project, $items->where('currency', $currency), $currency, $covered, $paid, $dates, $today))->all(),
        ];
    }

    /**
     * @param  Collection<int, WishlistItem>  $items  the items in one currency
     * @param  array<int, Money>  $covered
     * @param  array<int, Money>  $paid
     * @param  array<int, string|null>  $dates
     * @return array<string, mixed>
     */
    private function totals(?Project $project, Collection $items, string $currency, array $covered, array $paid, array $dates, CarbonInterface $today): array
    {
        $wanted = $items->where('status', WishlistStatus::Wanted);
        $planned = $wanted->reduce(fn (Money $sum, WishlistItem $item) => $sum->add($item->price), Money::zero());
        $spent = $items->where('status', WishlistStatus::Purchased)
            ->reduce(fn (Money $sum, WishlistItem $item) => $sum->add($paid[$item->purchased_transaction_id] ?? $item->price), Money::zero());
        $funded = $wanted->reduce(fn (Money $sum, WishlistItem $item) => $sum->add($covered[$item->id] ?? Money::zero()), Money::zero());

        $itemDates = $wanted->map(fn (WishlistItem $item) => $dates[$item->id] ?? null)->all();
        $completion = $itemDates === [] || in_array(null, $itemDates, true) ? null : max($itemDates);

        $own = $project !== null && $project->currency === $currency;
        $budget = $own ? $project->budget_amount : null;
        $target = $own ? $project->target_date : null;

        return [
            'currency' => $currency,
            'wanted' => $planned->toFloat(),
            'purchased' => $spent->toFloat(),
            'funded' => $funded->toFloat(),
            'fundedPercent' => $planned->isPositive() ? round($funded->percentageOf($planned), 1) : 0.0,
            'budgetLeft' => $budget?->subtract($spent)->subtract($planned)->toFloat(),
            'completion' => $completion,
            'onTime' => $completion !== null && $target !== null ? $completion <= $target->toDateString() : null,
            'neededPerMonth' => $target !== null ? $this->neededPerMonth($planned->subtract($funded), $today, $target) : null,
        ];
    }

    /**
     * What to put aside each month, from today until the target date, to cover what the buckets do not yet (null
     * when the target date has passed or nothing is missing).
     */
    private function neededPerMonth(Money $missing, CarbonInterface $today, CarbonInterface $target): ?float
    {
        if (! $missing->isPositive() || $target->lte($today)) {
            return null;
        }

        $months = 0;
        for ($day = $today->copy(); $day->lt($target); $day = $day->copy()->addMonthNoOverflow()) {
            $months++;
        }

        return ceil($missing->toFloat() / max(1, $months) * 100) / 100;
    }
}
