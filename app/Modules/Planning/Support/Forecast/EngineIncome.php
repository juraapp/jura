<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

final readonly class EngineIncome
{
    public function __construct(
        public CarbonImmutable $date,
        public Money $amount,
        public ?int $accountId,
    ) {}
}
