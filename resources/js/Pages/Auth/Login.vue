<script setup>
import AuthButton from '@/Components/AuthButton.vue';
import AuthField from '@/Components/AuthField.vue';
import AuthStatus from '@/Components/AuthStatus.vue';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { store } from '@/actions/App/Modules/Identity/Http/Controllers/Auth/AuthenticatedSessionController';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.submit(store(), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout :title="$t('ui.auth.login.title')" :description="$t('ui.auth.login.description')">
        <Head :title="$t('ui.auth.login.title')" />

        <AuthStatus v-if="status">{{ status }}</AuthStatus>

        <form class="space-y-5" @submit.prevent="submit">
            <AuthField
                id="email"
                v-model="form.email"
                type="email"
                icon="envelope"
                :label="$t('ui.auth.email')"
                :error="form.errors.email"
                autocomplete="username"
                autofocus
            />

            <AuthField
                id="password"
                v-model="form.password"
                type="password"
                icon="lock-closed"
                :label="$t('ui.auth.password')"
                :error="form.errors.password"
                autocomplete="current-password"
            />

            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <label class="flex min-h-[44px] cursor-pointer items-center gap-2.5">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $t('ui.auth.login.remember') }}</span>
                </label>
                <Link
                    v-if="canResetPassword"
                    :href="request()"
                    class="inline-flex min-h-[44px] items-center rounded-md text-sm font-semibold text-primary-700 hover:text-primary-800 hover:underline focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:text-primary-300 dark:hover:text-primary-200 dark:focus:ring-offset-gray-900"
                >
                    {{ $t('ui.auth.login.forgot') }}
                </Link>
            </div>

            <AuthButton :processing="form.processing">{{ $t('ui.auth.login.title') }}</AuthButton>
        </form>

        <template #footer>
            <span>{{ $t('ui.auth.login.no_account') }}</span>
            <Link :href="register()" class="ms-1">{{ $t('ui.auth.register.title') }}</Link>
        </template>
    </GuestLayout>
</template>
