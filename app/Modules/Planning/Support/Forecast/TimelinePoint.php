<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * The state at the end of a simulated day: on-budget cash, the money funded envelopes hold, and ready to assign
 * (cash minus earmarked).
 */
final readonly class TimelinePoint
{
    /**
     * @param  array<int, Money>  $balances  bucket id => envelope (funded or not)
     * @param  array<int, bool>  $funded  bucket id => whether its envelope is earmarked money
     */
    public function __construct(
        public CarbonImmutable $date,
        public Money $cash,
        public Money $earmarked,
        public Money $free,
        private array $balances = [],
        private array $funded = [],
    ) {}

    /**
     * What every funded bucket holds.
     *
     * @return array<int, Money> bucket id => envelope
     */
    public function envelopes(): array
    {
        return array_filter($this->balances, fn (int $id) => $this->funded[$id] ?? false, ARRAY_FILTER_USE_KEY);
    }
}
