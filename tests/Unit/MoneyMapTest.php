<?php

namespace Tests\Unit;

use App\Modules\Planning\Support\MoneyMap;
use App\Modules\Shared\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyMapTest extends TestCase
{
    /**
     * @return array{currency: string, balance: Money}
     */
    private function account(string $currency, string|int $balance): array
    {
        return ['currency' => $currency, 'balance' => Money::of($balance)];
    }

    /**
     * @return array{currency: string, envelope: Money}
     */
    private function bucket(string $currency, string|int $envelope): array
    {
        return ['currency' => $currency, 'envelope' => Money::of($envelope)];
    }

    public function test_nothing_gives_an_empty_map(): void
    {
        $this->assertSame([], MoneyMap::summarize([], []));
    }

    public function test_what_is_not_in_a_bucket_is_ready_to_assign(): void
    {
        $row = MoneyMap::summarize(
            [$this->account('COP', 600), $this->account('COP', 400)],
            [$this->bucket('COP', 700)],
        )['COP'];

        $this->assertSame('1000.00', $row->inAccounts->toDecimalString());
        $this->assertSame('700.00', $row->inBuckets->toDecimalString());
        $this->assertSame('300.00', $row->readyToAssign->toDecimalString());
        $this->assertFalse($row->isOverAssigned());
    }

    public function test_more_in_buckets_than_in_accounts_is_over_assigned(): void
    {
        $row = MoneyMap::summarize([$this->account('COP', 100)], [$this->bucket('COP', 250)])['COP'];

        $this->assertSame('-150.00', $row->readyToAssign->toDecimalString());
        $this->assertTrue($row->isOverAssigned());
    }

    public function test_currencies_never_mix(): void
    {
        $map = MoneyMap::summarize(
            [$this->account('USD', 10), $this->account('COP', 1000)],
            [$this->bucket('COP', 400), $this->bucket('USD', 4)],
        );

        $this->assertSame(['COP', 'USD'], array_keys($map));
        $this->assertSame('600.00', $map['COP']->readyToAssign->toDecimalString());
        $this->assertSame('6.00', $map['USD']->readyToAssign->toDecimalString());
    }

    public function test_a_bucket_currency_without_an_account_is_fully_over_assigned(): void
    {
        $row = MoneyMap::summarize([], [$this->bucket('EUR', 50)])['EUR'];

        $this->assertSame('0.00', $row->inAccounts->toDecimalString());
        $this->assertSame('-50.00', $row->readyToAssign->toDecimalString());
    }

    public function test_an_overspent_envelope_counts_as_negative(): void
    {
        $row = MoneyMap::summarize([$this->account('COP', 500)], [$this->bucket('COP', 300), $this->bucket('COP', -100)])['COP'];

        $this->assertSame('200.00', $row->inBuckets->toDecimalString());
        $this->assertSame('300.00', $row->readyToAssign->toDecimalString());
    }

    public function test_a_credit_card_in_debt_lowers_what_is_ready(): void
    {
        $row = MoneyMap::summarize([$this->account('COP', 1000), $this->account('COP', -300)], [$this->bucket('COP', 200)])['COP'];

        $this->assertSame('700.00', $row->inAccounts->toDecimalString());
        $this->assertSame('500.00', $row->readyToAssign->toDecimalString());
    }
}
