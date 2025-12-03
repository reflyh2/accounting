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
    salesOrder: Object,
    defaultLines: {
        type: Array,
        default: () => [],
    },
    selectedSalesOrderLabel: String,
    salesOrderSearchUrl: String,
    primaryCurrency: Object,
});

const selectedSalesOrderId = ref(props.invoice?.sales_order_id || props.salesOrder?.id || null);

const form = useForm({
    sales_order_id: selectedSalesOrderId.value,
    invoice_date: props.invoice?.invoice_date || new Date().toISOString().split('T')[0],
    due_date: props.invoice?.due_date || null,
    customer_invoice_number: props.invoice?.customer_invoice_number || '',
    exchange_rate: props.invoice?.exchange_rate || props.salesOrder?.exchange_rate || 1,
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

const canEditSalesOrder = computed(() => !props.invoice);

const salesOrderTableHeaders = [
    { key: 'order_number', label: 'Nomor SO' },
    { key: 'partner.name', label: 'Customer' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'actions', label: '' }
];

watch(() => props.salesOrder, (newSo) => {
    if (newSo) {
        selectedSalesOrderId.value = newSo.id;
        form.sales_order_id = newSo.id;
        if (!props.invoice) {
            form.exchange_rate = newSo.exchange_rate || form.exchange_rate;
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
        sales_order_line_id: line.sales_order_line_id,
        sales_delivery_line_id: line.sales_delivery_line_id,
        delivery_number: line.sales_delivery_line?.sales_delivery?.delivery_number,
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
        sales_order_line_id: line.sales_order_line_id,
        sales_delivery_line_id: line.sales_delivery_line_id,
        delivery_number: line.delivery_number,
        description: line.description,
        uom_label: line.uom_label,
        quantity: Number(line.quantity),
        unit_price: Number(line.unit_price),
        tax_amount: Number(line.tax_amount || 0),
        max_quantity: Number(line.max_quantity || line.quantity),
        available_quantity: Number(line.available_quantity || line.quantity),
        ordered_quantity: Number(line.ordered_quantity || 0),
        delivered_quantity: Number(line.delivered_quantity || 0),
        invoiced_quantity: Number(line.invoiced_quantity || 0),
    }));
}

function removeLine(index) {
    form.lines.splice(index, 1);
}

function addLine() {
    form.lines.push({
        sales_order_line_id: null,
        sales_delivery_line_id: null,
        delivery_number: '',
        description: '',
        uom_label: '',
        quantity: 0,
        unit_price: 0,
        tax_amount: 0,
        max_quantity: null,
    });
}

function handleSalesOrderSelect(salesOrder) {
    selectedSalesOrderId.value = salesOrder.id;
    form.sales_order_id = salesOrder.id;

    router.reload({
        only: ['salesOrder', 'defaultLines'],
        data: { sales_order_id: salesOrder.id }
    });
}

function submitForm(createAnother = false) {
    form.create_another = createAnother;

    if (props.invoice) {
        form.put(route('sales-invoices.update', props.invoice.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('sales-invoices.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            },
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-6">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <AppPopoverSearch
                            v-if="canEditSalesOrder"
                            v-model="selectedSalesOrderId"
                            :url="salesOrderSearchUrl"
                            :headers="salesOrderTableHeaders"
                            :selectedLabel="selectedSalesOrderLabel"
                            placeholder="Cari Sales Order..."
                            label="Sales Order:"
                            @select="handleSalesOrderSelect"
                        />
                        <div v-else class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Sales Order:</label>
                            <p class="mt-1 text-sm text-gray-900">{{ selectedSalesOrderLabel }}</p>
                        </div>
                    </div>

                    <AppInput
                        v-model="form.invoice_date"
                        type="date"
                        label="Tanggal Faktur:"
                        :error="form.errors.invoice_date"
                        required
                    />

                    <AppInput
                        v-model="form.due_date"
                        type="date"
                        label="Tanggal Jatuh Tempo:"
                        :error="form.errors.due_date"
                    />

                    <AppInput
                        v-model="form.customer_invoice_number"
                        label="Nomor Faktur Customer:"
                        :error="form.errors.customer_invoice_number"
                    />

                    <AppInput
                        v-model="form.exchange_rate"
                        type="number"
                        step="0.000001"
                        label="Kurs:"
                        :error="form.errors.exchange_rate"
                        required
                    />
                </div>

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                    class="mt-4"
                />
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Faktur Penjualan</h3>
                <p class="mb-2">Faktur penjualan adalah dokumen yang dikeluarkan kepada customer untuk menagih pembayaran atas barang/jasa yang telah dikirim.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih Sales Order yang akan difakturkan</li>
                    <li>Tentukan tanggal faktur dan jatuh tempo</li>
                    <li>Nomor faktur customer adalah nomor referensi dari customer</li>
                    <li>Kurs digunakan untuk konversi mata uang jika berbeda</li>
                    <li>Detail faktur akan otomatis terisi dari delivery yang tersedia</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm px-1.5 py-1.5">No. Pengiriman</th>
                        <th class="border border-gray-300 text-sm px-1.5 py-1.5">Deskripsi</th>
                        <th class="border border-gray-300 text-sm px-1.5 py-1.5">Qty</th>
                        <th class="border border-gray-300 text-sm px-1.5 py-1.5">Harga Satuan</th>
                        <th class="border border-gray-300 text-sm px-1.5 py-1.5">Total</th>
                        <th class="border border-gray-300 text-sm px-1.5 py-1.5">Pajak</th>
                        <th class="border border-gray-300 px-1.5 py-1.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(line, index) in form.lines" :key="index">
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="line.delivery_number"
                                :error="form.errors[`lines.${index}.sales_delivery_line_id`]"
                                readonly
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="line.description"
                                :error="form.errors[`lines.${index}.description`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="line.quantity"
                                type="number"
                                step="0.001"
                                :error="form.errors[`lines.${index}.quantity`]"
                                :max="line.max_quantity"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="line.unit_price"
                                type="number"
                                step="0.01"
                                :error="form.errors[`lines.${index}.unit_price`]"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-right">
                            {{ formatNumber((line.quantity || 0) * (line.unit_price || 0)) }}
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="line.tax_amount"
                                type="number"
                                step="0.01"
                                :error="form.errors[`lines.${index}.tax_amount`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                            <button type="button" @click="removeLine(index)" class="text-red-500 hover:text-red-700">
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>

                <tfoot>
                    <tr class="text-sm">
                        <th class="border border-gray-300 px-4 py-2 text-right" colspan="4">Subtotal</th>
                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(subtotal) }}</th>
                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(taxTotal) }}</th>
                        <th></th>
                    </tr>
                    <tr class="text-sm">
                        <th class="border border-gray-300 px-4 py-2 text-right" colspan="5">Total</th>
                        <th class="border border-gray-300 px-4 py-2 text-right" colspan="2">{{ formatNumber(totalAmount) }}</th>
                    </tr>
                </tfoot>
            </table>
            <div class="flex mt-2 mb-4">
                <button type="button" @click="addLine" class="flex items-center text-main-500 hover:text-main-700">
                    <ArrowPathIcon class="w-6 h-6 mr-2" /> Tambah Baris
                </button>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ invoice ? 'Ubah' : 'Tambah' }} Faktur Penjualan
            </AppPrimaryButton>
            <AppUtilityButton v-if="!invoice" type="button" @click="submitForm(true)" class="mr-2">
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('sales-invoices.index'))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
