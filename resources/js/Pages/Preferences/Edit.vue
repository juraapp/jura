<script setup>
import { computed } from 'vue';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { createFormat } from '@/format';
import { update } from '@/actions/App/Modules/Identity/Http/Controllers/PreferenceController';

defineProps({
    currencies: { type: Array, required: true },
    timezones: { type: Array, required: true },
});

const page = usePage();

const form = useForm({
    currency_default: page.props.auth.user.currency_default,
    locale: page.props.auth.user.locale,
    timezone: page.props.auth.user.timezone,
    date_format: page.props.auth.user.date_format ?? 'auto',
    time_format: page.props.auth.user.time_format ?? 'auto',
    decimal_separator: page.props.auth.user.decimal_separator ?? '',
    thousands_separator: page.props.auth.user.thousands_separator ?? '',
    fraction_digits: page.props.auth.user.fraction_digits ?? '',
    currency_display: page.props.auth.user.currency_display ?? 'auto',
    currency_position: page.props.auth.user.currency_position ?? 'auto',
});

const numberPresets = [
    { label: 'Follow my language', decimal: '', thousands: '', digits: '' },
    { label: '1,234,567.89', decimal: '.', thousands: ',', digits: 2 },
    { label: '1.234.567,89', decimal: ',', thousands: '.', digits: 2 },
    { label: '1 234 567,89', decimal: ',', thousands: ' ', digits: 2 },
    { label: '1234567.89', decimal: '.', thousands: 'none', digits: 2 },
];

const applyPreset = (preset) => {
    form.decimal_separator = preset.decimal;
    form.thousands_separator = preset.thousands;
    form.fraction_digits = preset.digits;
};

const preview = computed(() => {
    const format = createFormat({
        ...form.data(),
        decimal_separator: form.decimal_separator || null,
        thousands_separator: form.thousands_separator || null,
        fraction_digits: form.fraction_digits === '' ? null : Number(form.fraction_digits),
    }, form.locale);
    const sample = new Date(2026, 2, 9, 15, 5);

    return {
        date: format.date(sample),
        time: format.time(sample),
        money: format.money(1234567.5, form.currency_default),
        number: format.number(1234567.891),
    };
});

const submit = () => {
    form.submit(update(), { preserveScroll: true });
};
</script>

<template>
    <Head title="Preferences" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Preferences
            </h2>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <header class="mb-6">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Currency, language, and time zone</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        These preferences control how amounts and dates are formatted throughout the app.
                    </p>
                </header>

                <form class="space-y-6" @submit.prevent="submit">
                    <div>
                        <InputLabel for="currency_default" value="Primary currency" />
                        <select
                            id="currency_default"
                            v-model="form.currency_default"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.currency_default" />
                    </div>

                    <div>
                        <InputLabel for="locale" value="Language" />
                        <select
                            id="locale"
                            v-model="form.locale"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="en">English</option>
                            <option value="es">Español</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.locale" />
                    </div>

                    <div>
                        <InputLabel for="timezone" value="Time zone" />
                        <select
                            id="timezone"
                            v-model="form.timezone"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option v-for="timezone in timezones" :key="timezone" :value="timezone">{{ timezone }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.timezone" />
                    </div>

                    <fieldset class="space-y-5 border-t border-gray-200 pt-6 dark:border-gray-700">
                        <legend class="text-base font-semibold text-gray-900 dark:text-white">How dates, times and numbers look</legend>

                        <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-700/50 dark:text-gray-200">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Preview</p>
                            <p class="mt-1">{{ preview.date }} &middot; {{ preview.time }}</p>
                            <p>{{ preview.money }} &middot; {{ preview.number }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="date_format" value="Date" />
                                <select id="date_format" v-model="form.date_format" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                    <option value="auto">Follow my language</option>
                                    <option value="iso">2026-03-09 (year-month-day)</option>
                                    <option value="mdy">03/09/2026 (month/day/year)</option>
                                    <option value="dmy">09/03/2026 (day/month/year)</option>
                                    <option value="medium">9 Mar 2026</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.date_format" />
                            </div>
                            <div>
                                <InputLabel for="time_format" value="Time" />
                                <select id="time_format" v-model="form.time_format" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                    <option value="auto">Follow my language</option>
                                    <option value="12h">12-hour (3:05 PM)</option>
                                    <option value="24h">24-hour (15:05)</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.time_format" />
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Numbers" />
                            <div class="mt-1 flex flex-wrap gap-2">
                                <button v-for="preset in numberPresets" :key="preset.label" type="button" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700" @click="applyPreset(preset)">
                                    {{ preset.label }}
                                </button>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <InputLabel for="decimal_separator" value="Decimal separator" />
                                    <select id="decimal_separator" v-model="form.decimal_separator" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                        <option value="">Follow my language</option>
                                        <option value=".">Point (.)</option>
                                        <option value=",">Comma (,)</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.decimal_separator" />
                                </div>
                                <div>
                                    <InputLabel for="thousands_separator" value="Thousands separator" />
                                    <select id="thousands_separator" v-model="form.thousands_separator" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                        <option value="">Follow my language</option>
                                        <option value=",">Comma (,)</option>
                                        <option value=".">Point (.)</option>
                                        <option value=" ">Space</option>
                                        <option value="none">None</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.thousands_separator" />
                                </div>
                                <div>
                                    <InputLabel for="fraction_digits" value="Decimals" />
                                    <select id="fraction_digits" v-model="form.fraction_digits" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                        <option value="">Follow the currency</option>
                                        <option :value="0">0</option>
                                        <option :value="1">1</option>
                                        <option :value="2">2</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.fraction_digits" />
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="currency_display" value="Currency shown as" />
                                <select id="currency_display" v-model="form.currency_display" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                    <option value="auto">Follow my language</option>
                                    <option value="symbol">Symbol ($)</option>
                                    <option value="code">Code (COP)</option>
                                    <option value="none">Nothing</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.currency_display" />
                            </div>
                            <div>
                                <InputLabel for="currency_position" value="Currency position" />
                                <select id="currency_position" v-model="form.currency_position" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                    <option value="auto">Follow my language</option>
                                    <option value="before">Before the amount ($100)</option>
                                    <option value="before_space">Before, with a space ($ 100)</option>
                                    <option value="after">After the amount (100$)</option>
                                    <option value="after_space">After, with a space (100 $)</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.currency_position" />
                            </div>
                        </div>
                    </fieldset>

                    <PrimaryButton type="submit" :disabled="form.processing">Save</PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
