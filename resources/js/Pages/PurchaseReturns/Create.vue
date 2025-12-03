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
    filters: {
        type: Object,
        default: () => ({}),
    },
    goodsReceipts: {
        type: Array,
        default: () => [],
    },
    selectedGoodsReceipt: {
        type: Object,
        default: null,
    },
    reasonOptions: {
        type: Array,
        default: () => [],
    },
});

const today = new Date().toISOString().split('T')[0];

const form = useForm({
    goods_receipt_id: props.selectedGoodsReceipt?.id ?? null,
    return_date: today,
    reason_code: props.reasonOptions?.[0]?.value ?? null,
    notes: '',
    lines: props.selectedGoodsReceipt
        ? props.selectedGoodsReceipt.lines.map(line => ({
            goods_receipt_line_id: line.id,
            description: line.description,
            variant: line.variant,
            uom: line.uom,
            available_quantity: Number(line.available_quantity || 0),
            quantity: 0,
            unit_price: Number(line.unit_price || 0),
        }))
        : [],
});

const goodsReceiptOptions = computed(() =>
    props.goodsReceipts.map(receipt => ({
        value: receipt.id,
        label: `${receipt.receipt_number} — ${receipt.partner || 'Tanpa Supplier'} (${Number(receipt.available_quantity || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 })})`,
    }))
);

const hasSelectedReceipt = computed(() => Boolean(props.selectedGoodsReceipt));
const hasAvailableLines = computed(() =>
    form.lines.some(line => Number(line.available_quantity || 0) > 0)
);
const hasSelectedQuantity = computed(() =>
    form.lines.some(line => Number(line.quantity || 0) > 0)
);

const totalValue = computed(() =>
    form.lines.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_price || 0), 0)
);

function changeGoodsReceipt(value) {
    if (!value) {
        router.get(route('purchase-returns.create'));
        return;
    }

    router.get(route('purchase-returns.create'), {
        goods_receipt_id: value,
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
    const max = Number(line.available_quantity || 0);
    if (line.quantity > max) {
        line.quantity = max;
    }
    if (line.quantity < 0) {
        line.quantity = 0;
    }
}

function returnAll() {
    form.lines = form.lines.map(line => ({
        ...line,
        quantity: Number(line.available_quantity || 0),
    }));
}

function filteredLinesPayload(lines) {
    return lines
        .filter(line => Number(line.quantity || 0) > 0)
        .map(line => ({
            goods_receipt_line_id: line.goods_receipt_line_id,
            quantity: Number(line.quantity),
        }));
}

function submit() {
    if (!hasSelectedReceipt.value) {
        form.setError('goods_receipt_id', 'Pilih Goods Receipt terlebih dahulu.');
        return;
    }
    if (!hasSelectedQuantity.value) {
        form.setError('lines', 'Minimal satu baris harus memiliki jumlah retur.');
        return;
    }

    form.transform(data => ({
        ...data,
        lines: filteredLinesPayload(data.lines),
    }));

    form.post(route('purchase-returns.store'), {
        preserveScroll: true,
        onFinish: () => form.transform(data => data),
    });
}
</script>

<template>
    <Head title="Buat Retur Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Retur Pembelian</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900 space-y-6">
                    <AppBackLink :href="route('purchase-returns.index', filters)" text="Kembali ke Daftar Retur" />

                    <div class="grid md:grid-cols-3 gap-4">
                        <AppSelect
                            v-model="form.goods_receipt_id"
                            :options="goodsReceiptOptions"
                            label="Goods Receipt"
                            placeholder="Pilih GRN"
                            :error="form.errors.goods_receipt_id"
                            @update:modelValue="changeGoodsReceipt"
                        />

                        <AppInput
                            v-model="form.return_date"
                            type="date"
                            label="Tanggal Retur"
                            :error="form.errors.return_date"
                        />

                        <AppSelect
                            v-model="form.reason_code"
                            :options="reasonOptions"
                            label="Alasan"
                            placeholder="Pilih alasan"
                            :error="form.errors.reason_code"
                        />
                    </div>

                    <AppTextarea
                        v-model="form.notes"
                        label="Catatan"
                        placeholder="Tambahkan keterangan tambahan"
                        :error="form.errors.notes"
                    />

                    <div v-if="!hasSelectedReceipt" class="border border-dashed border-gray-300 rounded p-6 text-center text-gray-600 text-sm">
                        Pilih Goods Receipt yang akan diretur.
                    </div>

                    <div v-else>
                        <div class="flex flex-col gap-1 mb-4">
                            <p class="font-semibold text-lg">{{ selectedGoodsReceipt.receipt_number }}</p>
                            <p class="text-sm text-gray-500">
                                PO: {{ selectedGoodsReceipt.purchase_order?.order_number || '—' }} · Supplier:
                                {{ selectedGoodsReceipt.purchase_order?.partner || '—' }}
                            </p>
                            <p class="text-sm text-gray-500">
                                Lokasi: {{ selectedGoodsReceipt.location?.name || '—' }}
                            </p>
                        </div>

                        <div v-if="!hasAvailableLines" class="mb-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                            Seluruh barang pada GRN ini sudah tidak tersedia untuk retur.
                        </div>

                        <p v-if="form.errors.lines" class="text-sm text-red-600 mb-2">{{ form.errors.lines }}</p>

                        <div class="flex justify-end mb-3">
                            <AppSecondaryButton
                                type="button"
                                @click="returnAll"
                                :disabled="!hasAvailableLines"
                            >
                                Retur Semua Sisa
                            </AppSecondaryButton>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 border border-gray-200 text-left">Barang</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Sisa</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Retur</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Harga</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, index) in form.lines" :key="line.goods_receipt_line_id" class="border-t">
                                        <td class="px-3 py-2 border border-gray-200 align-top">
                                            <div class="font-medium text-gray-900">{{ line.variant?.product_name || line.description }}</div>
                                            <div class="text-xs text-gray-500">{{ line.variant?.sku }}</div>
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right align-top">
                                            {{ Number(line.available_quantity || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 }) }}
                                            <div class="text-xs text-gray-500">{{ line.uom?.code }}</div>
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 align-top">
                                            <AppInput
                                                v-model="line.quantity"
                                                type="number"
                                                step="0.001"
                                                min="0"
                                                :max="line.available_quantity"
                                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                                :disabled="Number(line.available_quantity || 0) === 0"
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
                            <div>Total baris: {{ form.lines.length }}</div>
                            <div class="font-semibold">
                                Nilai Retur: {{ totalValue.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <AppPrimaryButton
                            type="button"
                            :disabled="form.processing || !hasSelectedReceipt || !hasSelectedQuantity || !hasAvailableLines"
                            @click="submit"
                        >
                            Posting Retur Pembelian
                        </AppPrimaryButton>
                        <AppSecondaryButton :href="route('purchase-returns.index', filters)" as="a">
                            Batal
                        </AppSecondaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


