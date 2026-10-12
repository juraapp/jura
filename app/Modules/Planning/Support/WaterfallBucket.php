<?php

namespace App\Modules\Planning\Support;

use App\Modules\Planning\Enums\FillRule;
use App\Modules\Shared\Support\Money;

/**
 * A bucket as the waterfall sees it: its rule and how much more it can take.
 */
final readonly class WaterfallBucket
{
    /**
     * @param  Money|null  $room  how much it can still receive before reaching its target (null = no limit)
     */
    public function __construct(
        public int $id,
        public ?FillRule $rule,
        public ?string $value,
        public ?Money $room,
    ) {}
}
