<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The single source of truth for "how much money is where". Account balances
 * and net worth are never stored as columns — always derived from the
 * transaction ledger so they can't drift out of sync — but are cached since
 * they're read on every page (topbar, dashboard, account list).
 */
class BalanceCalculator
{
    private const TTL = 3600;

    public function accountBalance(Account $account): Money
    {
        // Cache the raw decimal string rather than the Money object itself —
        // value objects round-tripped through the phpredis serializer can
        // come back as __PHP_Incomplete_Class; primitives are cache-portable.
        $decimal = Cache::remember(
            $this->accountCacheKey($account),
            self::TTL,
            fn () => $this->computeAccountBalance($account)->toDecimalString(),
        );

        return Money::of($decimal);
    }

    public function netWorth(User $user): Money
    {
        $decimal = Cache::remember(
            $this->netWorthCacheKey($user),
            self::TTL,
            function () use ($user) {
                return $user->accounts()
                    ->where('is_active', true)
                    ->get()
                    ->reduce(
                        fn (Money $carry, Account $account) => $carry->add($this->accountBalance($account)),
                        Money::zero(),
                    )
                    ->toDecimalString();
            },
        );

        return Money::of($decimal);
    }

    /**
     * Net worth grouped by currency, so balances in different currencies are
     * never added together as if they were the same unit.
     *
     * @return Collection<string, Money>
     */
    public function netWorthByCurrency(User $user): Collection
    {
        $byCurrency = $user->accounts()
            ->where('is_active', true)
            ->get()
            ->groupBy('currency')
            ->map(fn (Collection $accounts) => $accounts->reduce(
                fn (Money $carry, Account $account) => $carry->add($this->accountBalance($account)),
                Money::zero(),
            ))
            ->sortKeys();

        return $byCurrency->isEmpty()
            ? collect([$user->currency_default => Money::zero()])
            : $byCurrency;
    }

    public function forgetAccount(Account $account): void
    {
        Cache::forget($this->accountCacheKey($account));
        Cache::forget($this->netWorthCacheKey($account->user));
    }

    private function computeAccountBalance(Account $account): Money
    {
        $sums = $account->transactions()
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $balance = $account->initial_balance;

        $balance = $balance->add(Money::of($sums[TransactionType::Income->value] ?? 0));
        $balance = $balance->subtract(Money::of($sums[TransactionType::Expense->value] ?? 0));
        $balance = $balance->add(Money::of($sums[TransactionType::TransferIn->value] ?? 0));
        $balance = $balance->subtract(Money::of($sums[TransactionType::TransferOut->value] ?? 0));

        return $balance;
    }

    private function accountCacheKey(Account $account): string
    {
        return "account:{$account->id}:balance";
    }

    private function netWorthCacheKey(User $user): string
    {
        return "user:{$user->id}:net-worth";
    }
}
