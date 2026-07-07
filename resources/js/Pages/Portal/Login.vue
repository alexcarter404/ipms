<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });

const submit = () => form.post(route('portal.login.attempt'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Client Portal" />

    <div class="flex min-h-screen items-center justify-center bg-gray-100 px-4">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <span class="text-2xl font-bold text-indigo-600">IPMS</span>
                <p class="mt-1 text-sm text-gray-500">Client portal — sign in to view your portfolio</p>
            </div>

            <form class="space-y-4 rounded-lg bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 w-full" autofocus />
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="password" value="Password" />
                    <TextInput id="password" v-model="form.password" type="password" class="mt-1 w-full" />
                </div>
                <PrimaryButton class="w-full justify-center" :disabled="form.processing">Sign In</PrimaryButton>
            </form>
        </div>
    </div>
</template>
