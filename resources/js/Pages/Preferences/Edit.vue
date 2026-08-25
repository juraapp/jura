<script setup>
import { Head, usePage, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { update } from '@/actions/App/Http/Controllers/PreferenceController';

defineProps({
    currencies: { type: Array, required: true },
    timezones: { type: Array, required: true },
});

const page = usePage();

const form = useForm({
    currency_default: page.props.auth.user.currency_default,
    locale: page.props.auth.user.locale,
    timezone: page.props.auth.user.timezone,
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

                    <PrimaryButton type="submit" :disabled="form.processing">Save</PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
