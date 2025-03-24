<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import { ref, computed } from 'vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';


const props = defineProps({
    maintenanceType: Object,
    maintenanceRecords: Object,
    filters: Object,
    sort: String,
    order: String,
    perPage: [String, Number],
});

const currentSort = ref({ key: props.sort || 'maintenance_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteMaintenanceType = () => {
    form.delete(route('asset-maintenance-types.destroy', props.maintenanceType.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

function formatNumber(number) {
    if (number === null || number === undefined) return '-';
    return new Intl.NumberFormat('id-ID').format(number);
}

const tableHeaders = [
    { key: 'asset.name', label: 'Aset' },
    { key: 'maintenance_date', label: 'Tanggal Pemeliharaan' },
    { key: 'cost', label: 'Biaya' },
    { key: 'payment_status', label: 'Status Pembayaran' },
    { key: 'performed_by', label: 'Dilakukan Oleh' },
    { key: 'actions', label: '' }
];

const sortableColumns = ['asset.name', 'maintenance_date', 'cost', 'payment_status', 'performed_by'];
const defaultSort = { key: 'maintenance_date', order: 'desc' };

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('asset-maintenance-types.show', props.maintenanceType.id), {
        ...route().params,
        ...currentFilters.value,
        sort: newSort.key,
        order: newSort.order,
        per_page: props.perPage,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('asset-maintenance-types.show', props.maintenanceType.id), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
        per_page: newFilters.per_page || props.perPage,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const paymentStatusLabels = {
    'pending': 'Belum Dibayar',
    'paid': 'Sudah Dibayar'
};

const columnFormatters = {
    maintenance_date: (value) => formatDate(value),
    cost: (value) => formatNumber(value),
    payment_status: (value) => value === 'paid' ? 
        '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Sudah Dibayar</span>' : 
        '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Belum Dibayar</span>'
};
</script>

<template>
    <Head :title="`Detail Tipe Pemeliharaan: ${maintenanceType.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Tipe Pemeliharaan</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('asset-maintenance-types.index')" text="Kembali ke Daftar Tipe Pemeliharaan" />
                    </div>
                    
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">{{ maintenanceType.name }}</h3>
                        <div class="flex items-center">
                            <Link :href="route('asset-maintenance-types.edit', maintenanceType.id)">
                                <AppEditButton title="Edit" />
                            </Link>
                            <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <!-- Basic Information -->
                        <div>
                            <h3 class="text-md font-semibold mb-3">Informasi Dasar</h3>
                            <dl class="grid grid-cols-1 gap-4 *:py-1 text-sm">
                                <div>
                                    <dt class="font-semibold text-gray-500">Nama Tipe</dt>
                                    <dd>{{ maintenanceType.name }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-500">Kategori Aset</dt>
                                    <dd>{{ maintenanceType.asset_category?.name || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-500">Interval Pemeliharaan</dt>
                                    <dd>{{ maintenanceType.maintenance_interval || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-500">Interval (Hari)</dt>
                                    <dd>{{ maintenanceType.maintenance_interval_days || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-500">Deskripsi</dt>
                                    <dd>{{ maintenanceType.description || '-' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Financial & Usage Information -->
                        <div>
                            <h3 class="text-md font-semibold mb-3">Informasi Penggunaan</h3>
                            <dl class="grid grid-cols-1 gap-4 *:py-1 text-sm">
                                <div>
                                    <dt class="font-semibold text-gray-500">Akun Biaya Pemeliharaan</dt>
                                    <dd>{{ maintenanceType.maintenance_cost_account?.name || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-500">Perusahaan</dt>
                                    <dd>
                                        <ul class="list-disc list-inside">
                                            <li v-for="company in maintenanceType.companies" :key="company.id">
                                                {{ company.name }}
                                            </li>
                                        </ul>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-500">Jumlah Catatan Pemeliharaan</dt>
                                    <dd>{{ maintenanceType.maintenance_records_count || 0 }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Asset Maintenance Records List -->
                    <div class="mt-8">
                        <div class="flex justify-between items-center">
                            <h4 class="text-lg font-semibold">Daftar Pemeliharaan Aset {{ maintenanceType.name }}</h4>
                        </div>
                    </div>
                </div>

                <AppDataTable
                    :data="maintenanceRecords"
                    :tableHeaders="tableHeaders"
                    :columnFormatters="columnFormatters"
                    :indexRoute="{ name: 'asset-maintenance-types.show' }"
                    :viewRoute="{ name: 'asset-maintenance-records.show' }"
                    :sortable="sortableColumns"
                    :defaultSort="defaultSort"
                    :currentSort="currentSort"
                    :perPage="perPage"
                    :enableBulkActions="false"
                    :showPerPage="false"
                    :showDownload="false"
                    :enableFilters="false"
                    :customFilters="[]"
                    :filters="{}"
                    @sort="handleSort"
                />
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Tipe Pemeliharaan"
            @close="showDeleteConfirmation = false"
            @confirm="deleteMaintenanceType"
        >
            <template #message>
                Apakah Anda yakin ingin menghapus tipe pemeliharaan ini? Tipe pemeliharaan yang memiliki catatan pemeliharaan tidak dapat dihapus.
            </template>
        </DeleteConfirmationModal>
    </AuthenticatedLayout>
</template> 