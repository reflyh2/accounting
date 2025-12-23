<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { ref } from 'vue';

const props = defineProps({
    bankAccount: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteBankAccount = () => {
    form.delete(route('company-bank-accounts.destroy', props.bankAccount.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Rekening Bank" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Rekening Bank Perusahaan</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('company-bank-accounts.index', filters)" text="Kembali ke Daftar Rekening" />
                        </div>

                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold">{{ bankAccount.bank_name }} - {{ bankAccount.account_number }}</h3>
                            <div class="flex items-center gap-2">
                                <Link :href="route('company-bank-accounts.edit', bankAccount.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Hapus" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-6 text-sm">
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ bankAccount.company?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Akun GL:</p>
                                <p>{{ bankAccount.account ? `${bankAccount.account.code} - ${bankAccount.account.name}` : '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nama Bank:</p>
                                <p>{{ bankAccount.bank_name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nomor Rekening:</p>
                                <p>{{ bankAccount.account_number }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nama Pemilik:</p>
                                <p>{{ bankAccount.account_holder_name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ bankAccount.branch_name || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kode SWIFT:</p>
                                <p>{{ bankAccount.swift_code || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">IBAN:</p>
                                <p>{{ bankAccount.iban || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Mata Uang:</p>
                                <p>{{ bankAccount.currency?.code || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>
                                    <span v-if="bankAccount.is_active" class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Aktif</span>
                                    <span v-else class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">Nonaktif</span>
                                    <span v-if="bankAccount.is_primary" class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Utama</span>
                                </p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p class="whitespace-pre-line">{{ bankAccount.notes || '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Rekening Bank"
            @close="showDeleteConfirmation = false"
            @confirm="deleteBankAccount"
        />
    </AuthenticatedLayout>
</template>
