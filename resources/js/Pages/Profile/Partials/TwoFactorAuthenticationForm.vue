<script setup>
import ConfirmsPassword from '@/Components/ConfirmsPassword.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();

const enabled = computed(() => Boolean(page.props.auth.user.two_factor_confirmed_at));

const confirming = ref(false); // enrolment started, waiting for the code
const qrCode = ref(null);
const secretKey = ref(null);
const recoveryCodes = ref([]);
const confirmationCode = ref('');
const confirmationError = ref('');

const showEnrolment = async () => {
    const [qr, secret] = await Promise.all([
        window.axios.get(route('two-factor.qr-code')),
        window.axios.get(route('two-factor.secret-key')),
    ]);
    qrCode.value = qr.data.svg;
    secretKey.value = secret.data.secretKey;
    confirming.value = true;
};

const showRecoveryCodes = async () => {
    const { data } = await window.axios.get(route('two-factor.recovery-codes'));
    recoveryCodes.value = data;
};

const enable = () =>
    router.post(route('two-factor.enable'), {}, {
        preserveScroll: true,
        onSuccess: showEnrolment,
    });

const confirm = () => {
    confirmationError.value = '';
    router.post(route('two-factor.confirm'), { code: confirmationCode.value }, {
        preserveScroll: true,
        onSuccess: async () => {
            confirming.value = false;
            confirmationCode.value = '';
            await showRecoveryCodes();
        },
        onError: (errors) => {
            confirmationError.value =
                errors.code ?? errors.confirmTwoFactorAuthentication?.code ?? 'The provided code was invalid.';
        },
    });
};

const regenerateRecoveryCodes = async () => {
    await window.axios.post(route('two-factor.regenerate-recovery-codes'));
    await showRecoveryCodes();
};

const disable = () =>
    router.delete(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = false;
            recoveryCodes.value = [];
        },
    });
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Two-Factor Authentication
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Add an extra layer of security: signing in will also require a
                one-time code from your authenticator app.
            </p>
        </header>

        <div class="mt-4 space-y-4">
            <!-- Disabled -->
            <template v-if="!enabled && !confirming">
                <p class="text-sm font-medium text-gray-800">
                    You have not enabled two-factor authentication.
                </p>
                <ConfirmsPassword @confirmed="enable">
                    <PrimaryButton type="button">Enable</PrimaryButton>
                </ConfirmsPassword>
            </template>

            <!-- Enrolment: scan + confirm -->
            <template v-else-if="confirming">
                <p class="text-sm text-gray-600">
                    Scan the QR code with your authenticator app (or enter the
                    setup key manually), then confirm with a generated code to
                    finish enabling two-factor authentication.
                </p>
                <div v-if="qrCode" class="inline-block rounded-lg border border-gray-200 bg-white p-4" v-html="qrCode" />
                <p v-if="secretKey" class="text-sm text-gray-600">
                    Setup key:
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs" data-testid="two-factor-secret">{{ secretKey }}</code>
                </p>
                <div class="max-w-xs">
                    <InputLabel value="Code" />
                    <TextInput
                        v-model="confirmationCode"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        class="mt-1 w-full"
                        @keyup.enter="confirm"
                    />
                    <InputError :message="confirmationError" class="mt-1" />
                </div>
                <div class="flex gap-3">
                    <PrimaryButton type="button" @click="confirm">Confirm</PrimaryButton>
                    <ConfirmsPassword @confirmed="disable">
                        <SecondaryButton type="button">Cancel</SecondaryButton>
                    </ConfirmsPassword>
                </div>
            </template>

            <!-- Enabled -->
            <template v-else>
                <p class="text-sm font-medium text-green-700">
                    Two-factor authentication is enabled.
                </p>

                <div v-if="recoveryCodes.length" class="space-y-2">
                    <p class="text-sm text-gray-600">
                        Store these recovery codes in a safe place — each can be
                        used once to sign in if you lose your device.
                    </p>
                    <div class="grid max-w-md gap-1 rounded-lg bg-gray-100 p-4 font-mono text-sm" data-testid="recovery-codes">
                        <div v-for="code in recoveryCodes" :key="code">{{ code }}</div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <ConfirmsPassword v-if="!recoveryCodes.length" @confirmed="showRecoveryCodes">
                        <SecondaryButton type="button">Show Recovery Codes</SecondaryButton>
                    </ConfirmsPassword>
                    <ConfirmsPassword v-else @confirmed="regenerateRecoveryCodes">
                        <SecondaryButton type="button">Regenerate Recovery Codes</SecondaryButton>
                    </ConfirmsPassword>
                    <ConfirmsPassword @confirmed="disable">
                        <DangerButton type="button">Disable</DangerButton>
                    </ConfirmsPassword>
                </div>
            </template>
        </div>
    </section>
</template>
