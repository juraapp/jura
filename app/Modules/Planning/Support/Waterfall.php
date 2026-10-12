<?php

namespace App\Modules\Planning\Support;

use App\Modules\Planning\Enums\FillRule;
use App\Modules\Shared\Support\Money;

/**
 * Splits one income across buckets. Buckets are offered the income in the
 * order given (priority order); each takes what its rule asks for, limited by
 * its remaining room and by what is still unclaimed. Pure: no I/O.
 */
final class Waterfall
{
    /**
     * @param  list<WaterfallBucket>  $buckets  in priority order
     * @param  array<int, Money>  $overrides  explicit amounts per bucket id; when given, rules are ignored
     * @return array{allocations: array<int, Money>, leftover: Money}
     */
    public function distribute(Money $income, array $buckets, array $overrides = []): array
    {
        if (! $income->isPositive()) {
            return ['allocations' => [], 'leftover' => Money::zero()];
        }

        $remaining = $income;
        $allocations = [];

        if ($overrides !== []) {
            foreach ($overrides as $bucketId => $amount) {
                $granted = $this->smaller($amount, $remaining);
                if ($granted->isPositive()) {
                    $allocations[$bucketId] = $granted;
                    $remaining = $remaining->subtract($granted);
                }
            }

            return ['allocations' => $allocations, 'leftover' => $remaining];
        }

        foreach ($buckets as $bucket) {
            if ($bucket->rule === null) {
                continue;
            }

            $wanted = match ($bucket->rule) {
                FillRule::Fixed => $this->fixedAmount($bucket),
                FillRule::Percent => $income->multiply($bucket->value ?? 0)->divide(100),
                FillRule::PercentOfRemaining => $remaining->multiply($bucket->value ?? 0)->divide(100),
                FillRule::FillToTarget => $bucket->room ?? Money::zero(),
            };

            if ($bucket->room !== null) {
                $wanted = $this->smaller($wanted, $bucket->room);
            }

            $granted = $this->smaller($wanted, $remaining);
            if ($granted->isPositive()) {
                $allocations[$bucket->id] = $granted;
                $remaining = $remaining->subtract($granted);
            }
        }

        return ['allocations' => $allocations, 'leftover' => $remaining];
    }

    private function fixedAmount(WaterfallBucket $bucket): Money
    {
        $amount = Money::of($bucket->value ?? 0);

        if ($bucket->received === null) {
            return $amount;
        }

        $missing = $amount->subtract($bucket->received);

        return $missing->isPositive() ? $missing : Money::zero();
    }

    private function smaller(Money $a, Money $b): Money
    {
        return $a->lessThan($b) ? $a : $b;
    }
}
