<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * How a bucket's current window (its cycle, or the calendar month) is expected to end: income it receives in it,
 * what it holds at the end before carry-over, what it gives away when the cycle closes, what other buckets pass to
 * it, and what it holds once all of that is done.
 */
final readonly class CycleOutcome
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public Money $provisional,
        public Money $projectedEnd,
        public Money $released,
        public Money $receivedFromOthers,
        public Money $close,
    ) {}
}
