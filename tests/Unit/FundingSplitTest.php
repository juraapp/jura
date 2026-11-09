<?php

namespace Tests\Unit;

use App\Modules\Planning\Support\FundingSplit;
use App\Modules\Shared\Support\Money;
use PHPUnit\Framework\TestCase;

class FundingSplitTest extends TestCase
{
    /**
     * @param  array<int, Money>  $covered
     * @return array<int, string>
     */
    private function strings(array $covered): array
    {
        return array_map(fn (Money $money) => $money->toDecimalString(), $covered);
    }

    private function item(int $id, ?int $bucketId, int $price, int $importance = 3): array
    {
        return ['id' => $id, 'bucketId' => $bucketId, 'price' => Money::of($price), 'importance' => $importance];
    }

    public function test_items_sharing_a_bucket_use_its_money_once_the_most_wanted_first(): void
    {
        // The bucket holds 100. A (80, wanted 5) takes 80, B (60, wanted 3) gets the 20 left: 100 covered, not 140.
        $covered = (new FundingSplit)->split([1 => Money::of(100)], [$this->item(2, 1, 60, 3), $this->item(1, 1, 80, 5)]);

        $this->assertSame([1 => '80.00', 2 => '20.00'], $this->strings($covered));
    }

    public function test_equally_wanted_items_are_served_oldest_first(): void
    {
        $covered = (new FundingSplit)->split([1 => Money::of(100)], [$this->item(7, 1, 70), $this->item(3, 1, 70)]);

        $this->assertSame([3 => '70.00', 7 => '30.00'], $this->strings($covered));
    }

    public function test_buckets_are_independent_of_each_other(): void
    {
        $covered = (new FundingSplit)->split(
            [1 => Money::of(50), 2 => Money::of(500)],
            [$this->item(1, 1, 80), $this->item(2, 2, 80)],
        );

        $this->assertSame([1 => '50.00', 2 => '80.00'], $this->strings($covered));
    }

    public function test_an_item_without_a_bucket_an_unknown_bucket_or_an_overspent_bucket_is_covered_by_nothing(): void
    {
        $covered = (new FundingSplit)->split(
            [1 => Money::of(-30)],
            [$this->item(1, null, 80), $this->item(2, 9, 80), $this->item(3, 1, 80)],
        );

        $this->assertSame([1 => '0.00', 2 => '0.00', 3 => '0.00'], $this->strings($covered));
    }

    public function test_a_bucket_with_more_than_enough_covers_every_item_in_full(): void
    {
        $covered = (new FundingSplit)->split([1 => Money::of(1000)], [$this->item(1, 1, 80), $this->item(2, 1, 60)]);

        $this->assertSame([1 => '80.00', 2 => '60.00'], $this->strings($covered));
    }
}
