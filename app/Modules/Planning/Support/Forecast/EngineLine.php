<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

/**
 * One purchase in a scenario: an item, paid from its bucket first (when it has one) and the rest from ready to
 * assign.
 */
final readonly class EngineLine
{
    /**
     * @param  int|null  $bucketId  the bucket it is paid from first (the item's, or its project's), null = ready to assign only
     * @param  string|null  $blocked  a reason the item can never be bought (accounts | off_budget), or null
     * @param  list<string>  $warnings  things to tell the user about the line (cap_below_price)
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
        public ?int $projectId = null,
        public array $warnings = [],
    ) {}
}
