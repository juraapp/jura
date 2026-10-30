<?php

namespace App\Modules\Planning\Support;

use App\Modules\Shared\Support\Money;

/**
 * The envelope identity, per currency: ready to assign = money in on-budget accounts − money in funded bucket
 * envelopes. Sums are signed and never clamped (an overspent envelope or a card in debt counts as negative), so
 * in accounts = in buckets + ready to assign always holds. Pure: no I/O.
 */
final class MoneyMap
{
    /**
     * @param  iterable<array{currency: string, balance: Money}>  $accounts  on-budget accounts
     * @param  iterable<array{currency: string, envelope: Money}>  $buckets  funded buckets
     * @return array<string, MoneyMapRow> keyed by currency, sorted by currency
     */
    public static function summarize(iterable $accounts, iterable $buckets): array
    {
        $inAccounts = [];
        $inBuckets = [];

        foreach ($accounts as $account) {
            $inAccounts[$account['currency']] = ($inAccounts[$account['currency']] ?? Money::zero())->add($account['balance']);
        }

        foreach ($buckets as $bucket) {
            $inBuckets[$bucket['currency']] = ($inBuckets[$bucket['currency']] ?? Money::zero())->add($bucket['envelope']);
        }

        $currencies = array_unique([...array_keys($inAccounts), ...array_keys($inBuckets)]);
        sort($currencies);

        $rows = [];
        foreach ($currencies as $currency) {
            $real = $inAccounts[$currency] ?? Money::zero();
            $earmarked = $inBuckets[$currency] ?? Money::zero();
            $rows[$currency] = new MoneyMapRow($currency, $real, $earmarked, $real->subtract($earmarked));
        }

        return $rows;
    }
}
