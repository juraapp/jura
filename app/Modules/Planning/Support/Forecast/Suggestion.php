<?php

namespace App\Modules\Planning\Support\Forecast;

/**
 * One proposed purchase order and how it plays out.
 */
final readonly class Suggestion
{
    /**
     * @param  string  $strategy  best | importance | cheapest | deadline | affordable
     * @param  list<int>  $order  wish-list item ids in purchase order
     */
    public function __construct(
        public string $strategy,
        public array $order,
        public EngineResult $result,
        public float $score,
    ) {}
}
