<script setup>
import { computed, onMounted, ref } from 'vue';
import DynamicIcon from '@/Components/DynamicIcon.vue';
import InputError from '@/Components/InputError.vue';

const model = defineModel({ type: String, required: true });

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    icon: { type: String, default: '' },
    error: { type: String, default: undefined },
    autocomplete: { type: String, default: undefined },
    autofocus: { type: Boolean, default: false },
    required: { type: Boolean, default: true },
});

const input = ref(null);
const revealed = ref(false);

const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && revealed.value ? 'text' : props.type));

onMounted(() => {
    if (props.autofocus) {
        input.value?.focus();
    }
});
</script>

<template>
    <div>
        <label :for="id" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ label }}</label>

        <div class="relative">
            <input
                :id="id"
                ref="input"
                v-model="model"
                :name="id"
                :type="inputType"
                :required="required"
                :autocomplete="autocomplete"
                :aria-invalid="Boolean(error)"
                :aria-describedby="error ? `${id}-error` : undefined"
                :class="[icon ? 'ps-11' : 'ps-4', isPassword ? 'pe-12' : 'pe-4']"
                class="peer block min-h-[48px] w-full rounded-xl border border-gray-300 bg-white py-3 text-base text-gray-900 shadow-sm transition duration-200 placeholder:text-gray-400 focus:border-primary-500 focus:ring-4 focus:ring-primary-500/15 aria-[invalid=true]:border-red-400 aria-[invalid=true]:focus:border-red-500 aria-[invalid=true]:focus:ring-red-500/15 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-primary-400 dark:focus:ring-primary-400/20 dark:aria-[invalid=true]:border-red-500"
            />

            <span
                v-if="icon"
                aria-hidden="true"
                class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-gray-400 transition-colors duration-200 peer-focus:text-primary-600 dark:text-gray-500 dark:peer-focus:text-primary-400"
            >
                <DynamicIcon :name="icon" class="h-5 w-5" />
            </span>

            <button
                v-if="isPassword"
                type="button"
                class="absolute inset-y-0 end-0 flex w-12 items-center justify-center rounded-e-xl text-gray-400 transition-colors duration-200 hover:text-gray-600 focus:text-primary-600 focus:outline-none dark:text-gray-500 dark:hover:text-gray-300 dark:focus:text-primary-400"
                :aria-label="revealed ? $t('ui.auth.hide_password') : $t('ui.auth.show_password')"
                :aria-pressed="revealed"
                @click="revealed = !revealed"
            >
                <DynamicIcon :name="revealed ? 'eye-slash' : 'eye'" class="h-5 w-5" />
            </button>
        </div>

        <InputError :id="`${id}-error`" role="alert" class="mt-2" :message="error" />
    </div>
</template>
