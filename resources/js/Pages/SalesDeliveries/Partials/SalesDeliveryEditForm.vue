<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { ArchiveBoxArrowDownIcon } from '@heroicons/vue/24/outline';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    delivery: Object,
    selectedSalesOrders: Array,
    selectedPartnerId: Number,
    locations: Array,
    filters: Object,
});

// Build initial lines from delivery
function buildInitialLines() {
    return (props.delivery?.lines || []).map((line) => ({
        sales_order_line_id: line.sales_order_line_id,
        quantity: line.quantity,
    }));
}

const form = useForm({
    delivery_date: props.delivery?.delivery_date || new Date().toISOString().split('T')[0],
    location_id: props.delivery?.location_id || null,
    notes: props.delivery?.notes || '',
    lines: buildInitialLines(),
});

const allSoLines = computed(() => {
    const lines = [];
    for (const so of (props.selectedSalesOrders || [])) {
        for (const line of so.lines) {
            lines.push({
                ...line,
                sales_order_number: so.order_number,
                partner_name: so.partner?.name,
            });
        }
    }
    return lines;
});

const formLinesBySoLineId = computed(() => {
    const map = {};
    for (const line of form.lines) {
        map[line.sales_order_line_id] = line;
    }
    return map;
});

function getQuantity(soLineId) {
    return formLinesBySoLineId.value[soLineId]?.quantity ?? 0;
}

function setQuantity(soLineId, value) {
    const existingIndex = form.lines.findIndex(l => l.sales_order_line_id === soLineId);
    if (existingIndex >= 0) {
        form.lines[existingIndex].quantity = parseFloat(value) || 0;
    } else {
        form.lines.push({
            sales_order_line_id: soLineId,
            quantity: parseFloat(value) || 0,
        });
    }
}

// Click on remaining quantity to deliver all for that row
function deliverRemainingForLine(line) {
    if (line.remaining_quantity > 0) {
        setQuantity(line.id, line.remaining_quantity);
    }
}

const totalQuantity = computed(() =>
    form.lines.reduce((sum, line) => sum + Number(line.quantity || 0), 0)
);

const hasSelectedQuantity = computed(() =>
    form.lines.some(line => Number(line.quantity) > 0)
);

function deliverAllRemaining() {
    for (const soLine of allSoLines.value) {
        if (soLine.remaining_quantity > 0) {
            setQuantity(soLine.id, soLine.remaining_quantity);
        }
    }
}

function clearAllQuantities() {
    for (const line of form.lines) {
        line.quantity = 0;
    }
}

const locationOptions = computed(() => {
    return (props.locations || []).map(loc => ({
        value: loc.id,
        label: loc.name,
        description: loc.code,
    }));
});

function filteredLinesPayload(lines) {
   return lines.filter(line => Number(line.quantity) > 0);
}

function submitForm() {
   if (!hasSelectedQuantity.value) {
      form.setError('lines', 'Minimal satu baris harus memiliki jumlah pengiriman.');
      return;
   }

   form.transform(data => ({
      ...data,
      lines: filteredLinesPayload(data.lines),
   }));

   form.put(route('sales-deliveries.update', props.delivery.id), {
      preserveScroll: true,
      onFinish: () => form.transform(data => data),
   });
}
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h3 class="font-semibold text-blue-800">{{ delivery.delivery_number }}</h3>
                    <p class="text-sm text-blue-600">Customer: {{ delivery.partner?.name }}</p>
                </div>

                <!-- Date & Location -->
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.delivery_date"
                        type="date"
                        label="Tanggal Pengiriman:"
                        :error="form.errors.delivery_date"
                        required
                    />

                    <AppSelect
                        v-model="form.location_id"
                        :options="locationOptions"
                        label="Lokasi Pengiriman:"
                        placeholder="Pilih Lokasi"
                        :error="form.errors.location_id"
                        required
                    />
                </div>

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                />
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Edit</h3>
                <p class="mb-2">Anda sedang mengedit pengiriman yang sudah ada. Perubahan akan memperbarui stok inventory.</p>
                <ul class="list-disc list-inside">
                    <li>Ubah jumlah barang yang dikirim</li>
                    <li>Ubah tanggal atau lokasi pengiriman</li>
                    <li>Stok akan disesuaikan secara otomatis</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <div class="flex justify-between flex-col lg:flex-row">
                <div>
                    <h2 class="text-lg font-semibold">Detail Pengiriman</h2>
                    <p class="text-sm text-gray-500 mb-4">Ubah jumlah barang yang akan dikirim.</p>
                </div>

                <div class="flex mb-2 py-4 min-w-max">
                    <AppUtilityButton @click="deliverAllRemaining" class="mr-2 h-8 text-xs">
                        <ArchiveBoxArrowDownIcon class="w-4 h-4 mr-1" /> Kirim Semua Sisa
                    </AppUtilityButton>
                    <AppSecondaryButton @click="clearAllQuantities" class="h-8 text-xs">
                        Reset
                    </AppSecondaryButton>
                </div>
            </div>

            <div v-if="allSoLines.length > 0">
                <table class="min-w-full bg-white border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 text-sm min-w-48 px-1.5 py-1.5">Produk</th>
                            <th class="border border-gray-300 text-sm px-1.5 py-1.5">SO #</th>
                            <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Sisa</th>
                            <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Qty Kirim</th>
                            <th class="border border-gray-300 text-sm px-1.5 py-1.5">UOM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in allSoLines" :key="line.id">
                            <td class="border border-gray-300 px-1.5 py-1.5">
                                <div class="font-medium text-gray-900">{{ line.variant?.product_name ?? '-' }}</div>
                                <div class="text-xs text-gray-500">{{ line.variant?.sku ?? line.description }}</div>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-sm text-gray-500">
                                {{ line.sales_order_number }}
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-sm text-right">
                                <span :class="line.remaining_quantity > 0 ? 'text-amber-600 font-medium' : 'text-gray-400'">
                                    {{ formatNumber(line.remaining_quantity, 2) }}
                                </span>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5">
                                <AppInput
                                    :modelValue="getQuantity(line.id)"
                                    @update:modelValue="setQuantity(line.id, $event)"
                                    :numberFormat="true"
                                    :max="line.remaining_quantity"
                                    :disabled="line.remaining_quantity <= 0"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                    :prefix="true"
                                >
                                    <template #prefix-slot>
                                        <button
                                            type="button"
                                            @click.stop="deliverRemainingForLine(line)"
                                            class="w-full h-full text-main-600 hover:text-main-800 rounded transition-colors"
                                            title="Kirim Semua Sisa"
                                        >
                                            <ArchiveBoxArrowDownIcon class="w-4 h-4" />
                                        </button>
                                    </template>
                                </AppInput>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-sm text-gray-500">
                                {{ line.uom?.code ?? '-' }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="text-sm">
                            <th colspan="3" class="border border-gray-300 px-4 py-2 text-right">Total Kirim</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">{{ formatNumber(totalQuantity, 2) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>

                <div v-if="form.errors.lines" class="text-red-500 text-sm mt-2">{{ form.errors.lines }}</div>
            </div>

            <div v-else class="text-center py-8 text-gray-500">
                <p>Tidak ada baris pengiriman yang tersedia.</p>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="form.processing || !hasSelectedQuantity">
                Simpan Perubahan
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('sales-deliveries.show', delivery.id))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
