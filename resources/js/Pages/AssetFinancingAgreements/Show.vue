<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { format, parseISO } from 'date-fns';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';
import { formatNumber } from '@/utils/numberFormat';
import AppDataTable from '@/Components/AppDataTable.vue';
import { usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    agreement: Object,
    schedules: Object,
    filters: Object,
    perPage: [Number, String],
    sort: String,
    order: String,
    statusOptions: Object,
    paymentFrequencyOptions: Object,
    interestCalculationMethodOptions: Object,
});

const statusLabels = {
    'unpaid': 'Belum Dibayar',
    'paid': 'Lunas',
    'partial': 'Dibayar Sebagian',
};

const currentSort = ref({ key: props.sort || 'payment_date', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'payment_number', label: 'No.' },
    { key: 'payment_date', label: 'Tanggal' },
    { key: 'principal_amount', label: 'Pokok' },
    { key: 'interest_amount', label: 'Bunga' },
    { key: 'total_payment', label: 'Total' },
    { key: 'status', label: 'Status' },
    { key: 'paid_date', label: 'Tanggal Bayar' },
    { key: 'paid_principal_amount', label: 'Pokok Dibayar' },
    { key: 'paid_interest_amount', label: 'Bunga Dibayar' },
    { key: 'total_paid_amount', label: 'Total Dibayar' },
];

const columnFormatters = {
    payment_date: (value) => format(parseISO(value), 'dd MMM yyyy'),
    principal_amount: (value) => formatNumber(value),
    interest_amount: (value) => formatNumber(value),
    total_payment: (value) => formatNumber(value),
    paid_date: (value) => value ? format(parseISO(value), 'dd MMM yyyy') : '-',
    paid_principal_amount: (value) => formatNumber(value),
    paid_interest_amount: (value) => formatNumber(value),
    total_paid_amount: (value) => formatNumber(value),
};

const columnRenderers = {
    status: (value) => {
        const statusColors = {
            paid: 'bg-green-100 text-green-800',
            unpaid: 'bg-red-100 text-red-800',
            partial: 'bg-yellow-100 text-yellow-800',
            default: 'bg-gray-100 text-gray-800'
        };
        const color = statusColors[value] || statusColors.default;
        return `<span class="px-2 py-1 text-xs font-medium rounded-full ${color}">${statusLabels[value] || value}</span>`;
    },
};

const sortableColumns = ['payment_date', 'status', 'paid_date'];
const defaultSort = { key: 'payment_date', order: 'asc' };

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAgreement = () => {
    form.delete(route('asset-financing-agreements.destroy', props.agreement.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

const handleSort = (newSort) => {
    currentSort.value = newSort;
    router.get(route('asset-financing-agreements.show', {id: props.agreement.id}), {
        ...route().params,
        ...currentFilters.value,
        sort: newSort.key,
        order: newSort.order,
        per_page: props.per_page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('asset-financing-agreements.show', props.agreement.id), {
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

const printAgreement = () => {
    window.open(route('asset-financing-agreements.print', props.agreement.id), '_blank');
};
</script>

<template>
    <Head :title="`Detail Perjanjian Pembiayaan Aset - ${agreement.number}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Perjanjian Pembiayaan Aset</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('asset-financing-agreements.index', filters)" text="Kembali ke Daftar Perjanjian Pembiayaan Aset" />
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">{{ agreement.number }}</h3>
                        <div class="flex items-center">                              
                            <a :href="route('asset-financing-agreements.print', agreement.id)" target="_blank">
                                <AppPrintButton title="Print" />
                            </a>
                            <Link :href="route('asset-financing-agreements.edit', agreement.id)">
                                <AppEditButton title="Edit" />
                            </Link>
                            <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 *:py-1 text-sm mb-6">
                        <div>
                            <p class="font-semibold">Perusahaan:</p>
                            <p>{{ agreement.branch.branch_group.company.name }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Cabang:</p>
                            <p>{{ agreement.branch.name }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Tanggal Perjanjian:</p>
                            <p>{{ new Date(agreement.agreement_date).toLocaleDateString('id-ID') }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Kreditor:</p>
                            <p>{{ agreement.creditor.name }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Total Jumlah:</p>
                            <p>{{ formatNumber(agreement.total_amount) }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Bunga Tahunan:</p>
                            <p>{{ agreement.interest_rate }}%</p>
                        </div>
                        <div>
                            <p class="font-semibold">Metode Perhitungan Bunga:</p>
                            <p>{{ interestCalculationMethodOptions[agreement.interest_calculation_method] }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Tanggal Mulai:</p>
                            <p>{{ new Date(agreement.start_date).toLocaleDateString('id-ID') }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Tanggal Selesai:</p>
                            <p>{{ new Date(agreement.end_date).toLocaleDateString('id-ID') }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Frekuensi Pembayaran:</p>
                            <p>{{ paymentFrequencyOptions[agreement.payment_frequency] }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Status:</p>
                            <p>{{ statusOptions[agreement.status] }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="font-semibold">Catatan:</p>
                            <p>{{ agreement.notes || '-' }}</p>
                        </div>
                    </div>

                    <!-- Asset Invoice Details -->
                    <div class="mt-6">
                        <h4 class="text-lg font-semibold mb-2">Detail Invoice Aset</h4>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="font-semibold">Nomor Invoice:</p>
                                    <p>{{ agreement.asset_invoice.number }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Tanggal Invoice:</p>
                                    <p>{{ new Date(agreement.asset_invoice.invoice_date).toLocaleDateString('id-ID') }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Aset:</p>
                                    <p>{{ agreement.asset_invoice.asset_invoice_details?.[0]?.asset?.name || 'No Asset' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Vendor:</p>
                                    <p>{{ agreement.asset_invoice.partner.name }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Schedule -->
                    <div class="mt-6">
                        <h4 class="text-lg font-semibold mb-2">Jadwal Pembayaran</h4>
                        <AppDataTable
                            :data="schedules"
                            :tableHeaders="tableHeaders"
                            :columnFormatters="columnFormatters"
                            :columnRenderers="columnRenderers"
                            :enableFilters="false"
                            :enableBulkActions="false"
                            :indexRoute="{ name: 'asset-financing-agreements.show', params: { agreement: agreement.id } }"
                            :sortable="sortableColumns"
                            :defaultSort="defaultSort"
                            :currentSort="currentSort"
                            :perPage="perPage"
                            @sort="handleSort"
                            @filter="handleFilter"
                        />
                    </div>

                    <!-- Creation/Update Info -->
                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <div class="grid grid-cols-2 gap-4 text-xs text-gray-500">
                            <div v-if="agreement.created_by">
                                <p class="font-semibold">Dibuat oleh:</p>
                                <p>{{ agreement.created_by?.name }} - {{ new Date(agreement.created_at).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div v-if="agreement.updated_by">
                                <p class="font-semibold">Diubah oleh:</p>
                                <p>{{ agreement.updated_by?.name }} - {{ new Date(agreement.updated_at).toLocaleDateString('id-ID') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Data"
            message="Apakah Anda yakin ingin menghapus perjanjian ini?"
            @close="showDeleteConfirmation = false"
            @confirm="deleteAgreement"
        />
    </AuthenticatedLayout>
</template> 