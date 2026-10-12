<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\LedgerQuery;
use App\Modules\Planning\Enums\BucketKind;
use App\Modules\Planning\Enums\CarryOverPolicy;
use App\Modules\Planning\Enums\FillRule;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates, updates and deletes buckets, keeping the invariants: one default
 * bucket per currency, and one spending bucket per category, currency and cycle.
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
                'name' => $data['name'] ?? $this->ledger->categoryName((int) ($data['category_id'] ?? 0)) ?? __('buckets.untitled'),
                'kind' => $kind,
                'currency' => $data['currency'],
                'priority' => $data['priority'] ?? $bucket?->priority ?? 100,
                'category_id' => $kind === BucketKind::Spending ? $data['category_id'] : null,
                'cycle' => $data['cycle'] ?? null,
                'cap_amount' => isset($data['cap_amount']) ? Money::of($data['cap_amount']) : null,
                'fill_rule' => $fillRule,
                'fill_value' => $fillRule !== null && $fillRule !== FillRule::FillToTarget ? $data['fill_value'] : null,
                'carry_over' => $carryOver,
                'overflow_bucket_id' => $carryOver === CarryOverPolicy::Overflow ? ($data['overflow_bucket_id'] ?? null) : null,
                'allowed_account_ids' => ! empty($data['allowed_account_ids']) ? array_map('intval', $data['allowed_account_ids']) : null,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ];

            if ($bucket === null && $kind === BucketKind::Spending) {
                $bucket = Bucket::forUser($user)
                    ->where('category_id', $attributes['category_id'])
                    ->where('currency', strtoupper($attributes['currency']))
                    ->where('cycle', $attributes['cycle'])
                    ->first();
            }

            if ($bucket === null) {
                $bucket = new Bucket($attributes + ['tracking_started_on' => Carbon::today()->toDateString()]);
                $bucket->user_id = $user->id;
                $bucket->save();
            } else {
                $bucket->update($attributes);
            }

            if ($bucket->is_default) {
                Bucket::forUser($user)
                    ->where('currency', $bucket->currency)
                    ->whereKeyNot($bucket->id)
                    ->update(['is_default' => false]);
            }

            return $bucket;
        });
    }

    public function delete(Bucket $bucket): void
    {
        DB::transaction(function () use ($bucket) {
            $this->buckets->releaseFunds($bucket);
            $bucket->delete();
        });
    }
}
