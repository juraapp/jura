<script setup>
import AuthButton from '@/Components/AuthButton.vue';
import AuthField from '@/Components/AuthField.vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import InputError from '@/Components/InputError.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { store as storeAccount } from '@/actions/App/Modules/Ledger/Http/Controllers/AccountController';
import { complete } from '@/actions/App/Modules/Identity/Http/Controllers/OnboardingController';
import { update as updatePreferences } from '@/actions/App/Modules/Identity/Http/Controllers/PreferenceController';
import { store as storeBucket } from '@/actions/App/Modules/Planning/Http/Controllers/BucketController';
import { store as storePlannedEvent } from '@/actions/App/Modules/Planning/Http/Controllers/PlannedEventController';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    currencies: { type: Array, required: true },
    timezones: { type: Array, required: true },
    preferences: { type: Object, required: true },
    accountTypes: { type: Array, required: true },
    accounts: { type: Array, required: true },
    expenseCategories: { type: Array, required: true },
    salaryCategoryId: { type: Number, default: null },
});

const { t } = useI18n();

const TOTAL_STEPS = 4;
const SUGGESTED_CATEGORIES = ['Food', 'Transportation', 'Housing', 'Utilities', 'Entertainment', 'Shopping'];
const BRAND_COLOR = '#146a50';

const initialStep = Number(new URLSearchParams(window.location.search).get('step'));
const step = ref(initialStep >= 1 && initialStep <= TOTAL_STEPS ? initialStep : 1);

watch(step, (value) => {
    const url = new URL(window.location.href);
    url.searchParams.set('step', value);
    window.history.replaceState(window.history.state, '', url);
});

const currency = computed(() => props.preferences.currency_default);
const stepTitle = computed(() => t(`ui.onboarding.${['preferences', 'account', 'income', 'budgets'][step.value - 1]}.title`));
const stepText = computed(() => t(`ui.onboarding.${['preferences', 'account', 'income', 'budgets'][step.value - 1]}.text`));

const next = () => (step.value = Math.min(step.value + 1, TOTAL_STEPS));
const back = () => (step.value = Math.max(step.value - 1, 1));

const visitOptions = (onSuccess) => ({ preserveState: true, preserveScroll: true, onSuccess });

/* Step 1: preferences */
const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
const browserLocale = (navigator.language || '').toLowerCase().startsWith('es') ? 'es' : 'en';
const preferencesForm = useForm({
    ...props.preferences,
    locale: browserLocale,
    timezone: props.timezones.includes(browserTimezone) ? browserTimezone : props.preferences.timezone,
});
const languages = [
    { value: 'es', label: 'Español' },
    { value: 'en', label: 'English' },
];
const savePreferences = () => preferencesForm.submit(updatePreferences(), visitOptions(next));

/* Step 2: first account */
const accountForm = useForm({
    name: '',
    type: 'bank',
    initial_balance: '',
    currency: currency.value,
    color: BRAND_COLOR,
    is_active: true,
});
const saveAccount = () => {
    accountForm.currency = currency.value;
    accountForm.submit(storeAccount(), visitOptions(next));
};

/* Step 3: main income, saved as a recurring planned event (projection only) */
const incomeForm = useForm({
    type: 'income',
    description: '',
    amount: '',
    date: '',
    frequency: 'monthly',
    certainty: 'expected',
    currency: currency.value,
    account_id: null,
    category_id: props.salaryCategoryId,
});
const frequencies = ['monthly', 'biweekly', 'weekly'];
watch(() => props.accounts, (accounts) => {
    if (incomeForm.account_id === null && accounts.length) {
        incomeForm.account_id = accounts[accounts.length - 1].id;
    }
}, { immediate: true });
const saveIncome = () => {
    incomeForm.currency = currency.value;
    incomeForm.description = incomeForm.description || t('ui.onboarding.income.description');
    incomeForm.submit(storePlannedEvent(), visitOptions(next));
};

/* Step 4: spending buckets and an optional savings goal, then finish */
const suggestedCategories = computed(() => {
    const byName = new Map(props.expenseCategories.map((category) => [category.name, category]));
    const suggested = SUGGESTED_CATEGORIES.map((name) => byName.get(name)).filter(Boolean);

    return suggested.length ? suggested : props.expenseCategories.slice(0, 6);
});
const limits = ref({});
const toggleCategory = (id) => {
    if (id in limits.value) {
        delete limits.value[id];
    } else {
        limits.value[id] = '';
    }
};
const wantsGoal = ref(false);
const goal = ref({ name: '', target_amount: '', target_date: '' });
const finishing = ref(false);
const finishError = ref('');

const send = (action, data) => new Promise((resolve, reject) => {
    router.visit(action.url, {
        method: action.method,
        data,
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => (Object.keys(page.props.errors ?? {}).length ? reject() : resolve()),
        onError: reject,
    });
});

const finish = async () => {
    finishing.value = true;
    finishError.value = '';

    try {
        for (const [categoryId, cap] of Object.entries(limits.value)) {
            if (Number(cap) > 0) {
                await send(storeBucket(), {
                    kind: 'spending',
                    category_id: Number(categoryId),
                    currency: currency.value,
                    cap_amount: cap,
                    cycle: 'monthly',
                });
                delete limits.value[categoryId];
            }
        }

        if (wantsGoal.value && goal.value.name && Number(goal.value.target_amount) > 0) {
            await send(storeBucket(), {
                kind: 'savings',
                name: goal.value.name,
                currency: currency.value,
                cap_amount: goal.value.target_amount,
                target_date: goal.value.target_date || null,
            });
            wantsGoal.value = false;
        }

        router.post(complete().url);
    } catch {
        finishError.value = t('ui.onboarding.error');
        finishing.value = false;
    }
};

const skipAll = () => router.post(complete().url);

const choiceClass = (selected) => [
    'flex min-h-[48px] items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition-colors duration-200 focus:outline-none focus:ring-4 focus:ring-primary-500/15',
    selected
        ? 'border-primary-500 bg-primary-50 text-primary-800 dark:border-primary-400 dark:bg-primary-900/40 dark:text-primary-200'
        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-500',
];
const selectClass = 'block min-h-[48px] w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-base text-gray-900 shadow-sm focus:border-primary-500 focus:ring-4 focus:ring-primary-500/15 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
const labelClass = 'mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200';
</script>

<template>
    <GuestLayout :title="stepTitle" :description="stepText">
        <Head :title="stepTitle" />

        <template #top>
            <div class="mb-6">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-primary-700 dark:text-primary-300">{{ $t('ui.onboarding.progress', { current: step, total: TOTAL_STEPS }) }}</span>
                    <button
                        type="button"
                        class="inline-flex min-h-[44px] items-center rounded-md text-gray-500 hover:text-gray-700 hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-400 dark:hover:text-gray-200"
                        @click="skipAll"
                    >
                        {{ $t('ui.onboarding.skip_all') }}
                    </button>
                </div>
                <div class="mt-2 flex gap-1.5" aria-hidden="true">
                    <span
                        v-for="n in TOTAL_STEPS"
                        :key="n"
                        class="h-1.5 flex-1 rounded-full transition-colors duration-500"
                        :class="n <= step ? 'bg-primary-500' : 'bg-gray-200 dark:bg-gray-700'"
                    ></span>
                </div>
            </div>
        </template>

        <Transition name="step" mode="out-in">
            <!-- Step 1: preferences -->
            <form v-if="step === 1" key="1" class="space-y-6" @submit.prevent="savePreferences">
                <fieldset>
                    <legend :class="labelClass">{{ $t('ui.onboarding.preferences.language') }}</legend>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            v-for="language in languages"
                            :key="language.value"
                            type="button"
                            :class="choiceClass(preferencesForm.locale === language.value)"
                            :aria-pressed="preferencesForm.locale === language.value"
                            @click="preferencesForm.locale = language.value"
                        >
                            {{ language.label }}
                        </button>
                    </div>
                </fieldset>

                <fieldset>
                    <legend :class="labelClass">{{ $t('ui.onboarding.preferences.currency') }}</legend>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            v-for="option in currencies"
                            :key="option.value"
                            type="button"
                            :class="choiceClass(preferencesForm.currency_default === option.value)"
                            :aria-pressed="preferencesForm.currency_default === option.value"
                            @click="preferencesForm.currency_default = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <InputError class="mt-2" :message="preferencesForm.errors.currency_default" />
                </fieldset>

                <div>
                    <label for="timezone" :class="labelClass">{{ $t('ui.onboarding.preferences.timezone') }}</label>
                    <select id="timezone" v-model="preferencesForm.timezone" :class="selectClass">
                        <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option>
                    </select>
                    <InputError class="mt-2" :message="preferencesForm.errors.timezone" />
                </div>

                <AuthButton :processing="preferencesForm.processing">{{ $t('ui.onboarding.continue') }}</AuthButton>
            </form>

            <!-- Step 2: first account -->
            <form v-else-if="step === 2" key="2" class="space-y-6" @submit.prevent="saveAccount">
                <fieldset>
                    <legend :class="labelClass">{{ $t('ui.onboarding.account.type') }}</legend>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <button
                            v-for="type in accountTypes"
                            :key="type.value"
                            type="button"
                            :class="choiceClass(accountForm.type === type.value)"
                            :aria-pressed="accountForm.type === type.value"
                            @click="accountForm.type = type.value"
                        >
                            <DynamicIcon :name="type.icon" class="h-5 w-5 shrink-0" />
                            {{ type.label }}
                        </button>
                    </div>
                </fieldset>

                <AuthField
                    id="account_name"
                    v-model="accountForm.name"
                    icon="banknotes"
                    :label="$t('ui.onboarding.account.name')"
                    :error="accountForm.errors.name"
                    autocomplete="off"
                />

                <AuthField
                    id="initial_balance"
                    v-model="accountForm.initial_balance"
                    type="number"
                    step="0.01"
                    inputmode="decimal"
                    icon="wallet"
                    :label="`${$t('ui.onboarding.account.balance')} (${currency})`"
                    :error="accountForm.errors.initial_balance"
                />

                <AuthButton :processing="accountForm.processing">{{ $t('ui.onboarding.continue') }}</AuthButton>
            </form>

            <!-- Step 3: main income -->
            <form v-else-if="step === 3" key="3" class="space-y-6" @submit.prevent="saveIncome">
                <AuthField
                    id="income_amount"
                    v-model="incomeForm.amount"
                    type="number"
                    step="0.01"
                    min="0"
                    inputmode="decimal"
                    icon="arrow-trending-up"
                    :label="`${$t('ui.onboarding.income.amount')} (${currency})`"
                    :error="incomeForm.errors.amount"
                />

                <fieldset>
                    <legend :class="labelClass">{{ $t('ui.onboarding.income.frequency') }}</legend>
                    <div class="grid grid-cols-3 gap-3">
                        <button
                            v-for="frequency in frequencies"
                            :key="frequency"
                            type="button"
                            :class="choiceClass(incomeForm.frequency === frequency)"
                            :aria-pressed="incomeForm.frequency === frequency"
                            @click="incomeForm.frequency = frequency"
                        >
                            {{ $t(`ui.onboarding.income.${frequency}`) }}
                        </button>
                    </div>
                </fieldset>

                <AuthField
                    id="income_date"
                    v-model="incomeForm.date"
                    type="date"
                    icon="calendar"
                    :label="$t('ui.onboarding.income.next_date')"
                    :error="incomeForm.errors.date"
                />

                <div v-if="accounts.length">
                    <label for="income_account" :class="labelClass">{{ $t('ui.onboarding.income.account') }}</label>
                    <select id="income_account" v-model="incomeForm.account_id" :class="selectClass">
                        <option :value="null">{{ $t('ui.onboarding.income.no_account') }}</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                </div>

                <AuthButton :processing="incomeForm.processing">{{ $t('ui.onboarding.continue') }}</AuthButton>
            </form>

            <!-- Step 4: budgets and a goal -->
            <form v-else key="4" class="space-y-6" @submit.prevent="finish">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="category in suggestedCategories"
                        :key="category.id"
                        type="button"
                        :class="choiceClass(category.id in limits)"
                        :aria-pressed="category.id in limits"
                        @click="toggleCategory(category.id)"
                    >
                        <DynamicIcon :name="category.icon" class="h-4 w-4 shrink-0" :style="{ color: category.color }" />
                        {{ category.name }}
                    </button>
                </div>

                <TransitionGroup name="row" tag="div" class="space-y-4">
                    <AuthField
                        v-for="category in suggestedCategories.filter((c) => c.id in limits)"
                        :id="`limit_${category.id}`"
                        :key="category.id"
                        v-model="limits[category.id]"
                        type="number"
                        step="0.01"
                        min="0"
                        inputmode="decimal"
                        icon="chart-pie"
                        :label="`${$t('ui.onboarding.budgets.monthly_limit', { category: category.name })} (${currency})`"
                    />
                </TransitionGroup>

                <label class="flex min-h-[44px] cursor-pointer items-center gap-3">
                    <input v-model="wantsGoal" type="checkbox" class="h-5 w-5 rounded-md border-gray-300 text-primary-600 focus:ring-2 focus:ring-primary-200 dark:border-gray-500 dark:bg-gray-700" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $t('ui.onboarding.budgets.goal_toggle') }}</span>
                </label>

                <Transition name="row">
                    <div v-if="wantsGoal" class="space-y-4">
                        <AuthField id="goal_name" v-model="goal.name" icon="flag" :label="$t('ui.onboarding.budgets.goal_name')" autocomplete="off" />
                        <AuthField
                            id="goal_amount"
                            v-model="goal.target_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            inputmode="decimal"
                            icon="banknotes"
                            :label="`${$t('ui.onboarding.budgets.goal_amount')} (${currency})`"
                        />
                        <AuthField id="goal_date" v-model="goal.target_date" type="date" icon="calendar" :label="$t('ui.onboarding.budgets.goal_date')" :required="false" />
                    </div>
                </Transition>

                <p v-if="finishError" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ finishError }}</p>

                <AuthButton :processing="finishing">{{ $t('ui.onboarding.finish') }}</AuthButton>
            </form>
        </Transition>

        <div class="mt-4 flex items-center justify-between text-sm">
            <button
                v-if="step > 1"
                type="button"
                class="inline-flex min-h-[44px] items-center gap-1 rounded-md font-medium text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-400 dark:hover:text-gray-200"
                @click="back"
            >
                {{ $t('ui.onboarding.back') }}
            </button>
            <span v-else></span>
            <button
                v-if="step < TOTAL_STEPS"
                type="button"
                class="inline-flex min-h-[44px] items-center rounded-md font-medium text-primary-700 hover:text-primary-800 hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-primary-300 dark:hover:text-primary-200"
                @click="next"
            >
                {{ $t('ui.onboarding.skip_step') }}
            </button>
        </div>
    </GuestLayout>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .step-enter-active,
    .step-leave-active,
    .row-enter-active,
    .row-leave-active {
        transition:
            opacity 0.25s ease,
            transform 0.25s ease;
    }

    .step-enter-from {
        opacity: 0;
        transform: translateX(16px);
    }

    .step-leave-to {
        opacity: 0;
        transform: translateX(-16px);
    }

    .row-enter-from,
    .row-leave-to {
        opacity: 0;
        transform: translateY(-6px);
    }
}
</style>
