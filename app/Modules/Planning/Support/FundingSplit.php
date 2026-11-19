<?php

namespace App\Modules\Planning\Support;

use App\Modules\Shared\Support\Money;

/**
 * How much of each wish-list item its bucket covers when several items share one bucket: the bucket's money is
 * used once, by the items in order of importance (the most wanted first, then the oldest), so two items on the
 * same bucket never both count the same money. Pure: no I/O.
 */
final class FundingSplit
{
    /**
     * @param  array<int, Money>  $envelopes  what each bucket holds, by bucket id (a negative envelope covers nothing)
     * @param  list<array{id: int, bucketId: int|null, price: Money, importance: int}>  $items
     * @return array<int, Money> the covered amount of every item, by item id (zero for an item with no bucket)
     */
    public function split(array $envelopes, array $items): array
    {
        $covered = [];
        $remaining = [];
        foreach ($envelopes as $bucketId => $envelope) {
            $remaining[$bucketId] = $envelope->isPositive() ? $envelope : Money::zero();
        }

        usort($items, fn (array $a, array $b) => [$b['importance'], $a['id']] <=> [$a['importance'], $b['id']]);

        foreach ($items as $item) {
            $left = $item['bucketId'] !== null ? ($remaining[$item['bucketId']] ?? Money::zero()) : Money::zero();
            $take = $left->lessThan($item['price']) ? $left : $item['price'];

            $covered[$item['id']] = $take;
            if ($item['bucketId'] !== null && isset($remaining[$item['bucketId']])) {
                $remaining[$item['bucketId']] = $left->subtract($take);
            }
        }

        return $covered;
    }
}
