<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    salesOrder: {
        type: Object,
        required: true,
    },
    locations: {
        type: Array,
        default: () => [],
    },
});

const defaultLocationId =
    props.salesOrder.lines.find((line) => line.reservation_location?.id)?.reservation_location?.id ??
    props.locations?.[0]?.id ??
    null;

const mappedLines = props.salesOrder.lines
    .filter((line) => Number(line.remaining_quantity) > 0)
    .map((line) => ({
        sales_order_line_id: line.id,
        description: line.description,
        sku: line.sku,
        uom: line.uom,
        remaining_quantity: Number(line.remaining_quantity),
        reservation_location_id: line.reservation_location?.id ?? null,
        reservation_location: line.reservation_location,
        quantity: Number(line.remaining_quantity),
    }));

const form = useForm({
    sales_order_id: props.salesOrder.id,
    delivery_date: new Date().toISOString().split('T')[0],
    location_id: defaultLocationId,
    notes: '',
    lines: mappedLines,
});

const locationOptions = computed(() =>
    props.locations.map((location) => ({
        value: location.id,
        label: location.label,
    }))
);

const totalQuantity = computed(() =>
    form.lines.reduce((sum, line) => sum + Number(line.quantity || 0), 0)
);

const hasLines = computed(() => form.lines.length > 0);

const showLocationWarning = computed(() =>
    form.lines.some(
        (line) => line.reservation_location_id && line.reservation_location_id !== form.location_id
    )
);

function clampLineQuantity(line) {
    if (line.quantity < 0) {
        line.quantity = 0;
        return;
    }

    if (line.quantity > line.remaining_quantity) {
        line.quantity = line.remaining_quantity;
    }
}

function submit() {
    form.transform((data) => ({
        sales_order_id: Number(data.sales_order_id),
        delivery_date: data.delivery_date,
        location_id: data.location_id ? Number(data.location_id) : null,
        notes: data.notes,
        lines: data.lines
            .filter((line) => Number(line.quantity) > 0)
            .map((line) => ({
                sales_order_line_id: Number(line.sales_order_line_id),
                quantity: Number(line.quantity),
            })),
    }));

    form.post(route('sales-deliveries.store'), {
        preserveScroll: true,
        onFinish: () => form.transform((data) => data),
    });
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="grid md:grid-cols-2 gap-4">
            <AppInput
                v-model="form.delivery_date"
                type="date"
                label="Tanggal Delivery"
                :error="form.errors.delivery_date"
                required
            />
            <AppSelect
                v-model="form.location_id"
                :options="locationOptions"
                label="Lokasi Pengiriman"
                placeholder="Pilih lokasi"
                :error="form.errors.location_id"
                required
            />
        </div>

        <AppTextarea
            v-model="form.notes"
            label="Catatan"
            placeholder="Opsional"
            :error="form.errors.notes"
        />

        <div
            v-if="showLocationWarning"
            class="bg-yellow-50 border border-yellow-400 text-yellow-800 rounded px-4 py-3 text-sm"
        >
            Beberapa baris memiliki lokasi reservasi berbeda dari lokasi pengiriman yang dipilih.
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">Detail Barang</h3>
                <span class="text-sm text-gray-500">
                    Total Rencana Kirim:
                    <strong>{{ formatNumber(totalQuantity) }}</strong>
                </span>
            </div>
            <p v-if="form.errors.lines" class="text-sm text-red-600">{{ form.errors.lines }}</p>

            <div v-if="!hasLines" class="border border-dashed border-gray-300 rounded p-6 text-center">
                Semua baris pada Sales Order ini telah terkirim.
            </div>

            <div v-else class="overflow-auto border border-gray-200 rounded">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">SKU</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Deskripsi</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">Sisa</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">Kirim</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr v-for="(line, index) in form.lines" :key="line.sales_order_line_id">
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ line.sku }}</p>
                                <p class="text-xs text-gray-500">{{ line.description }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p>{{ line.reservation_location?.name || 'Lokasi belum ditentukan' }}</p>
                                <p class="text-xs text-gray-500" v-if="line.reservation_location">
                                    Lokasi Reservasi: {{ line.reservation_location?.code }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ formatNumber(line.remaining_quantity) }} {{ line.uom }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <AppInput
                                    v-model="line.quantity"
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    :max="line.remaining_quantity"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                    :error="form.errors[`lines.${index}.quantity`]"
                                    @change="clampLineQuantity(line)"
                                    @blur="clampLineQuantity(line)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <AppPrimaryButton type="submit" :disabled="!hasLines || totalQuantity <= 0 || form.processing">
                Posting Pengiriman
            </AppPrimaryButton>
            <AppSecondaryButton :href="route('sales-orders.show', salesOrder.id)" as="a">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>

