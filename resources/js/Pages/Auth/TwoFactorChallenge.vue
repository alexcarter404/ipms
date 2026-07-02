<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const recovery = ref(false);
const codeInput = ref(null);
const recoveryInput = ref(null);

const form = useForm({
    code: '',
    recovery_code: '',
});

const toggleRecovery = async () => {
    recovery.value = !recovery.value;
    await nextTick();
    if (recovery.value) {
        recoveryInput.value?.focus();
        form.code = '';
    } else {
        codeInput.value?.focus();
        form.recovery_code = '';
    }
};

const submit = () => form.post(route('two-factor.login.store'));
</script>

<template>
    <GuestLayout>
        <Head title="Two-Factor Confirmation" />

        <div class="mb-4 text-sm text-gray-600">
            <template v-if="!recovery">
                Please confirm access to your account by entering the
                authentication code provided by your authenticator application.
            </template>
            <template v-else>
                Please confirm access to your account by entering one of your
                emergency recovery codes.
            </template>
        </div>

        <form @submit.prevent="submit">
            <div v-if="!recovery">
                <InputLabel for="code" value="Code" />
                <TextInput
                    id="code"
                    ref="codeInput"
                    v-model="form.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    class="mt-1 block w-full"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>

            <div v-else>
                <InputLabel for="recovery_code" value="Recovery Code" />
                <TextInput
                    id="recovery_code"
                    ref="recoveryInput"
                    v-model="form.recovery_code"
                    autocomplete="one-time-code"
                    class="mt-1 block w-full"
                />
                <InputError class="mt-2" :message="form.errors.recovery_code" />
            </div>

            <div class="mt-4 flex items-center justify-between">
                <button
                    type="button"
                    class="text-sm text-gray-600 underline hover:text-gray-900"
                    @click="toggleRecovery"
                >
                    {{ recovery ? 'Use an authentication code' : 'Use a recovery code' }}
                </button>

                <PrimaryButton :disabled="form.processing">Log in</PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
