<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * One purchase in a scenario: an item to buy from a bucket (null = the default bucket).
 */
final readonly class EngineLine
{
    /**
     * @param  string|null  $blocked  a reason the item can never be bought (for example no allowed account), or null
     */
    public function __construct(
        public int $itemId,
        public string $name,
        public Money $price,
        public ?int $bucketId,
        public ?CarbonImmutable $notBefore,
        public ?CarbonImmutable $desiredBy,
        public int $importance,
        public ?string $blocked = null,
    ) {}
}
