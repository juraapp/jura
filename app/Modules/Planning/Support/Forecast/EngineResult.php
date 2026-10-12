<?php

namespace App\Modules\Planning\Support\Forecast;

use App\Modules\Shared\Support\Money;

final readonly class EngineResult
{
    /**
     * @param  list<LineResult>  $lines  in scenario order
     * @param  array<int, Money>  $endBalances  bucket id => funds left at the end of the horizon
     */
    public function __construct(
        public array $lines,
        public array $endBalances,
    ) {}
}
