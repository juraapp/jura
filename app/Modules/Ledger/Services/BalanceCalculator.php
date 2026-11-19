<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Contracts\Balances;
use App\Modules\Ledger\Enums\TransactionType;
use App\Modules\Ledger\Models\Account;
use App\Modules\Shared\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The single source of truth for "how much money is where". Account balances
 * and net worth are never stored as columns — always derived from the
 * transaction ledger so they can't drift out of sync — but are cached since
 * they're read on every page (topbar, dashboard, account list).
 */
class BalanceCalculator implements Balances
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

    /**
     * Read on every page (the money map), so it takes a constant number of queries: cached balances in one cache
     * read, and the missing ones in one grouped query (then cached like accountBalance does).
     */
    public function budgetBalances(User $user): Collection
    {
        $accounts = $user->accounts()
            ->where('is_active', true)
            ->where('on_budget', true)
            ->orderBy('id')
            ->get();

        $balances = $this->balancesOf($accounts);

        return $accounts
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'currency' => $account->currency,
                'balance' => $balances[$account->id],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array<int, Money> keyed by account id
     */
    private function balancesOf(Collection $accounts): array
    {
        if ($accounts->isEmpty()) {
            return [];
        }

        $keys = $accounts->mapWithKeys(fn (Account $account) => [$account->id => $this->accountCacheKey($account)]);
        $cached = Cache::many($keys->values()->all());

        $balances = [];
        $missing = collect();
        foreach ($accounts as $account) {
            $decimal = $cached[$keys[$account->id]] ?? null;
            $decimal === null ? $missing->push($account) : $balances[$account->id] = Money::of($decimal);
        }

        if ($missing->isNotEmpty()) {
            $sums = DB::table('transactions')
                ->whereIn('account_id', $missing->pluck('id')->all())
                ->whereNull('deleted_at')
                ->selectRaw('account_id, type, COALESCE(SUM(amount), 0) as total')
                ->groupBy('account_id', 'type')
                ->get()
                ->groupBy('account_id');

            $fresh = [];
            foreach ($missing as $account) {
                $balance = $this->fromSums($account, collect($sums->get($account->id, []))->pluck('total', 'type'));
                $balances[$account->id] = $balance;
                $fresh[$keys[$account->id]] = $balance->toDecimalString();
            }

            Cache::putMany($fresh, self::TTL);
        }

        return $balances;
    }

    public function yieldBearing(User $user): Collection
    {
        return $user->accounts()
            ->where('is_active', true)
            ->where('yield_rate', '>', 0)
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'currency' => $account->currency,
                'rate' => (string) $account->yield_rate,
                'compounding' => $account->yield_compounding->value,
                'balance' => $this->accountBalance($account),
            ])
            ->values();
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

    /**
     * Forgets the cached balance now (so the rest of this request reads fresh), again once the open DB
     * transaction commits (so a concurrent read made before the commit can't stay cached), and again if it
     * rolls back (so a balance read inside the failed transaction, e.g. by a listener, can't stay cached).
     */
    public function forgetAccount(Account $account): void
    {
        $key = $this->accountCacheKey($account);

        Cache::forget($key);
        DB::afterCommit(fn () => Cache::forget($key));
        DB::afterRollBack(fn () => Cache::forget($key));
    }

    private function computeAccountBalance(Account $account): Money
    {
        $sums = $account->transactions()
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return $this->fromSums($account, $sums);
    }

    /**
     * @param  Collection<string, mixed>  $sums  total per transaction type
     */
    private function fromSums(Account $account, Collection $sums): Money
    {
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
}
