<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    mode: {
        type: String,
        default: 'create',
    },
    transaction: {
        type: Object,
        default: null,
    },
    locations: {
        type: Array,
        default: () => [],
    },
    productVariants: {
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

const defaultLine = () => ({
    product_variant_id: null,
    uom_id: null,
    quantity: 0,
    unit_cost: null,
});

const form = useForm({
    transaction_date: props.transaction?.transaction_date ?? new Date().toISOString().split('T')[0],
    location_id: props.transaction?.location_from?.id ?? props.locations?.[0]?.id ?? null,
    valuation_method: props.transaction?.valuation_method ?? props.defaultValuationMethod,
    reason: props.transaction?.source_type ?? '',
    notes: props.transaction?.notes ?? '',
    lines: props.transaction?.lines?.map(line => ({
        product_variant_id: line.product_variant_id,
        uom_id: line.uom_id,
        quantity: line.quantity,
        unit_cost: line.unit_cost,
    })) ?? [defaultLine()],
});

const variantOptions = computed(() =>
    props.productVariants.map(variant => ({
        value: variant.id,
        label: variant.label,
        uom_id: variant.uom_id,
        uom_label: variant.uom_label,
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

const totalQuantity = computed(() => form.lines.reduce((sum, line) => sum + Number(line.quantity || 0), 0));
const totalValue = computed(() => form.lines.reduce((sum, line) => sum + ((Number(line.quantity || 0) > 0 ? Number(line.unit_cost || 0) : 0) * Number(line.quantity || 0)), 0));

function addLine() {
    form.lines.push(defaultLine());
}

function removeLine(index) {
    form.lines.splice(index, 1);
}

function onVariantChange(index) {
    const line = form.lines[index];
    const variant = props.productVariants.find(variant => variant.id === line.product_variant_id);
    line.uom_id = variant ? variant.uom_id : null;
}

function submit() {
    form.transform((data) => ({
        ...data,
        location_id: data.location_id ? Number(data.location_id) : null,
        lines: data.lines.map((line) => ({
            product_variant_id: line.product_variant_id ? Number(line.product_variant_id) : null,
            uom_id: line.uom_id ? Number(line.uom_id) : null,
            quantity: Number(line.quantity ?? 0),
            unit_cost: line.unit_cost === null || line.unit_cost === '' ? null : Number(line.unit_cost),
        })),
    }));

    const options = {
        preserveScroll: true,
        onFinish: () => form.transform((data) => data),
    };

    if (props.mode === 'edit' && props.transaction) {
        form.put(route('inventory.adjustments.update', props.transaction.id), options);
    } else {
        form.post(route('inventory.adjustments.store'), options);
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="grid md:grid-cols-3 gap-4">
            <AppInput
                v-model="form.transaction_date"
                type="date"
                label="Tanggal"
                :error="form.errors.transaction_date"
                required
            />
            <AppSelect
                v-model="form.location_id"
                :options="locationOptions"
                label="Lokasi"
                placeholder="Pilih lokasi"
                :error="form.errors.location_id"
                required
            />
            <AppSelect
                v-model="form.valuation_method"
                :options="valuationOptions"
                label="Metode Penilaian"
                placeholder="Pilih metode"
                :error="form.errors.valuation_method"
            />
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <AppInput
                v-model="form.reason"
                label="Alasan Penyesuaian"
                placeholder="Mis. Stok Opname, Kerusakan"
                :error="form.errors.reason"
            />
        </div>

        <AppTextarea
            v-model="form.notes"
            label="Catatan"
            placeholder="Tuliskan catatan tambahan"
            :error="form.errors.notes"
        />

        <div class="bg-yellow-50 border border-yellow-200 text-xs text-yellow-900 rounded p-3">
            <p class="font-semibold">Catatan:</p>
            <p>Masukkan jumlah positif untuk penyesuaian masuk, jumlah negatif untuk penyesuaian keluar.</p>
            <p>Harga satuan wajib diisi untuk baris dengan jumlah positif.</p>
        </div>

        <div class="mt-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-semibold">Detail Penyesuaian</h3>
                <AppSecondaryButton type="button" @click="addLine">
                    Tambah Baris
                </AppSecondaryButton>
            </div>
            <p v-if="form.errors.lines" class="text-sm text-red-600 mb-2">{{ form.errors.lines }}</p>

            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 border border-gray-200 text-left">Produk</th>
                            <th class="px-3 py-2 border border-gray-200 text-left">Qty (+/-)</th>
                            <th class="px-3 py-2 border border-gray-200 text-left">Harga Satuan</th>
                            <th class="px-3 py-2 border border-gray-200"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in form.lines" :key="index" class="border-t">
                            <td class="px-3 py-2 border border-gray-200 align-top w-2/5">
                                <AppSelect
                                    v-model="line.product_variant_id"
                                    :options="variantOptions"
                                    placeholder="Pilih Produk"
                                    :error="form.errors[`lines.${index}.product_variant_id`]"
                                    @update:model-value="onVariantChange(index)"
                                    required
                                />
                                <div v-if="line.uom_id" class="text-xs text-gray-500 mt-1">
                                    UOM: {{
                                        props.productVariants.find(variant => variant.id === line.product_variant_id)?.uom_label || '-'
                                    }}
                                </div>
                            </td>
                            <td class="px-3 py-2 border border-gray-200 align-top">
                                <AppInput
                                    v-model="line.quantity"
                                    type="number"
                                    step="0.001"
                                    label=""
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                    :error="form.errors[`lines.${index}.quantity`]"
                                    required
                                />
                            </td>
                            <td class="px-3 py-2 border border-gray-200 align-top">
                                <AppInput
                                    v-model="line.unit_cost"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    label=""
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                    :error="form.errors[`lines.${index}.unit_cost`]"
                                    :placeholder="Number(line.quantity || 0) > 0 ? 'Wajib diisi' : 'Opsional'"
                                />
                            </td>
                            <td class="px-3 py-2 border border-gray-200 align-top text-right">
                                <button
                                    type="button"
                                    class="text-red-600 hover:underline text-sm"
                                    @click="removeLine(index)"
                                    v-if="form.lines.length > 1"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between bg-gray-50 border border-gray-200 rounded p-3 text-sm">
                <div>Total Baris: {{ form.lines.length }}</div>
                <div class="font-semibold">
                    Total Qty: {{ totalQuantity.toLocaleString('id-ID', { maximumFractionDigits: 3 }) }} |
                    Nilai Masuk: {{ totalValue.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </div>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <AppPrimaryButton type="submit" :disabled="form.processing">
                {{ props.mode === 'edit' ? 'Simpan Perubahan' : 'Simpan Penyesuaian' }}
            </AppPrimaryButton>
            <AppSecondaryButton :href="route('inventory.adjustments.index')" as="a">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>

