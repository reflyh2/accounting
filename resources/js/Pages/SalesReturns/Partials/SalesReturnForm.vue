<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, watch, ref, onMounted } from 'vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import { ArrowUturnLeftIcon } from '@heroicons/vue/24/outline';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    companies: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
    customers: {
        type: Array,
        default: () => [],
    },
    salesDeliveries: {
        type: Array,
        default: () => [],
    },
    selectedCompanyId: Number,
    selectedBranchId: Number,
    selectedCustomerId: Number,
    selectedSalesDelivery: {
        type: Object,
        default: null,
    },
    reasonOptions: {
        type: Array,
        default: () => [],
    },
});

const today = new Date().toISOString().split('T')[0];

// Selected company (separate ref for chained loading)
const selectedCompany = ref(
    props.selectedCompanyId ||
    (props.companies.length === 1 ? props.companies[0]?.value : null)
);

// Selected branch
const selectedBranch = ref(
    props.selectedBranchId ||
    (props.branches.length === 1 ? props.branches[0]?.value : null)
);

// Selected customer for popover search
const selectedCustomer = ref(props.selectedCustomerId || null);
const selectedCustomerName = ref('');

// Set initial customer name from customers prop
if (props.selectedCustomerId && props.customers) {
    const customer = props.customers.find(c => c.value === props.selectedCustomerId);
    if (customer) selectedCustomerName.value = customer.label;
}

// Customer table headers for popover search
const customerTableHeaders = [
    { key: 'name', label: 'Nama Customer' },
    { key: 'code', label: 'Kode' },
    { key: 'actions', label: '' }
];

// Selected Delivery
const selectedDeliveryId = ref(props.selectedSalesDelivery?.id ?? null);

const form = useForm({
    company_id: selectedCompany.value,
    branch_id: selectedBranch.value,
    customer_id: selectedCustomer.value,
    sales_delivery_id: selectedDeliveryId.value,
    return_date: today,
    reason_code: props.reasonOptions?.[0]?.value ?? null,
    notes: '',
    lines: props.selectedSalesDelivery
        ? props.selectedSalesDelivery.lines.map(line => ({
            sales_delivery_line_id: line.id,
            description: line.description,
            variant: line.variant,
            uom: line.uom,
            available_quantity: Number(line.available_quantity || 0),
            quantity: 0,
            unit_price: Number(line.unit_price || 0),
        }))
        : [],
});

const salesDeliveryOptions = computed(() =>
    props.salesDeliveries.map(delivery => ({
        value: delivery.id,
        label: `${delivery.delivery_number} (${formatNumber(delivery.available_quantity || 0, 2)})`,
    }))
);

const hasSelectedDelivery = computed(() => Boolean(props.selectedSalesDelivery));
const hasAvailableLines = computed(() =>
    form.lines.some(line => Number(line.available_quantity || 0) > 0)
);
const hasSelectedQuantity = computed(() =>
    form.lines.some(line => Number(line.quantity || 0) > 0)
);

const totalQuantity = computed(() =>
    form.lines.reduce((sum, line) => sum + Number(line.quantity || 0), 0)
);

const totalValue = computed(() =>
    form.lines.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_price || 0), 0)
);

// Watch for company selection changes
watch(selectedCompany, (newCompanyId) => {
    form.company_id = newCompanyId;
    // Reset downstream selections
    selectedBranch.value = null;
    selectedCustomer.value = null;
    selectedCustomerName.value = '';
    selectedDeliveryId.value = null;
    form.lines = [];
    
    router.get(route('sales-returns.create'), {
        company_id: newCompanyId,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['branches', 'customers', 'salesDeliveries', 'selectedSalesDelivery'],
    });
}, { immediate: false });

// Watch for branches prop changes - auto-select if only one
watch(
    () => props.branches,
    (newBranches) => {
        if (newBranches.length === 1 && !selectedBranch.value) {
            selectedBranch.value = newBranches[0].value;
        }
    },
    { immediate: true }
);

// Watch for branch selection changes
watch(selectedBranch, (newBranchId) => {
    form.branch_id = newBranchId;
    if (newBranchId) {
        // Reset downstream selections
        selectedCustomer.value = null;
        selectedCustomerName.value = '';
        selectedDeliveryId.value = null;
        form.lines = [];
        
        router.get(route('sales-returns.create'), {
            company_id: selectedCompany.value,
            branch_id: newBranchId,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['customers', 'salesDeliveries', 'selectedSalesDelivery'],
        });
    }
}, { immediate: false });

// Watch for customer selection changes (from AppPopoverSearch)
watch(selectedCustomer, (newCustomerId) => {
    form.customer_id = newCustomerId;
    if (newCustomerId) {
        // Reset delivery selection
        selectedDeliveryId.value = null;
        form.lines = [];
        
        router.get(route('sales-returns.create'), {
            company_id: selectedCompany.value,
            branch_id: selectedBranch.value,
            customer_id: newCustomerId,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['salesDeliveries', 'selectedSalesDelivery'],
        });
    }
}, { immediate: false });

// Watch for Delivery selection changes
watch(selectedDeliveryId, (newDeliveryId) => {
    form.sales_delivery_id = newDeliveryId;
    if (newDeliveryId) {
        router.get(route('sales-returns.create'), {
            company_id: selectedCompany.value,
            branch_id: selectedBranch.value,
            customer_id: selectedCustomer.value,
            sales_delivery_id: newDeliveryId,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['selectedSalesDelivery'],
        });
    }
}, { immediate: false });

// Watch for selectedSalesDelivery prop changes to populate lines
watch(
    () => props.selectedSalesDelivery,
    (newDelivery) => {
        if (newDelivery && newDelivery.lines) {
            form.lines = newDelivery.lines.map(line => ({
                sales_delivery_line_id: line.id,
                description: line.description,
                variant: line.variant,
                uom: line.uom,
                available_quantity: Number(line.available_quantity || 0),
                quantity: 0,
                unit_price: Number(line.unit_price || 0),
            }));
        }
    },
    { immediate: true }
);

// Auto-select on mount
onMounted(() => {
    // Auto-select company if only one
    if (props.companies.length === 1 && !selectedCompany.value) {
        selectedCompany.value = props.companies[0].value;
    }
    // Auto-select branch if only one
    if (props.branches.length === 1 && !selectedBranch.value) {
        selectedBranch.value = props.branches[0].value;
    }
});

function getQuantity(lineId) {
    const line = form.lines.find(l => l.sales_delivery_line_id === lineId);
    return line ? line.quantity : 0;
}

function setQuantity(lineId, value) {
    const line = form.lines.find(l => l.sales_delivery_line_id === lineId);
    if (line) {
        const max = Number(line.available_quantity || 0);
        let qty = Number(value) || 0;
        if (qty > max) qty = max;
        if (qty < 0) qty = 0;
        line.quantity = qty;
    }
}

function returnRemainingForLine(line) {
    const targetLine = form.lines.find(l => l.sales_delivery_line_id === line.sales_delivery_line_id);
    if (targetLine) {
        targetLine.quantity = Number(targetLine.available_quantity || 0);
    }
}

function returnAll() {
    form.lines = form.lines.map(line => ({
        ...line,
        quantity: Number(line.available_quantity || 0),
    }));
}

function clearAll() {
    form.lines = form.lines.map(line => ({
        ...line,
        quantity: 0,
    }));
}

function filteredLinesPayload(lines) {
    return lines
        .filter(line => Number(line.quantity || 0) > 0)
        .map(line => ({
            sales_delivery_line_id: line.sales_delivery_line_id,
            quantity: Number(line.quantity),
        }));
}

function submit() {
    if (!hasSelectedDelivery.value) {
        form.setError('sales_delivery_id', 'Pilih Pengiriman Penjualan terlebih dahulu.');
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

    form.post(route('sales-returns.store'), {
        preserveScroll: true,
        onFinish: () => form.transform(data => data),
    });
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <!-- Company & Branch -->
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCompany"
                        :options="companies"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        :error="form.errors.company_id"
                        required
                    />
                    
                    <AppSelect
                        v-model="selectedBranch"
                        :options="branches"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!selectedCompany"
                        required
                    />
                </div>

                <!-- Customer Selection with PopoverSearch -->
                <div class="grid grid-cols-1">
                    <AppPopoverSearch
                        v-model="selectedCustomer"
                        :url="route('api.customers-with-deliveries')"
                        :displayKeys="['name']"
                        :tableHeaders="customerTableHeaders"
                        :initialDisplayValue="selectedCustomerName"
                        label="Customer:"
                        placeholder="Pilih Customer"
                        modalTitle="Pilih Customer"
                        :disabled="!selectedBranch"
                        :error="form.errors.customer_id"
                        required
                    />
                </div>

                <!-- Delivery Selection -->
                <div class="grid grid-cols-1">
                    <AppSelect
                        v-model="selectedDeliveryId"
                        :options="salesDeliveryOptions"
                        label="Pengiriman Penjualan:"
                        placeholder="Pilih Delivery"
                        :error="form.errors.sales_delivery_id"
                        :disabled="!selectedCustomer"
                        required
                    />
                </div>

                <!-- Date & Reason -->
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.return_date"
                        type="date"
                        label="Tanggal Retur:"
                        :error="form.errors.return_date"
                        required
                    />

                    <AppSelect
                        v-model="form.reason_code"
                        :options="reasonOptions"
                        label="Alasan:"
                        placeholder="Pilih alasan"
                        :error="form.errors.reason_code"
                    />
                </div>

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                />
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Retur</h3>
                <p class="mb-2">Retur penjualan adalah dokumen yang mencatat pengembalian barang dari customer berdasarkan pengiriman penjualan.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan dan cabang terlebih dahulu</li>
                    <li>Pilih customer yang memiliki pengiriman aktif</li>
                    <li>Pilih Pengiriman Penjualan yang akan diretur</li>
                    <li>Isi jumlah retur untuk setiap baris</li>
                    <li>Jumlah tidak boleh melebihi sisa yang tersedia</li>
                </ul>
            </div>
        </div>

        <!-- Selected Delivery Info & Lines -->
        <div v-if="!hasSelectedDelivery" class="border border-dashed border-gray-300 rounded p-6 text-center text-gray-600 text-sm">
            Pilih Pengiriman Penjualan yang akan diretur.
        </div>

        <div v-else class="overflow-x-auto">
            <!-- Delivery Header Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="flex flex-col gap-1">
                    <p class="font-semibold text-lg text-blue-900">{{ selectedSalesDelivery.delivery_number }}</p>
                    <p class="text-sm text-blue-700">
                        SO: {{ selectedSalesDelivery.sales_order?.order_number || '—' }}
                    </p>
                    <p class="text-sm text-blue-700">
                        Customer: {{ selectedSalesDelivery.sales_order?.partner || '—' }}
                    </p>
                    <p class="text-sm text-blue-700">
                        Lokasi: {{ selectedSalesDelivery.location?.name || '—' }}
                    </p>
                </div>
            </div>

            <div v-if="!hasAvailableLines" class="mb-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                Seluruh barang pada pengiriman ini sudah tidak tersedia untuk retur.
            </div>

            <p v-if="form.errors.lines" class="text-sm text-red-600 mb-2">{{ form.errors.lines }}</p>

            <!-- Table Header with Actions -->
            <div class="flex justify-between flex-col lg:flex-row">
                <div>
                    <h2 class="text-lg font-semibold">Detail Retur</h2>
                    <p class="text-sm text-gray-500 mb-4">Masukkan jumlah barang yang akan diretur.</p>
                </div>

                <div class="flex mb-2 py-4 min-w-max">
                    <AppUtilityButton @click="returnAll" class="mr-2 h-8 text-xs" :disabled="!hasAvailableLines">
                        <ArrowUturnLeftIcon class="w-4 h-4 mr-1" /> Retur Semua Sisa
                    </AppUtilityButton>
                    <AppSecondaryButton @click="clearAll" class="h-8 text-xs" :disabled="!hasSelectedQuantity">
                        Reset
                    </AppSecondaryButton>
                </div>
            </div>

            <!-- Lines Table -->
            <div v-if="form.lines.length > 0">
                <table class="min-w-full bg-white border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 text-sm min-w-48 px-1.5 py-1.5">Produk</th>
                            <th class="border border-gray-300 text-sm px-1.5 py-1.5">UOM</th>
                            <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Sisa</th>
                            <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Qty Retur</th>
                            <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Harga</th>
                            <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in form.lines" :key="line.sales_delivery_line_id">
                            <td class="border border-gray-300 px-1.5 py-1.5">
                                <div class="font-medium text-gray-900">{{ line.variant?.product_name ?? '-' }}</div>
                                <div class="text-xs text-gray-500">{{ line.variant?.sku ?? line.description }}</div>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-sm text-gray-500">
                                {{ line.uom?.code ?? '-' }}
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-sm text-right">
                                <span :class="line.available_quantity > 0 ? 'text-amber-600 font-medium' : 'text-gray-400'">
                                    {{ formatNumber(line.available_quantity, 2) }}
                                </span>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5">
                                <AppInput
                                    :modelValue="getQuantity(line.sales_delivery_line_id)"
                                    @update:modelValue="setQuantity(line.sales_delivery_line_id, $event)"
                                    :numberFormat="true"
                                    :max="line.available_quantity"
                                    :disabled="line.available_quantity <= 0"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                    :prefix="true"
                                >
                                    <template #prefix-slot>
                                        <button
                                            type="button"
                                            @click.stop="returnRemainingForLine(line)"
                                            class="w-full h-full text-main-600 hover:text-main-800 rounded transition-colors"
                                            title="Retur Semua Sisa"
                                        >
                                            <ArrowUturnLeftIcon class="w-4 h-4" />
                                        </button>
                                    </template>
                                </AppInput>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-sm text-right">
                                {{ formatNumber(line.unit_price, 2) }}
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-sm text-right font-medium">
                                {{ formatNumber(Number(line.quantity || 0) * Number(line.unit_price || 0), 2) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="text-sm">
                            <th colspan="3" class="border border-gray-300 px-4 py-2 text-right">Total Retur</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">{{ formatNumber(totalQuantity, 2) }}</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">Total Nilai</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">{{ formatNumber(totalValue, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center gap-3 pt-4 border-t border-gray-200">
            <AppPrimaryButton
                type="submit"
                :disabled="form.processing || !hasSelectedDelivery || !hasSelectedQuantity || !hasAvailableLines"
            >
                Posting Retur Penjualan
            </AppPrimaryButton>
            <AppSecondaryButton :href="route('sales-returns.index', filters)" as="a">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
