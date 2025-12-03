<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import { TrashIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    invoice: Object,
    purchaseOrder: Object,
    defaultLines: {
        type: Array,
        default: () => [],
    },
    selectedPurchaseOrderLabel: String,
    purchaseOrderSearchUrl: String,
    primaryCurrency: Object,
});

const selectedPurchaseOrderId = ref(props.invoice?.purchase_order_id || props.purchaseOrder?.id || null);

const form = useForm({
    purchase_order_id: selectedPurchaseOrderId.value,
    invoice_date: props.invoice?.invoice_date || new Date().toISOString().split('T')[0],
    due_date: props.invoice?.due_date || null,
    vendor_invoice_number: props.invoice?.vendor_invoice_number || '',
    exchange_rate: props.invoice?.exchange_rate || props.purchaseOrder?.exchange_rate || 1,
    notes: props.invoice?.notes || '',
    lines: props.invoice ? normalizeExistingLines(props.invoice.lines || []) : cloneDefaultLines(props.defaultLines),
});

const subtotal = computed(() => {
    return form.lines.reduce((sum, line) => {
        return sum + (Number(line.quantity || 0) * Number(line.unit_price || 0));
    }, 0);
});

const taxTotal = computed(() => {
    return form.lines.reduce((sum, line) => sum + Number(line.tax_amount || 0), 0);
});

const totalAmount = computed(() => subtotal.value + taxTotal.value);

const baseTotal = computed(() => totalAmount.value * Number(form.exchange_rate || 1));

const canEditPurchaseOrder = computed(() => !props.invoice);

const purchaseOrderTableHeaders = [
    { key: 'order_number', label: 'Nomor PO' },
    { key: 'partner.name', label: 'Supplier' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'actions', label: '' }
];

watch(() => props.purchaseOrder, (newPo) => {
    if (newPo) {
        selectedPurchaseOrderId.value = newPo.id;
        form.purchase_order_id = newPo.id;
        if (!props.invoice) {
            form.exchange_rate = newPo.exchange_rate || form.exchange_rate;
        }
    }
});

watch(() => props.defaultLines, (lines) => {
    if (!props.invoice && Array.isArray(lines) && lines.length) {
        form.lines = cloneDefaultLines(lines);
    }
});

function normalizeExistingLines(lines) {
    return lines.map((line) => ({
        purchase_order_line_id: line.purchase_order_line_id,
        goods_receipt_line_id: line.goods_receipt_line_id,
        goods_receipt_number: line.goods_receipt_line?.goods_receipt?.receipt_number,
        description: line.description,
        uom_label: line.uom_label,
        quantity: Number(line.quantity),
        unit_price: Number(line.unit_price),
        tax_amount: Number(line.tax_amount || 0),
        max_quantity: Number(line.quantity) || null,
    }));
}

function cloneDefaultLines(lines) {
    return lines.map((line) => ({
        purchase_order_line_id: line.purchase_order_line_id,
        goods_receipt_line_id: line.goods_receipt_line_id,
        goods_receipt_number: line.goods_receipt_number,
        description: line.description,
        uom_label: line.uom_label,
        quantity: Number(line.quantity),
        unit_price: Number(line.unit_price),
        tax_amount: Number(line.tax_amount || 0),
        max_quantity: Number(line.max_quantity || line.available_quantity),
    }));
}

function handlePurchaseOrderChange(newValue) {
    selectedPurchaseOrderId.value = newValue;
    form.purchase_order_id = newValue;

    if (!newValue) {
        form.lines = [];
        return;
    }

    router.reload({
        only: ['purchaseOrder', 'defaultLines', 'selectedPurchaseOrderLabel'],
        data: { purchase_order_id: newValue },
        preserveScroll: true,
        onSuccess: () => {
            form.lines = cloneDefaultLines(props.defaultLines);
        },
    });
}

function removeLine(index) {
    form.lines.splice(index, 1);
}

function clampQuantity(index) {
    const line = form.lines[index];
    if (!line || line.max_quantity === null || line.max_quantity === undefined) {
        return;
    }

    if (Number(line.quantity) > Number(line.max_quantity)) {
        line.quantity = Number(line.max_quantity);
    }

    if (Number(line.quantity) < 0) {
        line.quantity = 0;
    }
}

function resetLinesFromDefaults() {
    form.lines = cloneDefaultLines(props.defaultLines);
    form.clearErrors('lines');
}

function lineSubtotal(line) {
    return Number(line.quantity || 0) * Number(line.unit_price || 0);
}

function submit(createAnother = false) {
    const url = props.invoice
        ? route('purchase-invoices.update', props.invoice.id)
        : route('purchase-invoices.store');

    form.transform(data => ({
        ...data,
        ...(props.invoice ? { _method: 'put' } : { create_another: createAnother }),
    }));

    form.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.invoice && createAnother) {
                form.reset('vendor_invoice_number', 'notes');
            }
        },
        onFinish: () => form.transform(data => data),
    });
}
</script>

<template>
    <form @submit.prevent="submit(false)" class="space-y-6">
        <div class="grid md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <AppPopoverSearch
                    v-model="selectedPurchaseOrderId"
                    label="Purchase Order"
                    placeholder="Pilih Purchase Order"
                    hint="Gunakan pencarian untuk menemukan purchase order lintas perusahaan."
                    :url="purchaseOrderSearchUrl"
                    :tableHeaders="purchaseOrderTableHeaders"
                    :displayKeys="['order_number', 'partner.name', 'branch.name', 'currency.code']"
                    :initialDisplayValue="selectedPurchaseOrderLabel || ''"
                    :error="form.errors.purchase_order_id"
                    :disabled="!canEditPurchaseOrder"
                    modalTitle="Pilih Purchase Order"
                    @update:modelValue="handlePurchaseOrderChange"
                />
                <div class="grid md:grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.invoice_date"
                        type="date"
                        label="Tanggal Faktur"
                        :error="form.errors.invoice_date"
                        required
                    />
                    <AppInput
                        v-model="form.due_date"
                        type="date"
                        label="Jatuh Tempo"
                        :error="form.errors.due_date"
                    />
                </div>
                <AppInput
                    v-model="form.vendor_invoice_number"
                    label="Nomor Dokumen Vendor"
                    :error="form.errors.vendor_invoice_number"
                    placeholder="Nomor invoice dari supplier"
                />
                <AppInput
                    v-model="form.exchange_rate"
                    label="Kurs ke Mata Uang Dasar"
                    :error="form.errors.exchange_rate"
                    type="number"
                    step="0.000001"
                    min="0.000001"
                    required
                />
                <AppTextarea
                    v-model="form.notes"
                    label="Catatan"
                    :error="form.errors.notes"
                    rows="3"
                />
            </div>

            <div class="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-4 text-sm space-y-2 min-h-48">
                <p class="font-semibold text-gray-700">Ringkasan Purchase Order</p>
                <div v-if="purchaseOrder" class="space-y-1">
                    <p><span class="text-gray-500">Nomor:</span> {{ purchaseOrder.order_number }}</p>
                    <p><span class="text-gray-500">Supplier:</span> {{ purchaseOrder.partner?.name }}</p>
                    <p><span class="text-gray-500">Cabang:</span> {{ purchaseOrder.branch?.name }}</p>
                    <p><span class="text-gray-500">Mata Uang:</span> {{ purchaseOrder.currency?.code }}</p>
                </div>
                <div v-else class="text-gray-500">
                    Pilih Purchase Order untuk menampilkan ringkasan dan sisa penerimaan yang siap difakturkan.
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg">
            <div class="flex items-center justify-between p-4 border-b border-gray-100">
                <div>
                    <p class="font-semibold">Detail Faktur</p>
                    <p class="text-xs text-gray-500">Ambil data dari penerimaan barang yang belum difakturkan.</p>
                </div>
                <div class="flex gap-2">
                    <AppUtilityButton type="button" @click="resetLinesFromDefaults" :disabled="!defaultLines.length">
                        <ArrowPathIcon class="w-4 h-4 mr-2" /> Gunakan Sisa GRN
                    </AppUtilityButton>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">GRN</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Deskripsi</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Qty</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Harga</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Pajak</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Subtotal</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!form.lines.length">
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">
                                Tidak ada baris faktur. Pilih Purchase Order untuk memuat sisa penerimaan.
                            </td>
                        </tr>
                        <tr v-for="(line, index) in form.lines" :key="index" class="border-b border-gray-100">
                            <td class="px-4 py-2 align-top">
                                <p class="font-medium">{{ line.goods_receipt_number || '-' }}</p>
                            </td>
                            <td class="px-4 py-2 align-top">
                                <AppInput
                                    v-model="line.description"
                                    label="Deskripsi"
                                    :margins="{ top: 0, right: 0, bottom: 1, left: 0 }"
                                    :error="form.errors[`lines.${index}.description`]"
                                />
                                <p class="text-xs text-gray-500 mt-1">
                                    Sisa: {{ formatNumber(line.max_quantity ?? line.quantity) }} {{ line.uom_label }}
                                </p>
                            </td>
                            <td class="px-4 py-2 align-top w-40">
                                <AppInput
                                    v-model="line.quantity"
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    label="Qty"
                                    :error="form.errors[`lines.${index}.quantity`]"
                                    :margins="{ top: 0, right: 0, bottom: 1, left: 0 }"
                                    @blur="clampQuantity(index)"
                                />
                                <p class="text-xs text-gray-500 mt-1">{{ line.uom_label }}</p>
                            </td>
                            <td class="px-4 py-2 align-top w-40">
                                <AppInput
                                    v-model="line.unit_price"
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    label="Harga"
                                    :error="form.errors[`lines.${index}.unit_price`]"
                                    :margins="{ top: 0, right: 0, bottom: 1, left: 0 }"
                                />
                            </td>
                            <td class="px-4 py-2 align-top w-36">
                                <AppInput
                                    v-model="line.tax_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    label="Pajak"
                                    :error="form.errors[`lines.${index}.tax_amount`]"
                                    :margins="{ top: 0, right: 0, bottom: 1, left: 0 }"
                                />
                            </td>
                            <td class="px-4 py-2 align-top w-40">
                                <p class="font-semibold text-right">
                                    {{ formatNumber(lineSubtotal(line)) }}
                                </p>
                            </td>
                            <td class="px-2 py-2 align-top">
                                <button type="button" class="text-red-500 hover:text-red-700" @click="removeLine(index)">
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500">Ringkasan Nilai</p>
                <p class="text-lg font-semibold text-gray-800">
                    {{ purchaseOrder?.currency?.symbol || '' }} {{ formatNumber(totalAmount) }}
                </p>
                <p class="text-xs text-gray-500">
                    {{ primaryCurrency?.symbol || '' }} {{ formatNumber(baseTotal) }} (konversi)
                </p>
            </div>
            <div class="text-sm text-gray-600 space-y-1">
                <p>Subtotal: {{ formatNumber(subtotal) }}</p>
                <p>Pajak: {{ formatNumber(taxTotal) }}</p>
                <p>Total: {{ formatNumber(totalAmount) }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <AppPrimaryButton type="submit">
                {{ invoice ? 'Simpan Perubahan' : 'Simpan Draf' }}
            </AppPrimaryButton>
            <AppUtilityButton
                v-if="!invoice"
                type="button"
                @click="submit(true)"
            >
                Simpan &amp; Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton type="button" @click="$inertia.visit(route('purchase-invoices.index'))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>

