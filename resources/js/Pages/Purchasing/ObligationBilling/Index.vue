<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    filters: Object,
    companies: Array,
    suppliers: Array,
    branches: Array,
    currencies: Array,
    outstanding: Array,
    today: String,
});

// Header filters that drive the outstanding query (companyId + supplierId).
const filterForm = ref({
    company_id: props.filters?.company_id || null,
    partner_id: props.filters?.partner_id || null,
});

function reload() {
    router.get(route('obligation-billing.index'), {
        company_id: filterForm.value.company_id,
        partner_id: filterForm.value.partner_id,
    }, { preserveScroll: true, preserveState: true });
}

watch(() => filterForm.value.company_id, () => { filterForm.value.partner_id = null; reload(); });
watch(() => filterForm.value.partner_id, reload);

// Selected obligations + PI header form.
const selectedIds = ref([]);

const allSelected = computed(() =>
    props.outstanding.length > 0 && selectedIds.value.length === props.outstanding.length
);

function toggleAll() {
    selectedIds.value = allSelected.value
        ? []
        : props.outstanding.map((row) => row.id);
}

const selectedTotal = computed(() => {
    return props.outstanding
        .filter((row) => selectedIds.value.includes(row.id))
        .reduce((sum, row) => sum + Number(row.supplier_cost || 0), 0);
});

const piForm = useForm({
    company_id: null,
    branch_id: null,
    partner_id: null,
    currency_id: props.currencies?.[0]?.id || null,
    invoice_date: props.today,
    due_date: '',
    exchange_rate: 1,
    vendor_invoice_number: '',
    notes: '',
    booking_line_ids: [],
});

// Mirror header filters into the PI form so the user doesn't repeat themselves.
watch(filterForm, (val) => {
    piForm.company_id = val.company_id;
    piForm.partner_id = val.partner_id;
    piForm.branch_id = props.branches?.[0]?.id || null;
}, { deep: true, immediate: true });

watch(() => props.branches, (val) => {
    if (val?.length === 1) piForm.branch_id = val[0].id;
});

function submit() {
    piForm.booking_line_ids = selectedIds.value;
    piForm.post(route('obligation-billing.store'), { preserveScroll: true });
}

function formatDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', { dateStyle: 'medium' });
}
</script>

<template>
    <Head title="Tagihan dari Booking" />

    <AuthenticatedLayout>
        <template #header><h2>Tagihan Pemasok dari Booking</h2></template>

        <div class="mx-auto bg-white shadow-sm sm:rounded border border-gray-200 p-6 space-y-4">
            <p class="text-sm text-gray-600">
                Pilih perusahaan dan pemasok untuk melihat tagihan booking-reseller yang belum
                dibuatkan PI. Centang baris yang akan dibayar (biasanya sesuai statement dari
                pemasok), lalu klik <strong>Buat PI</strong>. Sistem membuat satu PI draft yang
                bisa Anda tinjau dan post lewat alur normal Pembelian → Faktur Pembelian.
            </p>

            <!-- Header filters -->
            <div class="grid grid-cols-2 gap-3 max-w-xl">
                <AppSelect
                    v-model="filterForm.company_id"
                    :options="companies.map((c) => ({ value: c.id, label: c.name }))"
                    placeholder="Pilih Perusahaan"
                    label="Perusahaan:"
                />
                <AppSelect
                    v-model="filterForm.partner_id"
                    :options="suppliers.map((s) => ({ value: s.id, label: `${s.code} — ${s.name}` }))"
                    placeholder="Pilih Pemasok"
                    label="Pemasok:"
                    :disabled="!filterForm.company_id"
                />
            </div>

            <!-- Outstanding obligations table -->
            <div v-if="filterForm.company_id && filterForm.partner_id" class="mt-4">
                <h3 class="text-lg font-semibold mb-2">Tagihan Tersisa</h3>
                <table v-if="outstanding.length" class="min-w-full text-sm border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-2 py-2 border">
                                <input type="checkbox" :checked="allSelected" @change="toggleAll" />
                            </th>
                            <th class="px-3 py-2 border text-left">Booking</th>
                            <th class="px-3 py-2 border text-left">Item</th>
                            <th class="px-3 py-2 border text-left">Periode</th>
                            <th class="px-3 py-2 border text-right">Qty</th>
                            <th class="px-3 py-2 border text-right">Biaya Pemasok</th>
                            <th class="px-3 py-2 border text-left">Ref Pemasok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in outstanding" :key="row.id" class="border-b hover:bg-gray-50">
                            <td class="px-2 py-2 border text-center">
                                <input type="checkbox" :value="row.id" v-model="selectedIds" />
                            </td>
                            <td class="px-3 py-2 border">{{ row.booking_number }}</td>
                            <td class="px-3 py-2 border">
                                {{ row.product_name }}
                                <span v-if="row.booking_subtype" class="text-xs text-gray-500">/ {{ row.booking_subtype }}</span>
                            </td>
                            <td class="px-3 py-2 border">{{ formatDate(row.start_datetime) }} → {{ formatDate(row.end_datetime) }}</td>
                            <td class="px-3 py-2 border text-right">{{ row.qty }}</td>
                            <td class="px-3 py-2 border text-right">{{ formatNumber(row.supplier_cost) }}</td>
                            <td class="px-3 py-2 border text-xs">{{ row.supplier_invoice_ref || '—' }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="5" class="px-3 py-2 border text-right font-semibold">Total Dipilih</td>
                            <td class="px-3 py-2 border text-right font-semibold">{{ formatNumber(selectedTotal) }}</td>
                            <td class="px-3 py-2 border"></td>
                        </tr>
                    </tfoot>
                </table>
                <div v-else class="text-sm text-gray-500 py-4 text-center border border-dashed rounded">
                    Tidak ada tagihan terbuka untuk pemasok ini pada perusahaan ini.
                </div>
            </div>

            <!-- PI header form -->
            <div v-if="selectedIds.length" class="mt-6 border-t pt-4">
                <h3 class="text-lg font-semibold mb-3">Detail PI</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div class="grid grid-cols-3 gap-3">
                        <AppSelect
                            v-model="piForm.branch_id"
                            :options="branches.map((b) => ({ value: b.id, label: b.name }))"
                            placeholder="Pilih Cabang"
                            label="Cabang:"
                            required
                            :error="piForm.errors.branch_id"
                        />
                        <AppSelect
                            v-model="piForm.currency_id"
                            :options="currencies.map((c) => ({ value: c.id, label: `${c.code} — ${c.name}` }))"
                            label="Mata Uang:"
                            required
                            :error="piForm.errors.currency_id"
                        />
                        <AppInput
                            v-model="piForm.exchange_rate"
                            :numberFormat="true"
                            label="Kurs:"
                            :error="piForm.errors.exchange_rate"
                        />
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <AppInput
                            v-model="piForm.invoice_date"
                            type="date"
                            label="Tanggal Faktur:"
                            required
                            :error="piForm.errors.invoice_date"
                        />
                        <AppInput
                            v-model="piForm.due_date"
                            type="date"
                            label="Jatuh Tempo:"
                            :error="piForm.errors.due_date"
                        />
                        <AppInput
                            v-model="piForm.vendor_invoice_number"
                            label="No. Faktur Vendor:"
                            :error="piForm.errors.vendor_invoice_number"
                        />
                    </div>
                    <AppTextarea
                        v-model="piForm.notes"
                        label="Catatan:"
                        rows="2"
                        :error="piForm.errors.notes"
                    />

                    <div class="flex gap-2 justify-end">
                        <AppSecondaryButton type="button" @click="selectedIds = []">Batal</AppSecondaryButton>
                        <AppPrimaryButton type="submit" :disabled="piForm.processing">
                            Buat PI Draft
                        </AppPrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
