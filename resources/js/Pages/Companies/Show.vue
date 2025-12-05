<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import StatusBanner from '@/Components/StatusBanner.vue';
import { ref, computed } from 'vue';
import { Cog8ToothIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    company: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteCompany = () => {
    form.delete(route('companies.destroy', props.company.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

const showBanner = computed(() => {
    return props.company.default_receivable_account_id == null ||
        props.company.default_payable_account_id == null ||
        props.company.default_revenue_account_id == null ||
        props.company.default_cogs_account_id == null ||
        props.company.default_retained_earnings_account_id == null;
});
</script>

<template>
    <Head title="Detail Perusahaan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Perusahaan</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('companies.index', filters)" text="Kembali ke Daftar Perusahaan" />
                        </div>
                        <div v-if="showBanner" class="block w-full mb-4">
                            <StatusBanner type="warning" message="Pengaturan akun standar perusahaan belum diset!" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ company.name }}</h3>
                            <div class="flex items-center">
                                <Link :href="route('companies.default-accounts.edit', company.id)" title="Pengaturan Akun Standar">
                                    <button type="button" class="inline-flex items-center justify-center align-middle h-4 w-4 md:ml-2 text-main-500 hover:text-main-700 focus:outline-none focus:ring-2 focus:ring-main-500 focus:ring-opacity-50">
                                        <Cog8ToothIcon class="h-4 w-4" />
                                    </button>
                                </Link>
                                <Link :href="route('companies.edit', company.id)">
                                    <AppEditButton title="Ubah" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Hapus" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Nama Resmi:</p>
                                <p>{{ company.legal_name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">NPWP:</p>
                                <p>{{ company.tax_id ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">NIB:</p>
                                <p>{{ company.business_registration_number ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Alamat:</p>
                                <p>{{ company.address ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kota:</p>
                                <p>{{ company.city }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Provinsi:</p>
                                <p>{{ company.province }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kode Pos:</p>
                                <p>{{ company.postal_code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Telepon:</p>
                                <p>{{ company.phone }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Email:</p>
                                <p>{{ company.email ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Situs Web:</p>
                                <p>{{ company.website ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Industri:</p>
                                <p>{{ company.industry ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tahun Berdiri:</p>
                                <p>{{ company.year_established ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nomor Izin Usaha:</p>
                                <p>{{ company.business_license_number ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Kadaluarsa Izin Usaha:</p>
                                <p>{{ company.business_license_expiry ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nomor Registrasi Pajak:</p>
                                <p>{{ company.tax_registration_number ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nomor BPJS:</p>
                                <p>{{ company.social_security_number ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Perusahaan"
            @close="showDeleteConfirmation = false"
            @confirm="deleteCompany"
        />
    </AuthenticatedLayout>
</template>