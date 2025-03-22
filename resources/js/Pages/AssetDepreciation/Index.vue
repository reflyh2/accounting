<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { formatNumber } from '@/utils/numberFormat';
import DepreciationModal from './Partials/DepreciationModal.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

const props = defineProps({
    asset: {
        type: Object,
        required: true
    },
    entries: {
        type: Object,
        required: true
    },
    accounts: {
        type: Array,
        required: true
    },
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const depreciationMethods = [
    { value: 'straight-line', label: 'Garis Lurus' },
    { value: 'declining-balance', label: 'Saldo Menurun' },
];

const currentSort = ref({ key: props.sort || 'entry_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const showProcessModal = ref(false);
const selectedEntry = ref(null);
const showCancelConfirmation = ref(false);

const tableHeaders = [
    { key: 'entry_date', label: 'Tanggal Penyusutan' },
    { key: 'period_start', label: 'Awal Periode' },
    { key: 'period_end', label: 'Akhir Periode' },
    { key: 'type', label: 'Tipe' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'cumulative_amount', label: 'Akumulasi' },
    { key: 'remaining_value', label: 'Nilai Sisa' },
    { key: 'journal_id', label: 'No. Jurnal' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const indexRoute = { name: 'asset-depreciation.index' };

const customFilters = computed(() => [
    {
        name: 'from_date',
        type: 'date',
        placeholder: 'Dari Tanggal',
        label: 'Dari Tanggal'
    },
    {
        name: 'to_date',
        type: 'date',
        placeholder: 'Sampai Tanggal',
        label: 'Sampai Tanggal'
    },
    {
        name: 'status',
        type: 'select',
        options: [
            { value: 'scheduled', label: 'Terjadwal' },
            { value: 'processed', label: 'Diproses' }
        ],
        multiple: true,
        placeholder: 'Pilih status',
        label: 'Status'
    }
]);

const columnFormatters = {
    entry_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    period_start: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    period_end: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    amount: (value) => formatNumber(value),
    cumulative_amount: (value) => formatNumber(value),
    remaining_value: (value) => formatNumber(value),
    type: (value) => ({
        'depreciation': 'Penyusutan',
        'amortization': 'Amortisasi'
    })[value] || value,
    status: (value) => ({
        'scheduled': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Terjadwal</span>',
        'processed': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Diproses</span>'
    })[value] || value
};

const columnRenderers = {
    journal_id: (value) => value ? `<a href="${route('journals.show', value)}" class="text-blue-500 hover:text-blue-700" target="_blank">${props.entries.data.find(entry => entry.journal_id === value)?.journal?.journal_number}</a>` : '-'
};

const sortableColumns = ['entry_date', 'period_start', 'period_end', 'amount', 'cumulative_amount', 'remaining_value', 'type', 'status'];
const defaultSort = { key: 'entry_date', order: 'desc' };

function deleteEntry(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-depreciation.destroy', [id]), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery
        },
    });
}

function handleBulkDelete(ids) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-depreciation.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
            ids: ids,
        },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('asset-depreciation.index', props.asset.id), {
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
    router.get(route('asset-depreciation.index', props.asset.id), {
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

function openProcessModal(entry) {
    selectedEntry.value = entry;
    showProcessModal.value = true;
}

function closeProcessModal() {
    selectedEntry.value = null;
    showProcessModal.value = false;
}

function openCancelConfirmation(entry) {
    selectedEntry.value = entry;
    showCancelConfirmation.value = true;
}

function closeCancelConfirmation() {
    selectedEntry.value = null;
    showCancelConfirmation.value = false;
}

function cancelEntry() {
    router.delete(route('asset-depreciation.cancel', selectedEntry.value.id), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            closeCancelConfirmation();
        }
    });
}

function handleGenerateSchedule() {
    router.get(route('asset-depreciation.generate-schedule', props.asset.id));
}
</script>

<template>
    <Head title="Penyusutan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Penyusutan Aset</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 mb-6">
                <div class="p-6">
                    <div class="mb-6">
                        <AppBackLink :href="route('assets.show', asset.id)" :text="`Kembali ke Detail Aset: ${asset.name}`" />
                    </div>

                    <h3 class="text-lg font-semibold mb-4">Informasi Aset {{ asset?.name }}</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Perusahaan</p>
                            <p class="font-medium">{{ asset.branch.branch_group.company.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Nilai Perolehan</p>
                            <p class="font-medium">{{ formatNumber(asset.purchase_cost) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Cabang</p>
                            <p class="font-medium">{{ asset.branch.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Nilai Residu</p>
                            <p class="font-medium">{{ formatNumber(asset.salvage_value) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Metode Penyusutan</p>
                            <p class="font-medium">{{ depreciationMethods.find(method => method.value === asset.depreciation_method)?.label || 'Garis Lurus' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Akumulasi Penyusutan</p>
                            <p class="font-medium">{{ asset.depreciation_entries_sum_amount ? formatNumber(asset.depreciation_entries_sum_amount) : '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Masa Manfaat</p>
                            <p class="font-medium">{{ asset.useful_life_months }} bulan</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Nilai Sisa</p>
                            <p class="font-medium">{{ formatNumber(asset.purchase_cost - asset.depreciation_entries_sum_amount) }}</p>
                        </div>
                    </div>
                </div>

                <div class="text-gray-900">
                    <AppDataTable
                        :data="entries"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        :indexRoute="indexRoute"
                        :createRoute="{ name: 'asset-depreciation.create', params: { asset: asset.id } }"
                        :editRoute="{ name: 'asset-depreciation.edit' }"
                        :deleteRoute="{ name: 'asset-depreciation.destroy' }"
                        @delete="deleteEntry"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <button
                                v-if="item.status === 'scheduled'"
                                @click="openProcessModal(item)"
                                class="text-green-600 hover:text-green-900 mr-3"
                            >
                                Proses
                            </button>

                            <button
                                v-if="item.status === 'processed'"
                                @click="openCancelConfirmation(item)"
                                class="text-red-600 hover:text-red-900 mr-3"
                            >
                                Batal
                            </button>
                        </template>

                        <template #custom_buttons>
                            <button
                                @click="handleGenerateSchedule"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                Buat Jadwal Penyusutan
                            </button>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <DepreciationModal
        v-if="showProcessModal"
        :show="showProcessModal"
        :asset="asset"
        :entry="selectedEntry"
        :accounts="accounts"
        @close="closeProcessModal"
    />

    <DeleteConfirmationModal
        :show="showCancelConfirmation"
        title="Batalkan Penyusutan"
        message="Apakah Anda yakin ingin membatalkan penyusutan ini?"
        confirmButtonText="Batalkan Penyusutan"
        @close="closeCancelConfirmation"
        @confirm="cancelEntry"
    />
</template> 