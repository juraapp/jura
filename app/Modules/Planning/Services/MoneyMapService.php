<?php

namespace App\Modules\Planning\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Planning\Contracts\MoneyMap;
use App\Modules\Planning\Models\Bucket;
use App\Modules\Planning\Support\MoneyMap as MoneyMapCalculator;
use App\Modules\Planning\Support\MoneyMapRow;
use App\Modules\Shared\Support\Money;

/**
 * Feeds the pure money-map calculator with real balances and bucket envelopes.
 */
class MoneyMapService implements MoneyMap
{
    public function __construct(
        private readonly Balances $balances,
        private readonly BucketLedger $funds,
    ) {}

    public function summary(User $user): array
    {
        return array_map(fn (MoneyMapRow $row) => [
            'currency' => $row->currency,
            'inAccounts' => $row->inAccounts,
            'inBuckets' => $row->inBuckets,
            'readyToAssign' => $row->readyToAssign,
            'overAssigned' => $row->isOverAssigned(),
        ], $this->rows($user));
    }

    public function readyToAssign(User $user, string $currency): Money
    {
        return ($this->rows($user, $currency)[$currency] ?? null)?->readyToAssign ?? Money::zero();
    }

    /**
     * @return array<string, MoneyMapRow>
     */
    private function rows(User $user, ?string $currency = null): array
    {
        $accounts = $this->balances->budgetBalances($user)
            ->when($currency !== null, fn ($rows) => $rows->where('currency', $currency));

        $buckets = Bucket::forUser($user)
            ->when($currency !== null, fn ($query) => $query->where('currency', $currency))
            ->where('is_default', false)
            ->get()
            ->filter(fn (Bucket $bucket) => $this->funds->isFunded($bucket))
            ->map(fn (Bucket $bucket) => ['currency' => $bucket->currency, 'envelope' => $this->funds->envelope($bucket)]);

        return MoneyMapCalculator::summarize($accounts->all(), $buckets->all());
    }
}
