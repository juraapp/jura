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
     * @param  Money|null  $received  income this bucket already received in its current cycle; when set, a fixed rule tops up to its amount instead of repeating on every income
     */
    public function __construct(
        public int $id,
        public ?FillRule $rule,
        public ?string $value,
        public ?Money $room,
        public ?Money $received = null,
    ) {}
}
