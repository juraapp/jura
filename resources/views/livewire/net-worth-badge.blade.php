<div class="hidden flex-col leading-tight sm:flex">
    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total net worth') }}</span>
    @foreach ($netWorthByCurrency as $currency => $amount)
        <span class="text-sm font-semibold text-gray-900 dark:text-white">
            <x-money :amount="$amount" :currency="$currency" />
        </span>
    @endforeach
</div>
