<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    account: Object,
    filters: Object,
});

const accountTypes = [
   { value: 'kas_bank', label: 'Kas & Bank' },
   { value: 'piutang_usaha', label: 'Piutang Usaha' },
   { value: 'persediaan', label: 'Persediaan' },
   { value: 'aset_lancar_lainnya', label: 'Aset Lancar Lainnya' },
   { value: 'aset_tetap', label: 'Aset Tetap' },
   { value: 'akumulasi_penyusutan', label: 'Akumulasi Penyusutan' },
   { value: 'aset_lainnya', label: 'Aset Lainnya' },
   { value: 'utang_usaha', label: 'Utang Usaha' },
   { value: 'liabilitas_jangka_pendek', label: 'Liabilitas Jangka Pendek' },
   { value: 'liabilitas_jangka_panjang', label: 'Liabilitas Jangka Panjang' },
   { value: 'modal', label: 'Modal' },
   { value: 'pendapatan', label: 'Pendapatan' },
   { value: 'beban_pokok_penjualan', label: 'Beban Pokok Penjualan' },
   { value: 'beban', label: 'Beban' },
   { value: 'beban_lainnya', label: 'Beban Lainnya' },
   { value: 'pendapatan_lainnya', label: 'Pendapatan Lainnya' },
];

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAccount = () => {
    form.delete(route('accounts.destroy', props.account.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Akun" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Akun</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('accounts.index', filters)" text="Kembali ke Daftar Akun" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ account.code }} - {{ account.name }}</h3>
                            <div class="flex items-center">
                                <Link :href="route('accounts.edit', account.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Kode Akun:</p>
                                <p>{{ account.code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nama Akun:</p>
                                <p>{{ account.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tipe:</p>
                                <p>{{ accountTypes.find(type => type.value === account.type)?.label || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Akun Induk:</p>
                                <p>{{ account.parent ? account.parent.code + ' - ' + account.parent.name : '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <ul>
                                    <li v-for="company in account.companies" :key="company.id">
                                        {{ company.name }}
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <p class="font-semibold">Mata Uang:</p>
                                <ul>
                                    <li v-for="currency in account.currencies" :key="currency.id">
                                        {{ currency.name }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Akun"
            @close="showDeleteConfirmation = false"
            @confirm="deleteAccount"
        />
    </AuthenticatedLayout>
</template>