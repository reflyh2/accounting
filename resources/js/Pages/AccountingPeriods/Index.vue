<script setup>
import { ref, watch, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import Modal from '@/Components/Modal.vue';
import { LockClosedIcon, LockOpenIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    periods: Object,
    companies: Array,
    selectedCompanyId: [Number, String],
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'start_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});
const showCreateModal = ref(false);
const periodToClose = ref(null);
const periodToDelete = ref(null);

const months = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

const currentYear = new Date().getFullYear();

const companyOptions = computed(() =>
    props.companies.map(c => ({ value: c.id, label: c.name }))
);

const getInitialCompanyId = () => {
    const val = props.selectedCompanyId || (props.companies[0]?.id ?? '');
    return Array.isArray(val) ? (val[0] ?? '') : val;
};

const createForm = useForm({
    company_id: getInitialCompanyId(),
    month: new Date().getMonth() + 1,
    year: currentYear,
    notes: '',
});

function openCreateModal() {
    createForm.company_id = getInitialCompanyId();
    createForm.month = new Date().getMonth() + 1;
    createForm.year = currentYear;
    createForm.notes = '';
    createForm.clearErrors();
    showCreateModal.value = true;
}

const periodPreview = computed(() => {
    const monthObj = months.find(m => m.value === Number(createForm.month));
    if (!monthObj || !createForm.year) return '';

    const lastDay = new Date(createForm.year, createForm.month, 0).getDate();
    const monthStr = String(createForm.month).padStart(2, '0');
    return `${monthObj.label} ${createForm.year} (01/${monthStr}/${createForm.year} s/d ${lastDay}/${monthStr}/${createForm.year})`;
});

const tableHeaders = [
    { key: 'name', label: 'Nama Periode' },
    { key: 'start_date', label: 'Rentang Tanggal' },
    { key: 'status', label: 'Status' },
    { key: 'closed_by_user.name', label: 'Ditutup Oleh' },
    { key: 'notes', label: 'Catatan' },
    { key: 'actions', label: '' },
];

const columnRenderers = {
    start_date: (val, item) => `${formatDate(item.start_date)} - ${formatDate(item.end_date)}`,
    status: (val, item) => item.status === 'closed' ? 'Closed' : 'Open',
    notes: (val) => val || '-',
};

const customFilters = computed(() => [
    {
        name: 'company_id',
        type: 'select',
        options: companyOptions.value,
        placeholder: 'Pilih perusahaan',
        label: 'Perusahaan',
    },
]);

const sortableColumns = ['name', 'start_date', 'status'];
const defaultSort = { key: 'start_date', order: 'desc' };

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('accounting-periods.index'), {
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
    router.get(route('accounting-periods.index'), {
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

function submitCreate() {
    createForm.post(route('accounting-periods.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset('notes');
        },
    });
}

function confirmClose(period) {
    periodToClose.value = period;
}

function closePeriod() {
    if (!periodToClose.value) return;

    router.post(route('accounting-periods.close', periodToClose.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            periodToClose.value = null;
        },
        onError: () => {
            periodToClose.value = null;
        },
    });
}

function confirmDelete(period) {
    periodToDelete.value = period;
}

function deletePeriod() {
    if (!periodToDelete.value) return;

    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('accounting-periods.destroy', periodToDelete.value.id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
        },
        onSuccess: () => {
            periodToDelete.value = null;
        },
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return '-';
    return new Date(dateTimeStr).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Periode Akuntansi" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Periode Akuntansi (Closing Bulanan)</h2>
        </template>

        <div class="mx-auto space-y-4">
            <!-- Alert Info -->
            <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r shadow-sm">
                <div class="flex items-start">
                    <ExclamationTriangleIcon class="w-5 h-5 text-amber-600 mt-0.5 mr-3 flex-shrink-0" />
                    <div class="text-sm text-amber-800">
                        <p class="font-semibold">Ketentuan Closing Bulanan:</p>
                        <ul class="list-disc list-inside mt-1 space-y-1 text-amber-700 text-xs md:text-sm">
                            <li>Periode yang berstatus <strong>Closed (Ditutup)</strong> mengunci seluruh pembuatan, pengeditan, & penghapusan jurnal serta transaksi terkait.</li>
                            <li>Penutupan periode harus dilakukan <strong>berurutan</strong> (periode bulan sebelumnya harus sudah ditutup).</li>
                            <li>Untuk membuka kunci kembali (reopen), hapus data periode yang bersangkutan.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Data Table using standard AppDataTable component -->
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="periods"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnRenderers="columnRenderers"
                        :useCreateEvent="true"
                        createButtonLabel="Buat Periode Baru"
                        :indexRoute="{ name: 'accounting-periods.index' }"
                        :customFilters="customFilters"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="accounting-periods.index"
                        :enableBulkActions="false"
                        @create="openCreateModal"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <div class="flex items-center space-x-2">
                                <button
                                    v-if="item.status === 'open'"
                                    @click.stop="confirmClose(item)"
                                    class="inline-flex items-center px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded shadow-sm transition-colors"
                                    title="Tutup Periode"
                                >
                                    <LockClosedIcon class="w-3.5 h-3.5 mr-1" />
                                    Tutup
                                </button>
                                <AppDeleteButton
                                    @click.stop="confirmDelete(item)"
                                    title="Hapus / Reopen Periode"
                                />
                            </div>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>

        <!-- Modal Create Period -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" max-width="md">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Buat Periode Akuntansi Baru</h3>
                <p class="text-xs text-gray-500 mb-4">Pilih bulan & tahun periode akuntansi yang ingin dibuka.</p>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <AppSelect
                        v-model="createForm.company_id"
                        :options="companyOptions"
                        label="Perusahaan"
                        :error="createForm.errors.company_id"
                        required
                    />

                    <div class="grid grid-cols-2 gap-4">
                        <AppSelect
                            v-model="createForm.month"
                            :options="months"
                            label="Bulan"
                            :error="createForm.errors.month"
                            required
                        />

                        <AppInput
                            v-model="createForm.year"
                            type="number"
                            label="Tahun"
                            min="2000"
                            max="2100"
                            :error="createForm.errors.year"
                            required
                        />
                    </div>

                    <div class="bg-blue-50 border border-blue-200 p-3 rounded text-xs text-blue-900 font-medium">
                        Periode yang akan dibuat: <br />
                        <span class="font-bold text-blue-700 text-sm">{{ periodPreview }}</span>
                    </div>

                    <AppTextarea
                        v-model="createForm.notes"
                        label="Catatan (Opsional)"
                        placeholder="Catatan tambahan..."
                        :error="createForm.errors.notes"
                        rows="2"
                    />

                    <div class="mt-6 flex justify-end space-x-3 pt-4 border-t border-gray-100">
                        <AppSecondaryButton type="button" @click="showCreateModal = false">Batal</AppSecondaryButton>
                        <AppPrimaryButton type="submit" @click="submitCreate" :disabled="createForm.processing">Simpan Periode</AppPrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal Confirm Close -->
        <Modal :show="!!periodToClose" @close="periodToClose = null" max-width="md">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 mx-auto flex items-center justify-center mb-4">
                    <LockClosedIcon class="w-6 h-6" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Tutup Periode {{ periodToClose?.name }}?</h3>
                <p class="text-sm text-gray-500 mb-6 leading-relaxed">
                    Setelah periode ditutup, seluruh transaksi dan jurnal dari tanggal <strong class="text-gray-800">{{ formatDate(periodToClose?.start_date) }}</strong> s/d <strong class="text-gray-800">{{ formatDate(periodToClose?.end_date) }}</strong> akan dikunci dan tidak dapat ditambah, diubah, atau dihapus.
                </p>

                <div class="flex items-center justify-center space-x-3">
                    <AppSecondaryButton @click="periodToClose = null">Batal</AppSecondaryButton>
                    <button
                        @click="closePeriod"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded shadow transition-colors"
                    >
                        Ya, Tutup Periode
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Modal Confirm Delete -->
        <Modal :show="!!periodToDelete" @close="periodToDelete = null" max-width="md">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 mx-auto flex items-center justify-center mb-4">
                    <ExclamationTriangleIcon class="w-6 h-6" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Hapus / Reopen Periode {{ periodToDelete?.name }}?</h3>
                <p class="text-sm text-gray-500 mb-6 leading-relaxed">
                    Menghapus data periode ini akan membuka kembali (reopen) akses jurnal dan transaksi pada rentang tanggal <strong class="text-gray-800">{{ formatDate(periodToDelete?.start_date) }}</strong> s/d <strong class="text-gray-800">{{ formatDate(periodToDelete?.end_date) }}</strong>.
                </p>

                <div class="flex items-center justify-center space-x-3">
                    <AppSecondaryButton @click="periodToDelete = null">Batal</AppSecondaryButton>
                    <button
                        @click="deletePeriod"
                        class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded shadow transition-colors"
                    >
                        Ya, Hapus Periode
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
