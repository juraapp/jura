<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * One planned movement of on-budget money on its effective date (an overdue plan counts as today).
 */
final readonly class EngineFlow
{
    /**
     * @param  int  $seq  order among flows on the same date
     * @param  Money  $amount  positive for income and expenses; signed for a boundary flow (+ into the budget)
     * @param  array<int, Money>  $overrides  a planned income's explicit amounts per bucket id
     * @param  string|null  $ref  where it comes from (for display and debugging)
     */
    public function __construct(
        public CarbonImmutable $date,
        public int $seq,
        public FlowKind $kind,
        public Money $amount,
        public ?int $accountId = null,
        public ?int $categoryId = null,
        public array $overrides = [],
        public ?string $ref = null,
    ) {}

    /**
     * How the flow changes on-budget cash.
     */
    public function cashEffect(): Money
    {
        return $this->kind === FlowKind::Expense ? $this->amount->negate() : $this->amount;
    }
}
