<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates, updates and deletes buckets, keeping the invariant of one spending
 * bucket per category, currency and cycle.
 */
class BucketManager
{
    public function __construct(
        private readonly LedgerQuery $ledger,
        private readonly BucketLedger $buckets,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated request data
     */
    public function save(User $user, array $data, ?Bucket $bucket = null): Bucket
    {
        return DB::transaction(function () use ($user, $data, $bucket) {
            $kind = BucketKind::from($data['kind']);
            $fillRule = isset($data['fill_rule']) ? FillRule::from($data['fill_rule']) : null;
            $carryOver = CarryOverPolicy::from($data['carry_over'] ?? CarryOverPolicy::Carry->value);

            $attributes = [
                'name' => $data['name'] ?? $this->ledger->categoryName($user->id, (int) ($data['category_id'] ?? 0)) ?? __('buckets.untitled'),
                'kind' => $kind,
                'currency' => $data['currency'],
                'priority' => $data['priority'] ?? $bucket?->priority ?? 100,
                'category_id' => $kind === BucketKind::Spending ? $data['category_id'] : null,
                'cycle' => $data['cycle'] ?? null,
                'cycle_every' => isset($data['cycle']) ? (int) ($data['cycle_every'] ?? 1) : 1,
                'cycle_anchor' => isset($data['cycle']) ? ($data['cycle_anchor'] ?? null) : null,
                'cycle_goal' => isset($data['cycle']) && $kind === BucketKind::Savings && isset($data['cycle_goal']) ? Money::of($data['cycle_goal']) : null,
                'cap_amount' => isset($data['cap_amount']) ? Money::of($data['cap_amount']) : null,
                'fill_rule' => $fillRule,
                'fill_value' => $fillRule !== null && $fillRule !== FillRule::FillToTarget ? $data['fill_value'] : null,
                'step_up_value' => $fillRule !== null && $fillRule !== FillRule::FillToTarget && ! empty($data['step_up_after_bucket_id']) && isset($data['step_up_value']) ? $data['step_up_value'] : null,
                'step_up_after_bucket_id' => $fillRule !== null && $fillRule !== FillRule::FillToTarget && isset($data['step_up_value']) && ! empty($data['step_up_after_bucket_id']) ? (int) $data['step_up_after_bucket_id'] : null,
                'completes_on_purchase' => $kind === BucketKind::Savings && (bool) ($data['completes_on_purchase'] ?? false),
                'carry_over' => $carryOver,
                'overflow_bucket_id' => $carryOver === CarryOverPolicy::Overflow ? ($data['overflow_bucket_id'] ?? null) : null,
                'allowed_account_ids' => ! empty($data['allowed_account_ids']) ? array_map('intval', $data['allowed_account_ids']) : null,
            ];

            $bucket ??= $this->matchingSpendingBucket($user, $kind, $attributes['category_id'], $attributes['currency'], $attributes['cycle']);

            if ($bucket === null) {
                $bucket = new Bucket($attributes + ['tracking_started_on' => $user->today()->toDateString()]);
                $bucket->user_id = $user->id;
                $bucket->save();
            } else {
                $bucket->update($attributes);
            }

            if (array_key_exists('opening_balance', $data)) {
                $this->buckets->setOpeningBalance($bucket, Money::of($data['opening_balance'] ?? 0));
            }

            return $bucket;
        });
    }

    /**
     * How much saving this data would move out of ready to assign through the bucket's opening balance: the change
     * in the opening amount, less the category spending a first opening brings into the envelope. Saving a new
     * spending bucket for a category that already has one updates that bucket, so its opening counts as the base.
     *
     * @param  array<string, mixed>  $data  validated request data
     */
    public function openingEffect(User $user, array $data, ?Bucket $bucket = null): Money
    {
        if (! array_key_exists('opening_balance', $data)) {
            return Money::zero();
        }

        $kind = BucketKind::from($data['kind']);
        $categoryId = $kind === BucketKind::Spending ? ($data['category_id'] ?? null) : null;
        $target = $bucket ?? $this->matchingSpendingBucket($user, $kind, $categoryId, (string) $data['currency'], $data['cycle'] ?? null);

        $probe = $target !== null ? clone $target : new Bucket(['tracking_started_on' => $user->today()->toDateString()]);
        $probe->user_id = $user->id;
        $probe->kind = $kind;
        $probe->category_id = $categoryId;
        $probe->currency = (string) $data['currency'];

        $amount = Money::of($data['opening_balance'] ?? 0);
        $current = $target !== null ? $this->buckets->openingBalance($target) : Money::zero();

        return $current->isZero()
            ? $this->buckets->assignEffect($probe, $amount)
            : $amount->subtract($current);
    }

    /**
     * The spending bucket already tracking this category, currency and cycle (there is at most one).
     */
    private function matchingSpendingBucket(User $user, BucketKind $kind, mixed $categoryId, string $currency, mixed $cycle): ?Bucket
    {
        if ($kind !== BucketKind::Spending) {
            return null;
        }

        return Bucket::forUser($user)
            ->where('category_id', $categoryId)
            ->where('currency', strtoupper($currency))
            ->where('cycle', $cycle)
            ->first();
    }

    public function delete(Bucket $bucket): void
    {
        // Its allocations go with it, so whatever it held is ready to assign again.
        $bucket->delete();
    }
}
