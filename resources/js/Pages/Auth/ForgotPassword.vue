<script setup>
import AuthButton from '@/Components/AuthButton.vue';
import AuthField from '@/Components/AuthField.vue';
import AuthStatus from '@/Components/AuthStatus.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.submit(email());
};
</script>

<template>
    <GuestLayout illustration="envelope" :title="$t('ui.auth.forgot.title')" :description="$t('ui.auth.forgot.text')">
        <Head :title="$t('ui.auth.forgot.title')" />

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

            <AuthButton :processing="form.processing">{{ $t('ui.auth.forgot.button') }}</AuthButton>
        </form>

        <template #footer>
            <Link :href="login()">{{ $t('ui.auth.back_to_login') }}</Link>
        </template>
    </GuestLayout>
</template>
