<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { formatNumber } from '@/utils/numberFormat';
import PaymentModal from './Partials/PaymentModal.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

const props = defineProps({
    asset: {
        type: Object,
        required: true
    },
    payments: {
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

const currentSort = ref({ key: props.sort || 'due_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const showPaymentModal = ref(false);
const selectedPayment = ref(null);
const showCancelConfirmation = ref(false);
const tableHeaders = [
    { key: 'due_date', label: 'Tanggal Jatuh Tempo' },
    { key: 'payment_date', label: 'Tanggal Bayar' },
    { key: 'notes', label: 'Catatan' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'principal_portion', label: 'Porsi Pokok' },
    { key: 'interest_portion', label: 'Porsi Bunga' },
    { key: 'journal_id', label: 'No. Jurnal' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const indexRoute = { name: 'asset-financing-payments.index' };

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
            { value: 'pending', label: 'Menunggu' },
            { value: 'paid', label: 'Lunas' },
            { value: 'overdue', label: 'Terlambat' }
        ],
        multiple: true,
        placeholder: 'Pilih status',
        label: 'Status'
    }
]);

const columnFormatters = {
    due_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    payment_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    amount: (value) => formatNumber(value),
    principal_portion: (value) => formatNumber(value),
    interest_portion: (value) => formatNumber(value),
    status: (value) => ({
        'pending': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Menunggu</span>',
        'paid': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Lunas</span>',
        'overdue': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Terlambat</span>'
    })[value] || value
};

const columnRenderers = {
    journal_id: (value) => value ? `<a href="${route('journals.show', value)}" class="text-blue-500 hover:text-blue-700" target="_blank">${props.payments.data.find(payment => payment.journal_id === value)?.journal?.journal_number}</a>` : '-'
};

const sortableColumns = ['due_date', 'payment_date', 'amount', 'principal_portion', 'interest_portion', 'status'];
const defaultSort = { key: 'due_date', order: 'desc' };

function deletePayment(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-financing-payments.destroy', [id]), {
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

    router.delete(route('asset-financing-payments.bulk-delete'), {
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
    router.get(route('asset-financing-payments.index', props.asset.id), {
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
    router.get(route('asset-financing-payments.index', props.asset.id), {
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

function openPaymentModal(payment) {
    selectedPayment.value = payment;
    showPaymentModal.value = true;
}

function closePaymentModal() {
    selectedPayment.value = null;
    showPaymentModal.value = false;
}

function handlePaymentSubmitted(payment) {
    // Handle payment submission logic here
}

function openCancelPaymentModal(payment) {
    selectedPayment.value = payment;
    showCancelConfirmation.value = true;
}

function closeCancelConfirmation() {
    selectedPayment.value = null;
    showCancelConfirmation.value = false;
}

function cancelPayment() {
    router.delete(route('asset-financing-payments.cancel', selectedPayment.value.id), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            closeCancelConfirmation();
        }
    });
}
</script>

<template>
    <Head title="Pembayaran Pembiayaan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pembayaran Pembiayaan</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 mb-6">
                <div class="p-6">
                    <div class="mb-6">
                        <AppBackLink :href="route('assets.show', asset.id)" :text="`Kembali ke Detail Aset: ${asset.name}`" />
                    </div>

                    <h3 class="text-lg font-semibold mb-4">Informasi Aset</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Nama Aset</p>
                            <p class="font-medium">{{ asset?.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Jumlah Pembiayaan</p>
                            <p class="font-medium">{{ formatNumber(asset.financing_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Perusahaan</p>
                            <p class="font-medium">{{ asset.branch.branch_group.company.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Cabang</p>
                            <p class="font-medium">{{ asset.branch.name }}</p>
                        </div>
                    </div>
                </div>

                <div class="text-gray-900">
                    <AppDataTable
                        :data="payments"
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
                        :createRoute="{ name: 'asset-financing-payments.create', params: { asset: asset.id } }"
                        :editRoute="{ name: 'asset-financing-payments.edit' }"
                        :deleteRoute="{ name: 'asset-financing-payments.destroy' }"
                        @delete="deletePayment"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <button
                                v-if="item.status === 'pending'"
                                @click="openPaymentModal(item)"
                                class="text-green-600 hover:text-green-900 mr-3"
                            >
                                Bayar
                            </button>

                            <button
                                v-if="item.status === 'paid'"
                                @click="openCancelPaymentModal(item)"
                                class="text-red-600 hover:text-red-900 mr-3"
                            >
                                Batal
                            </button>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <PaymentModal
        v-if="showPaymentModal"
        :show="showPaymentModal"
        :asset="asset"
        :payment="selectedPayment"
        :accounts="accounts"
        @close="closePaymentModal"
        @submitted="handlePaymentSubmitted"
    />

    <DeleteConfirmationModal
        :show="showCancelConfirmation"
        title="Batalkan Pembayaran"
        message="Apakah Anda yakin ingin membatalkan pembayaran ini?"
        confirmButtonText="Batalkan Pembayaran"
        @close="closeCancelConfirmation"
        @confirm="cancelPayment"
    />
</template> 
