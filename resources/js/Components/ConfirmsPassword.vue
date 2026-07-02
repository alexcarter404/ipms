<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { nextTick, ref } from 'vue';

/**
 * Wrap an action that sits behind the password.confirm middleware:
 * checks whether the session was recently confirmed and, if not, asks
 * for the password first — then emits `confirmed` either way.
 */
const emit = defineEmits(['confirmed']);

const confirming = ref(false);
const password = ref('');
const error = ref('');
const processing = ref(false);
const passwordInput = ref(null);

const start = async () => {
    const { data } = await window.axios.get(route('password.confirmation'));

    if (data.confirmed) {
        emit('confirmed');
        return;
    }

    password.value = '';
    error.value = '';
    confirming.value = true;
    await nextTick();
    passwordInput.value?.focus();
};

const confirm = async () => {
    processing.value = true;
    error.value = '';

    try {
        await window.axios.post(route('password.confirm.store'), {
            password: password.value,
        });
        confirming.value = false;
        emit('confirmed');
    } catch (e) {
        error.value = e.response?.data?.errors?.password?.[0]
            ?? e.response?.data?.message
            ?? 'The password is incorrect.';
        passwordInput.value?.focus();
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <span>
        <span @click="start">
            <slot />
        </span>

        <Modal :show="confirming" max-width="md" @close="confirming = false">
            <div class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-800">Confirm Password</h3>
                <p class="text-sm text-gray-600">
                    For your security, please confirm your password to continue.
                </p>
                <div>
                    <InputLabel value="Password" />
                    <TextInput
                        ref="passwordInput"
                        v-model="password"
                        type="password"
                        class="mt-1 w-full"
                        autocomplete="current-password"
                        @keyup.enter="confirm"
                    />
                    <InputError :message="error" class="mt-1" />
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="confirming = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="processing" @click="confirm">Confirm</PrimaryButton>
                </div>
            </div>
        </Modal>
    </span>
</template>
