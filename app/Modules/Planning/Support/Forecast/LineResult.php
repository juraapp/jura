<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;

final readonly class LineResult
{
    public Money $fromBucket;

    public Money $fromFree;

    /**
     * @param  Money|null  $fromBucket  what its bucket pays (when bought)
     * @param  Money|null  $fromFree  what ready to assign pays (when bought)
     * @param  string|null  $limitedBy  why it is not bought: free (not enough ready to assign) | cash (a later planned expense needs the money)
     * @param  bool  $parked  moved to the end because it could not be bought in time
     * @param  CarbonImmutable|null  $freeDipsOn  the first later day ready to assign goes below zero after this purchase
     * @param  list<string>  $warnings
     */
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
        ?Money $fromBucket = null,
        ?Money $fromFree = null,
        public ?string $limitedBy = null,
        public bool $parked = false,
        public ?CarbonImmutable $freeDipsOn = null,
        public array $warnings = [],
    ) {
        $this->fromBucket = $fromBucket ?? Money::zero();
        $this->fromFree = $fromFree ?? Money::zero();
    }

    /**
     * The same result, marked as moved to the end of the order.
     */
    public function parked(): self
    {
        return new self(
            $this->itemId, $this->name, $this->price, $this->purchaseDate, $this->delayDays, $this->shortfall, $this->blocked,
            $this->late, $this->importance, $this->fromBucket, $this->fromFree, $this->limitedBy, true, $this->freeDipsOn, $this->warnings,
        );
    }
}
