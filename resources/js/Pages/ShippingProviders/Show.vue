<script setup>
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';

const props = defineProps({
    shippingProvider: Object,
    filters: Object,
    typeOptions: Object,
});

function deleteShippingProvider() {
    if (confirm('Apakah Anda yakin ingin menghapus penyedia pengiriman ini?')) {
        router.delete(route('shipping-providers.destroy', props.shippingProvider.id), {
            preserveScroll: true,
        });
    }
}

function getTypeLabel(type) {
    return props.typeOptions[type] || type;
}

function getTypeBadgeClass(type) {
    return type === 'internal'
        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
}
</script>

<template>
    <Head :title="`Detail Penyedia Pengiriman - ${shippingProvider.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Penyedia Pengiriman</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded border border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('shipping-providers.index', filters)" text="Kembali ke Daftar Penyedia Pengiriman" />
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-lg font-semibold mb-4">Informasi Umum</h3>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Kode</label>
                                    <p class="text-gray-900">{{ shippingProvider.code }}</p>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                                    <p class="text-gray-900">{{ shippingProvider.name }}</p>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                                    <span
                                        class="inline-block px-2 py-1 text-xs font-medium rounded"
                                        :class="getTypeBadgeClass(shippingProvider.type)"
                                    >
                                        {{ getTypeLabel(shippingProvider.type) }}
                                    </span>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <span
                                        class="inline-block px-2 py-1 text-xs font-medium rounded"
                                        :class="shippingProvider.is_active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
                                    >
                                        {{ shippingProvider.is_active ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold mb-4">Informasi Kontak</h3>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kontak</label>
                                    <p class="text-gray-900">{{ shippingProvider.contact_person || '-' }}</p>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Telepon</label>
                                    <p class="text-gray-900">{{ shippingProvider.phone || '-' }}</p>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <p class="text-gray-900">{{ shippingProvider.email || '-' }}</p>
                                </div>
                            </div>

                            <div class="col-span-2">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                                    <p class="text-gray-900 whitespace-pre-wrap">{{ shippingProvider.address || '-' }}</p>
                                </div>

                                <div class="mb-4" v-if="shippingProvider.notes">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                                    <p class="text-gray-900 whitespace-pre-wrap">{{ shippingProvider.notes }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between">
                            <div>
                                <AppPrimaryButton @click="router.visit(route('shipping-providers.edit', shippingProvider.id))">
                                    Edit
                                </AppPrimaryButton>
                            </div>
                            <div>
                                <AppDangerButton @click="deleteShippingProvider">
                                    Hapus
                                </AppDangerButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
