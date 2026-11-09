<script setup>
import logoIcon from '../../img/logo-icon.png';
import { login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

const year = new Date().getFullYear();

const chips = [
    { key: 'coffee', position: 'left-2 top-6', path: 'M4 8h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V8Zm12 1h2a2 2 0 0 1 0 4h-2M8 2v3m4-3v3' },
    { key: 'delivery', position: 'left-0 bottom-10', path: 'M4 11h16a1 1 0 0 0-1-4H5a1 1 0 0 0-1 4Zm0 3h16m-15 0 .5 3a2 2 0 0 0 2 1.5h9a2 2 0 0 0 2-1.5l.5-3' },
    { key: 'streaming', position: 'right-2 top-6', path: 'M3 6h18v12H3V6Zm7 3 5 3-5 3V9Z' },
    { key: 'transport', position: 'right-0 bottom-10', path: 'M5 15V9l2-4h10l2 4v6M5 15h14M5 15v3m14-3v3M8 12h.01M16 12h.01' },
];

const coins = [
    'left-[40%] top-2 rotate-[-15deg]',
    'left-[58%] top-8 rotate-12',
    'left-[28%] top-16 rotate-[-25deg]',
    'left-[46%] top-24 rotate-6',
];

const features = [
    { key: 'identify', path: 'M5 20V12m5 8V6m5 14V9m5 11V3' },
    { key: 'control', path: 'M12 3v9h9a9 9 0 1 1-9-9Zm3-1a9 9 0 0 1 7 7h-7V2Z' },
    { key: 'reach', path: 'M12 3a9 9 0 1 0 9 9m-9-5a4 4 0 1 0 4 4m-4-1 8-8m0 0v4m0-4h-4' },
];
</script>

<template>
    <Head :title="$t('ui.welcome.tagline')" />

    <div class="relative min-h-screen overflow-hidden bg-primary-50/40 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
        <!-- Decorative shapes -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-primary-200/60 dark:bg-primary-900/40"></div>
            <div class="absolute -left-10 top-1/3 h-96 w-40 rounded-r-[5rem] bg-primary-200/50 dark:bg-primary-900/30"></div>
            <div class="absolute -right-20 top-0 h-80 w-80 rotate-12 rounded-bl-[8rem] bg-primary-900 dark:bg-primary-950"></div>
            <div class="absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-primary-200/60 dark:bg-primary-900/40"></div>
            <div class="absolute -bottom-24 -left-16 h-64 w-72 rotate-12 rounded-tr-[6rem] bg-primary-950"></div>
            <div class="absolute left-10 top-10 h-64 w-64 rounded-full border border-primary-300/60 dark:border-primary-700/40"></div>
        </div>

        <div class="relative mx-auto flex min-h-screen max-w-6xl flex-col px-4 sm:px-6">
            <!-- Header -->
            <header class="flex items-center justify-between py-5">
                <Link href="/" class="flex items-center gap-3">
                    <img :src="logoIcon" alt="Jura" class="h-12 w-auto" />
                    <span class="hidden flex-col leading-none sm:flex">
                        <span class="text-2xl font-bold text-primary-800 dark:text-primary-300">Jura</span>
                        <span class="mt-1 text-[10px] uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">
                            {{ $t('ui.welcome.tagline') }}
                        </span>
                    </span>
                </Link>

                <nav class="flex items-center gap-2 sm:gap-3">
                    <Link
                        :href="login().url"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-primary-800 hover:bg-primary-100 dark:text-primary-200 dark:hover:bg-gray-800"
                    >
                        {{ $t('ui.welcome.login') }}
                    </Link>
                    <Link
                        :href="register().url"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-800"
                    >
                        {{ $t('ui.welcome.register') }}
                    </Link>
                </nav>
            </header>

            <main class="flex-1">
                <!-- Hero -->
                <section class="pb-10 pt-10 text-center sm:pt-16">
                    <h1 class="text-4xl font-bold leading-tight tracking-tight sm:text-6xl">
                        {{ $t('ui.welcome.hero.title_1') }}<br />
                        <span class="text-primary-600 dark:text-primary-400">{{ $t('ui.welcome.hero.title_2') }}</span>
                    </h1>
                    <div class="mx-auto mt-6 h-px w-16 bg-primary-300 dark:bg-primary-700"></div>
                </section>

                <!-- Wallet illustration + story -->
                <section class="grid items-center gap-10 pb-14 lg:grid-cols-[1fr_20rem]">
                    <div class="relative mx-auto h-[26rem] w-full max-w-md">
                        <!-- Wallet scene, centered -->
                        <div class="absolute left-1/2 top-1/2 h-72 w-72 -translate-x-1/2 -translate-y-1/2">
                            <div class="absolute inset-0 rounded-full bg-primary-100 dark:bg-primary-900/40"></div>

                            <!-- Coins falling into the wallet -->
                            <div
                                v-for="(coin, i) in coins"
                                :key="i"
                                :class="coin"
                                class="absolute flex h-9 w-9 items-center justify-center rounded-full border-2 border-primary-300 bg-primary-600 text-sm font-bold text-white shadow-md dark:border-primary-400"
                            >
                                $
                            </div>

                            <svg viewBox="0 0 200 150" class="absolute bottom-6 left-1/2 w-56 -translate-x-1/2 drop-shadow-xl" aria-hidden="true">
                                <rect x="20" y="20" width="160" height="110" rx="16" class="fill-primary-800" />
                                <rect x="20" y="20" width="160" height="38" rx="16" class="fill-primary-700" />
                                <path d="M30 50h140" class="stroke-primary-500" stroke-width="2" stroke-dasharray="4 4" fill="none" />
                                <rect x="130" y="68" width="62" height="40" rx="12" class="fill-primary-900" />
                                <circle cx="150" cy="88" r="6" class="fill-primary-300" />
                            </svg>
                        </div>

                        <!-- Expense chips around the wallet -->
                        <div
                            v-for="chip in chips"
                            :key="chip.key"
                            :class="chip.position"
                            class="absolute flex flex-col items-center gap-1"
                        >
                            <span class="flex h-14 w-14 items-center justify-center rounded-full border border-primary-200 bg-white text-primary-700 shadow-sm dark:border-primary-800 dark:bg-gray-800 dark:text-primary-300">
                                <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path :d="chip.path" />
                                </svg>
                            </span>
                            <span class="text-xs text-gray-600 dark:text-gray-300">{{ $t(`ui.welcome.chips.${chip.key}`) }}</span>
                        </div>
                    </div>

                    <div class="mx-auto max-w-sm space-y-4 text-center lg:mx-0 lg:text-left">
                        <p class="font-semibold">{{ $t('ui.welcome.story.lead') }}</p>
                        <p class="text-gray-600 dark:text-gray-300">{{ $t('ui.welcome.story.body') }}</p>
                        <div class="mx-auto h-px w-8 bg-primary-400 lg:mx-0"></div>
                        <p class="font-semibold">{{ $t('ui.welcome.story.separate') }}</p>
                        <p class="font-semibold text-primary-600 dark:text-primary-400">{{ $t('ui.welcome.story.together') }}</p>
                    </div>
                </section>

                <!-- Features -->
                <section class="grid gap-4 rounded-2xl border border-primary-100 bg-white/80 p-5 shadow-sm backdrop-blur sm:grid-cols-3 dark:border-gray-700 dark:bg-gray-800/80">
                    <div v-for="feature in features" :key="feature.key" class="flex items-center gap-4">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                            <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path :d="feature.path" />
                            </svg>
                        </span>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <span class="block font-semibold text-gray-900 dark:text-white">{{ $t(`ui.welcome.features.${feature.key}.title`) }}</span>
                            {{ $t(`ui.welcome.features.${feature.key}.text`) }}
                        </p>
                    </div>
                </section>

                <!-- Closing -->
                <section class="py-14 text-center">
                    <p class="text-2xl font-bold sm:text-3xl">
                        <span class="text-primary-600 dark:text-primary-400">{{ $t('ui.welcome.closing.line_1') }}</span><br />
                        {{ $t('ui.welcome.closing.line_2') }}
                        <span class="text-primary-600" aria-hidden="true">♥</span>
                    </p>
                    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link
                            :href="register().url"
                            class="rounded-lg bg-primary-600 px-6 py-3 text-base font-medium text-white shadow hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-800"
                        >
                            {{ $t('ui.welcome.hero.cta') }}
                        </Link>
                        <Link
                            :href="login().url"
                            class="rounded-lg border border-primary-300 bg-white px-6 py-3 text-base font-medium text-primary-800 hover:bg-primary-50 dark:border-primary-700 dark:bg-gray-800 dark:text-primary-200 dark:hover:bg-gray-700"
                        >
                            {{ $t('ui.welcome.hero.cta_secondary') }}
                        </Link>
                    </div>
                </section>
            </main>

            <footer class="py-6 text-center text-xs text-gray-500 dark:text-gray-400">
                © {{ year }} Jura · {{ $t('ui.welcome.footer') }}
            </footer>
        </div>
    </div>
</template>
