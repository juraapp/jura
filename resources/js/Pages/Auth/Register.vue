<script setup>
import AuthButton from '@/Components/AuthButton.vue';
import AuthField from '@/Components/AuthField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { store } from '@/actions/App/Modules/Identity/Http/Controllers/Auth/RegisteredUserController';
import { login } from '@/routes';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
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
    <GuestLayout :title="$t('ui.auth.register.title')">
        <Head :title="$t('ui.auth.register.title')" />

        <form class="space-y-5" @submit.prevent="submit">
            <AuthField
                id="name"
                v-model="form.name"
                icon="user"
                :label="$t('ui.auth.name')"
                :error="form.errors.name"
                autocomplete="name"
                autofocus
            />

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

            <AuthButton :processing="form.processing">{{ $t('ui.auth.register.title') }}</AuthButton>
        </form>

        <template #footer>
            <span>{{ $t('ui.auth.register.already') }}</span>
            <Link :href="login()" class="ms-1">{{ $t('ui.auth.login.title') }}</Link>
        </template>
    </GuestLayout>
</template>
