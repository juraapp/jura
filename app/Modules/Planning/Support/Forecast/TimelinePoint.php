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
     * @param  array<int, Money>  $envelopes  funded bucket id => envelope
     */
    public function __construct(
        public CarbonImmutable $date,
        public Money $cash,
        public Money $earmarked,
        public Money $free,
        public array $envelopes,
    ) {}
}
