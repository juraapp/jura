<script setup>
import { computed } from 'vue';
import AuthButton from '@/Components/AuthButton.vue';
import AuthStatus from '@/Components/AuthStatus.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { send } from '@/routes/verification';
import { logout } from '@/routes';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.submit(send());
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout illustration="envelope" :title="$t('ui.auth.verify.title')" :description="$t('ui.auth.verify.text')">
        <Head :title="$t('ui.auth.verify.title')" />

        <AuthStatus v-if="verificationLinkSent">{{ $t('ui.auth.verify.sent') }}</AuthStatus>

        <form @submit.prevent="submit">
            <AuthButton :processing="form.processing">{{ $t('ui.auth.verify.resend') }}</AuthButton>
        </form>

        <template #footer>
            <Link :href="logout()" as="button">{{ $t('ui.auth.verify.logout') }}</Link>
        </template>
    </GuestLayout>
</template>
