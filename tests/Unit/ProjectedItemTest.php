<?php

namespace Tests\Unit;

use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Planning\Enums\Certainty;
use App\Modules\Planning\Support\ProjectedItem;
use App\Modules\Shared\Support\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ProjectedItemTest extends TestCase
{
    private function item(string $date): ProjectedItem
    {
        return new ProjectedItem(1, null, TransactionType::Expense, CarbonImmutable::parse($date), Money::of(10), 'COP', null, null, null, Certainty::Confirmed, null, false);
    }

    public function test_an_overdue_item_moves_today_and_a_later_one_keeps_its_date(): void
    {
        $today = CarbonImmutable::parse('2026-11-10 15:00');

        $this->assertSame('2026-11-10', $this->item('2026-10-31')->effectiveDate($today)->toDateString());
        $this->assertSame('2026-11-10', $this->item('2026-11-10')->effectiveDate($today)->toDateString());
        $this->assertSame('2026-12-01', $this->item('2026-12-01')->effectiveDate($today)->toDateString());
    }
}
