<?php

namespace App\Modules\Planning\Support;

use App\Modules\Shared\Support\Money;

/**
 * One income split across the buckets: the single rule both the real income allocator and every forecast use.
 * Buckets the income may not fill are left out, explicit per-bucket amounts (a planned income's overrides) win
 * over the rules for the buckets that remain, and what no bucket claims is the leftover, which stays ready to
 * assign. Without overrides and without any fill rule the whole income is leftover. Pure: no I/O.
 */
final class IncomeStep
{
    public function __construct(private readonly Waterfall $waterfall = new Waterfall) {}

    /**
     * @param  list<IncomeStepBucket>  $buckets  in priority order
     * @param  array<int, Money>  $overrides  bucket id => explicit amount
     * @return array{allocations: array<int, Money>, leftover: Money}
     */
    public function split(Money $amount, array $buckets, array $overrides = []): array
    {
        $eligible = array_values(array_filter($buckets, fn (IncomeStepBucket $bucket) => $bucket->eligible));
        $ids = array_flip(array_map(fn (IncomeStepBucket $bucket) => $bucket->id, $eligible));
        $overrides = array_intersect_key($overrides, $ids);

        $hasRule = false;
        foreach ($eligible as $bucket) {
            $hasRule = $hasRule || $bucket->rule !== null;
        }

        if ($overrides === [] && ! $hasRule) {
            return ['allocations' => [], 'leftover' => $amount->isPositive() ? $amount : Money::zero()];
        }

        $inputs = array_map(
            fn (IncomeStepBucket $bucket) => new WaterfallBucket($bucket->id, $bucket->rule, $bucket->value, $bucket->room(), $bucket->receivedInCycle),
            $eligible,
        );

        return $this->waterfall->distribute($amount, $inputs, $overrides);
    }
}
