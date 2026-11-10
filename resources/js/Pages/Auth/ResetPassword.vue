<script setup>
import AuthButton from '@/Components/AuthButton.vue';
import AuthField from '@/Components/AuthField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/password';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.submit(store(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout :title="$t('ui.auth.reset.title')">
        <Head :title="$t('ui.auth.reset.title')" />

        <form class="space-y-5" @submit.prevent="submit">
            <AuthField
                id="email"
                v-model="form.email"
                type="email"
                icon="envelope"
                :label="$t('ui.auth.email')"
                :error="form.errors.email"
                autocomplete="username"
            />

            <AuthField
                id="password"
                v-model="form.password"
                type="password"
                icon="lock-closed"
                :label="$t('ui.auth.password')"
                :error="form.errors.password"
                autocomplete="new-password"
                autofocus
            />

            <AuthField
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                icon="lock-closed"
                :label="$t('ui.auth.confirm_password')"
                :error="form.errors.password_confirmation"
                autocomplete="new-password"
            />

            <AuthButton :processing="form.processing">{{ $t('ui.auth.reset.button') }}</AuthButton>
        </form>

        <template #footer>
            <Link :href="login()">{{ $t('ui.auth.back_to_login') }}</Link>
        </template>
    </GuestLayout>
</template>
