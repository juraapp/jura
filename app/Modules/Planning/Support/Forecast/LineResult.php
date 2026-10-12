<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

final readonly class LineResult
{
    public function __construct(
        public int $itemId,
        public string $name,
        public Money $price,
        public ?CarbonImmutable $purchaseDate,
        public ?int $delayDays,
        public Money $shortfall,
        public ?string $blocked,
        public bool $late,
        public int $importance,
    ) {}
}
