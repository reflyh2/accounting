<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    central_domain: {
        type: String,
        required: true
    }
});

const form = useForm({
    tenant_name: '',
    subdomain: '',
});

const submit = () => {
    form.post(route('store.tenant'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register Tenant" />

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="tenant_name" value="Tenant Name" />
                <TextInput
                    id="tenant_name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.tenant_name"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.tenant_name" />
            </div>

            <div class="mt-4">
                <InputLabel for="subdomain" value="Subdomain" />
                <div class="flex items-center">
                    <TextInput
                        id="subdomain"
                        type="text"
                        class="mt-1 block w-full rounded-r-none"
                        v-model="form.subdomain"
                        required
                    />
                    <span class="mt-1 px-3 py-2 bg-gray-100 text-gray-600 border border-gray-300 border-l-0 rounded-r-md">
                        .{{ central_domain }}
                    </span>
                </div>
                <InputError class="mt-2" :message="form.errors.subdomain" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Register
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>