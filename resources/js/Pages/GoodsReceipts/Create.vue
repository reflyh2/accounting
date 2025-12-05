<script setup>
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

const props = defineProps({
    purchaseOrders: {
        type: Array,
        default: () => [],
    },
    selectedPurchaseOrder: {
        type: Object,
        default: null,
    },
    locations: {
        type: Array,
        default: () => [],
    },
    valuationMethods: {
        type: Array,
        default: () => [],
    },
    defaultValuationMethod: {
        type: String,
        default: 'fifo',
    },
});

const today = new Date().toISOString().split('T')[0];

const form = useForm({
    purchase_order_id: props.selectedPurchaseOrder?.id ?? null,
    receipt_date: today,
    location_id: props.locations?.[0]?.id ?? null,
    valuation_method: props.defaultValuationMethod,
    notes: '',
    lines: props.selectedPurchaseOrder
        ? props.selectedPurchaseOrder.lines.map(line => ({
            purchase_order_line_id: line.id,
            quantity: line.remaining_quantity,
            remaining_quantity: line.remaining_quantity,
            ordered_quantity: line.ordered_quantity,
            received_quantity: line.received_quantity,
            unit_price: line.unit_price,
            description: line.description,
            variant: line.variant,
            line_number: line.line_number,
            uom: line.uom,
        }))
        : [],
});

const purchaseOrderOptions = computed(() =>
    props.purchaseOrders.map(order => ({
        value: order.id,
        label: `${order.order_number} — ${order.partner?.name ?? 'Tanpa Supplier'}`,
    }))
);

const locationOptions = computed(() =>
    props.locations.map(location => ({
        value: location.id,
        label: location.label,
    }))
);

const valuationOptions = computed(() =>
    props.valuationMethods.map(method => ({
        value: method.value,
        label: method.label,
    }))
);

const hasSelectedPo = computed(() => Boolean(props.selectedPurchaseOrder));

const hasRemainingLines = computed(() =>
    form.lines.some(line => Number(line.remaining_quantity) > 0)
);

const hasSelectedQuantity = computed(() =>
    form.lines.some(line => Number(line.quantity || 0) > 0)
);

const totalValue = computed(() =>
    form.lines.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_price || 0), 0)
);

function changePurchaseOrder(value) {
    if (!value) {
        router.get(route('goods-receipts.create'));
        return;
    }

    router.get(route('goods-receipts.create'), {
        purchase_order_id: value,
    }, {
        preserveState: false,
        preserveScroll: true,
    });
}

function clampQuantity(index) {
    const line = form.lines[index];
    if (!line) {
        return;
    }
    const max = Number(line.remaining_quantity || 0);
    if (line.quantity > max) {
        line.quantity = max;
    }
    if (line.quantity < 0) {
        line.quantity = 0;
    }
}

function receiveAllRemaining() {
    form.lines = form.lines.map((line) => ({
        ...line,
        quantity: Number(line.remaining_quantity || 0),
    }));
}

function filteredLinesPayload(lines) {
    return lines
        .filter(line => Number(line.quantity || 0) > 0)
        .map(line => ({
            purchase_order_line_id: line.purchase_order_line_id,
            quantity: Number(line.quantity),
        }));
}

function submit() {
    if (!hasSelectedQuantity.value) {
        form.setError('lines', 'Minimal satu baris harus memiliki jumlah penerimaan.');
        return;
    }

    form.transform(data => ({
        ...data,
        lines: filteredLinesPayload(data.lines),
    }));

    form.post(route('goods-receipts.store'), {
        preserveScroll: true,
        onFinish: () => form.transform(data => data),
    });
}
</script>

<template>
    <Head title="Buat Penerimaan Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Penerimaan Pembelian</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border border-gray-200">
                <div class="p-6 text-gray-900 space-y-6">
                    <AppBackLink :href="route('goods-receipts.index')" text="Kembali ke Daftar Penerimaan Pembelian" />

                    <div class="grid md:grid-cols-3 gap-4">
                        <AppSelect
                            v-model="form.purchase_order_id"
                            :options="purchaseOrderOptions"
                            label="Purchase Order"
                            placeholder="Pilih Purchase Order"
                            :error="form.errors.purchase_order_id"
                            @update:modelValue="changePurchaseOrder"
                        />

                        <AppInput
                            v-model="form.receipt_date"
                            type="date"
                            label="Tanggal Penerimaan"
                            :error="form.errors.receipt_date"
                        />

                        <AppSelect
                            v-model="form.location_id"
                            :options="locationOptions"
                            label="Lokasi Tujuan"
                            placeholder="Pilih Lokasi"
                            :disabled="!hasSelectedPo"
                            :error="form.errors.location_id"
                        />
                    </div>

                    <div class="grid md:grid-cols-3 gap-4">
                        <AppSelect
                            v-model="form.valuation_method"
                            :options="valuationOptions"
                            label="Metode Penilaian"
                            placeholder="Pilih Metode"
                            :error="form.errors.valuation_method"
                        />
                        <div class="md:col-span-2">
                            <AppTextarea
                                v-model="form.notes"
                                label="Catatan"
                                placeholder="Tambahkan keterangan tambahan"
                                :error="form.errors.notes"
                            />
                        </div>
                    </div>

                    <div v-if="!hasSelectedPo" class="border border-dashed border-gray-300 rounded p-6 text-center text-gray-600 text-sm">
                        Pilih Purchase Order terlebih dahulu untuk melanjutkan proses penerimaan barang.
                    </div>

                    <div v-else>
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <p class="font-semibold text-lg">{{ selectedPurchaseOrder.order_number }}</p>
                                <p class="text-sm text-gray-500">
                                    Supplier: {{ selectedPurchaseOrder.partner?.name || '—' }} · Cabang: {{ selectedPurchaseOrder.branch?.name || '—' }}
                                </p>
                            </div>
                            <AppSecondaryButton type="button" @click="receiveAllRemaining" :disabled="!hasRemainingLines">
                                Terima Semua Sisa
                            </AppSecondaryButton>
                        </div>

                        <div v-if="!hasRemainingLines" class="mb-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                            Purchase Order ini sudah diterima sepenuhnya.
                        </div>

                        <p v-if="form.errors.lines" class="text-sm text-red-600 mb-2">{{ form.errors.lines }}</p>

                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 border border-gray-200 text-left">Barang</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Dipesan</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Diterima</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Sisa</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Terima</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Harga</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, index) in form.lines" :key="line.purchase_order_line_id" class="border-t">
                                        <td class="px-3 py-2 border border-gray-200 align-top">
                                            <div class="font-medium text-gray-900">{{ line.variant?.product_name || line.description }}</div>
                                            <div class="text-xs text-gray-500">{{ line.variant?.sku }}</div>
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right align-top">
                                            {{ Number(line.ordered_quantity || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 }) }}
                                            <div class="text-xs text-gray-500">{{ line.uom?.code }}</div>
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right align-top">
                                            {{ Number(line.received_quantity || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 }) }}
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right align-top">
                                            {{ Number(line.remaining_quantity || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 }) }}
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 align-top">
                                            <AppInput
                                                v-model="line.quantity"
                                                type="number"
                                                step="0.001"
                                                min="0"
                                                :max="line.remaining_quantity"
                                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                                :disabled="Number(line.remaining_quantity || 0) === 0"
                                                @blur="clampQuantity(index)"
                                            />
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right align-top">
                                            {{ Number(line.unit_price || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right align-top">
                                            {{
                                                (Number(line.quantity || 0) * Number(line.unit_price || 0))
                                                    .toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between bg-gray-50 border border-gray-200 rounded p-3 text-sm">
                            <div>
                                Total baris: {{ form.lines.length }}
                            </div>
                            <div class="font-semibold">
                                Nilai Penerimaan: {{ totalValue.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <AppPrimaryButton
                            type="button"
                            :disabled="form.processing || !hasSelectedPo || !hasRemainingLines || !hasSelectedQuantity"
                            @click="submit"
                        >
                            Posting Penerimaan Pembelian
                        </AppPrimaryButton>
                        <AppSecondaryButton :href="route('goods-receipts.index')" as="a">
                            Batal
                        </AppSecondaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

