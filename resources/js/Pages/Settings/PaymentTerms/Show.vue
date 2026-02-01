<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';

const props = defineProps({
    paymentTerm: Object,
    filters: Object,
});

function deletePaymentTerm() {
    router.delete(route('settings.payment-terms.destroy', props.paymentTerm.id));
}
</script>

<template>
    <Head :title="`Payment Term: ${paymentTerm.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2>Detail Payment Term</h2>
                <div class="flex gap-2">
                    <AppEditButton :href="route('settings.payment-terms.edit', paymentTerm.id)" />
                    <AppDeleteButton @delete="deletePaymentTerm" confirmMessage="Hapus payment term ini?" />
                </div>
            </div>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('settings.payment-terms.index', filters)" text="Kembali ke Daftar Payment Terms" />
                        </div>

                        <dl class="grid grid-cols-2 gap-4 max-w-2xl">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Kode</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ paymentTerm.code }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nama</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ paymentTerm.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Jumlah Hari</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ paymentTerm.days }} hari</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Perusahaan</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ paymentTerm.company?.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <span :class="paymentTerm.is_active ? 'text-green-600' : 'text-red-600'">
                                        {{ paymentTerm.is_active ? 'Aktif' : 'Non-aktif' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="col-span-2" v-if="paymentTerm.description">
                                <dt class="text-sm font-medium text-gray-500">Deskripsi</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ paymentTerm.description }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
