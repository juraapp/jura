<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;

/**
 * The planned incomes a run counted and where they went: into buckets, or left in ready to assign.
 */
final readonly class IncomeSummary
{
    public function __construct(
        public int $counted,
        public Money $amount,
        public Money $allocated,
        public Money $leftFree,
    ) {}
}
