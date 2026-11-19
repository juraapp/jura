<script>
/**
 * Remembers the illustration shown on the previous auth page. Each auth page
 * mounts its own GuestLayout, so this module-level value is what lets the new
 * page start from the old illustration and morph into its own.
 */
let previousVariant = null;
</script>

<script setup>
import { onMounted, ref } from 'vue';
import AuthEnvelope from '@/Components/AuthEnvelope.vue';
import AuthWallet from '@/Components/AuthWallet.vue';

const props = defineProps({
    variant: { type: String, default: 'wallet' },
});

const illustrations = { wallet: AuthWallet, envelope: AuthEnvelope };

const shown = ref(previousVariant ?? props.variant);
const bursts = ref(0);

onMounted(() => {
    if (shown.value !== props.variant) {
        requestAnimationFrame(() => {
            shown.value = props.variant;
            bursts.value++;
        });
    }

    previousVariant = props.variant;
});
</script>

<template>
    <div class="relative flex h-[300px] w-80 items-center justify-center">
        <span v-if="bursts" :key="bursts" aria-hidden="true" class="burst absolute h-56 w-56 rounded-full border-4 border-primary-300/60"></span>

        <Transition name="morph" mode="out-in">
            <component :is="illustrations[shown] ?? AuthWallet" :key="shown" />
        </Transition>
    </div>
</template>

<style scoped>
.morph-leave-active {
    transition:
        transform 0.32s cubic-bezier(0.55, 0, 1, 0.45),
        opacity 0.28s ease-in;
}

.morph-leave-to {
    transform: scale(0.45) rotate(-14deg) translateY(24px);
    opacity: 0;
}

.morph-enter-active {
    transition:
        transform 0.65s cubic-bezier(0.34, 1.56, 0.64, 1),
        opacity 0.3s ease-out;
}

.morph-enter-from {
    transform: scale(0.45) rotate(14deg) translateY(32px);
    opacity: 0;
}

.burst {
    opacity: 0;
}

@media (prefers-reduced-motion: no-preference) {
    .burst {
        animation: burst 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.25s both;
    }
}

@media (prefers-reduced-motion: reduce) {
    .morph-enter-from,
    .morph-leave-to {
        transform: none;
    }
}

@keyframes burst {
    from { transform: scale(0.3); opacity: 1; }
    to { transform: scale(1.5); opacity: 0; }
}
</style>
