<script setup>
import logoIcon from '../../img/logo-icon.png';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-primary-50 pt-6 sm:justify-center sm:pt-0 dark:bg-gray-900"
    >
        <div>
            <Link href="/">
                <img :src="logoIcon" alt="Jura" class="h-20 w-auto" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg dark:bg-gray-800"
        >
            <slot />
        </div>
    </div>
</template>
