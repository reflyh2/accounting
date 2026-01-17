<script setup>
import CentralLayout from "@/Layouts/CentralLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, useForm } from "@inertiajs/vue3";
import AppPrimaryButton from "@/Components/AppPrimaryButton.vue";

const props = defineProps({
    central_domain: {
        type: String,
        required: true,
    },
});

const form = useForm({
    tenant_name: "",
    subdomain: "",
    company_name: "",
    company_address: "",
    company_city: "",
    company_province: "",
    company_postal_code: "",
    company_phone: "",
});

const submit = () => {
    form.post(route("store.tenant"), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Register Tenant" />

    <CentralLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Daftarkan Perusahaan Baru
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Tenant Info Section -->
                            <div class="border-b pb-4">
                                <h3
                                    class="text-lg font-medium text-gray-900 mb-4"
                                >
                                    Informasi Tenant
                                </h3>

                                <div>
                                    <InputLabel
                                        for="tenant_name"
                                        value="Nama Database *"
                                    />
                                    <TextInput
                                        id="tenant_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.tenant_name"
                                        required
                                        autofocus
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.tenant_name"
                                    />
                                </div>

                                <div class="mt-4">
                                    <InputLabel
                                        for="subdomain"
                                        value="Subdomain *"
                                    />
                                    <div class="flex items-center">
                                        <TextInput
                                            id="subdomain"
                                            type="text"
                                            class="mt-1 block w-full rounded-r-none"
                                            v-model="form.subdomain"
                                            required
                                        />
                                        <span
                                            class="mt-1 px-3 py-2 bg-gray-100 text-gray-600 border border-gray-300 border-l-0 rounded-r-md"
                                        >
                                            .{{ central_domain }}
                                        </span>
                                    </div>
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.subdomain"
                                    />
                                </div>
                            </div>

                            <!-- Company Info Section -->
                            <div>
                                <h3
                                    class="text-lg font-medium text-gray-900 mb-4"
                                >
                                    Informasi Perusahaan
                                </h3>

                                <div>
                                    <InputLabel
                                        for="company_name"
                                        value="Nama Perusahaan *"
                                    />
                                    <TextInput
                                        id="company_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.company_name"
                                        required
                                        placeholder="PT. Nama Perusahaan"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.company_name"
                                    />
                                </div>

                                <div class="mt-4">
                                    <InputLabel
                                        for="company_address"
                                        value="Alamat"
                                    />
                                    <TextInput
                                        id="company_address"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.company_address"
                                        placeholder="Jl. Contoh No. 123"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors.company_address"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <InputLabel
                                            for="company_city"
                                            value="Kota"
                                        />
                                        <TextInput
                                            id="company_city"
                                            type="text"
                                            class="mt-1 block w-full"
                                            v-model="form.company_city"
                                            placeholder="Jakarta"
                                        />
                                        <InputError
                                            class="mt-2"
                                            :message="form.errors.company_city"
                                        />
                                    </div>

                                    <div>
                                        <InputLabel
                                            for="company_province"
                                            value="Provinsi"
                                        />
                                        <TextInput
                                            id="company_province"
                                            type="text"
                                            class="mt-1 block w-full"
                                            v-model="form.company_province"
                                            placeholder="DKI Jakarta"
                                        />
                                        <InputError
                                            class="mt-2"
                                            :message="
                                                form.errors.company_province
                                            "
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <InputLabel
                                            for="company_postal_code"
                                            value="Kode Pos"
                                        />
                                        <TextInput
                                            id="company_postal_code"
                                            type="text"
                                            class="mt-1 block w-full"
                                            v-model="form.company_postal_code"
                                            placeholder="12345"
                                        />
                                        <InputError
                                            class="mt-2"
                                            :message="
                                                form.errors.company_postal_code
                                            "
                                        />
                                    </div>

                                    <div>
                                        <InputLabel
                                            for="company_phone"
                                            value="Telepon"
                                        />
                                        <TextInput
                                            id="company_phone"
                                            type="text"
                                            class="mt-1 block w-full"
                                            v-model="form.company_phone"
                                            placeholder="021-1234567"
                                        />
                                        <InputError
                                            class="mt-2"
                                            :message="form.errors.company_phone"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-end pt-4 border-t"
                            >
                                <AppPrimaryButton
                                    type="submit"
                                    class="ms-4"
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Buat Perusahaan
                                </AppPrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </CentralLayout>
</template>
