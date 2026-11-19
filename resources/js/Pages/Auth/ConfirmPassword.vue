<script setup>
import AuthButton from '@/Components/AuthButton.vue';
import AuthField from '@/Components/AuthField.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { store } from '@/actions/App/Modules/Identity/Http/Controllers/Auth/ConfirmablePasswordController';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.submit(store(), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout :title="$t('ui.auth.confirm.title')" :description="$t('ui.auth.confirm.text')">
        <Head :title="$t('ui.auth.confirm.title')" />

        <form class="space-y-5" @submit.prevent="submit">
            <AuthField
                id="password"
                v-model="form.password"
                type="password"
                icon="lock-closed"
                :label="$t('ui.auth.password')"
                :error="form.errors.password"
                autocomplete="current-password"
                autofocus
            />

            <AuthButton :processing="form.processing">{{ $t('ui.auth.confirm.button') }}</AuthButton>
        </form>
    </GuestLayout>
</template>
