<script setup>
import CentralLayout from '@/Layouts/CentralLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';

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
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Register Tenant" />

    <CentralLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Register New Tenant</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit">
                            <div>
                                <InputLabel for="tenant_name" value="Nama Database" />
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
                                <AppPrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Buat Database
                                </AppPrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </CentralLayout>
</template>