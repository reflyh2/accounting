<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, watch, ref, onMounted } from 'vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import { ArchiveBoxArrowDownIcon } from '@heroicons/vue/24/outline';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    companies: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
    salesOrders: Array,
    selectedSalesOrders: Array,
    selectedCompanyId: Number,
    selectedBranchId: Number,
    selectedPartnerId: Number,
    customers: Array,
    locations: Array,
    filters: Object,
});

const today = new Date().toISOString().split('T')[0];

// Selected company (separate ref for chained loading)
const selectedCompany = ref(
    props.selectedCompanyId || 
    (props.companies.length > 1 ? null : props.companies[0]?.value)
);

// Selected branch
const selectedBranch = ref(
    props.selectedBranchId || 
    (props.branches.length === 1 ? props.branches[0]?.value : null)
);

// Selected customer for filtering SOs
const selectedCustomerId = ref(props.selectedPartnerId || null);
const selectedCustomerName = ref('');

// Set initial customer name
if (props.selectedPartnerId && props.customers) {
    const customer = props.customers.find(c => c.value === props.selectedPartnerId);
    if (customer) selectedCustomerName.value = customer.label;
}

// Build initial lines from selected SOs
function buildInitialLines() {
    const lines = [];
    for (const so of (props.selectedSalesOrders || [])) {
        for (const line of so.lines) {
            if (line.remaining_quantity > 0) {
                lines.push({
                    sales_order_line_id: line.id,
                    quantity: line.remaining_quantity,
                });
            }
        }
    }
    return lines;
}

const selectedSoIds = ref((props.selectedSalesOrders || []).map(so => so.id));

const form = useForm({
    company_id: selectedCompany.value,
    branch_id: selectedBranch.value,
    partner_id: selectedCustomerId.value,
    sales_order_ids: selectedSoIds.value,
    delivery_date: today,
    location_id: props.locations?.[0]?.id ?? null,
    notes: '',
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

// Watch for company selection changes
watch(selectedCompany, (newCompanyId) => {
    form.company_id = newCompanyId;
    // Reset downstream selections
    selectedBranch.value = null;
    selectedCustomerId.value = null;
    selectedCustomerName.value = '';
    selectedSoIds.value = [];
    form.lines = [];
    
    router.reload({
        only: ['branches', 'customers', 'salesOrders', 'selectedSalesOrders', 'locations'],
        data: { company_id: newCompanyId },
    });
}, { immediate: false });

// Watch for branches prop changes - auto-select if only one
watch(
    () => props.branches,
    (newBranches) => {
        if (newBranches.length === 1) {
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
        selectedCustomerId.value = null;
        selectedCustomerName.value = '';
        selectedSoIds.value = [];
        form.lines = [];
        
        router.reload({
            only: ['customers', 'salesOrders', 'selectedSalesOrders', 'locations'],
            data: { 
                company_id: selectedCompany.value,
                branch_id: newBranchId,
            },
        });
    }
}, { immediate: false });

// Watch for customer selection changes
watch(selectedCustomerId, (newId) => {
    form.partner_id = newId;
    // Clear selected SOs when customer changes
    selectedSoIds.value = [];
    form.lines = [];
    router.get(route('sales-deliveries.create'), {
        company_id: selectedCompany.value,
        branch_id: selectedBranch.value,
        partner_id: newId,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['salesOrders', 'selectedSalesOrders', 'locations', 'selectedPartnerId'],
    });
}, { immediate: false });

// Watch for SO selection changes
watch(selectedSoIds, (newIds) => {
    form.sales_order_ids = newIds;
    if (newIds.length > 0) {
        router.get(route('sales-deliveries.create'), {
            company_id: selectedCompany.value,
            branch_id: selectedBranch.value,
            partner_id: selectedCustomerId.value,
            sales_order_ids: newIds,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['selectedSalesOrders', 'locations'],
        });
    }
}, { deep: true, immediate: false });

// Watch for selectedSalesOrders prop changes to populate lines
watch(
    () => props.selectedSalesOrders,
    (newSOs) => {
        if (newSOs && newSOs.length > 0) {
            repopulateLinesFromSOs();
        }
    },
    { immediate: true }
);

function repopulateLinesFromSOs() {
    if (!props.selectedSalesOrders || props.selectedSalesOrders.length === 0) {
        form.lines = [];
        return;
    }

    const newLines = [];
    for (const so of props.selectedSalesOrders) {
        for (const line of (so.lines || [])) {
            if (line.remaining_quantity > 0) {
                newLines.push({
                    sales_order_line_id: line.id,
                    quantity: line.remaining_quantity,
                });
            }
        }
    }
    form.lines = newLines;
}

// Auto-select on mount
onMounted(() => {
    // Auto-select company if only one
    if (props.companies.length === 1) {
        selectedCompany.value = props.companies[0].value;
    }
    // Auto-select branch if only one
    if (props.branches.length === 1) {
        selectedBranch.value = props.branches[0].value;
    }
});

const salesOrderOptions = computed(() => {
    return (props.salesOrders || []).map(so => ({
        value: so.id,
        label: `${so.order_number}`,
        description: `Sisa: ${so.remaining_quantity}`,
    }));
});

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

   form.post(route('sales-deliveries.store'), {
      preserveScroll: true,
      onFinish: () => form.transform(data => data),
   });
}

// Customer table headers for AppPopoverSearch
const customerTableHeaders = [
   { key: 'code', label: 'Kode' },
   { key: 'name', label: 'Nama' },
   { key: 'actions', label: '' },
];
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-4">
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

                <!-- Customer -->
                <div class="grid grid-cols-1">
                    <AppPopoverSearch
                        v-model="selectedCustomerId"
                        :url="route('api.customers-with-sos')"
                        :displayKeys="['name']"
                        :tableHeaders="customerTableHeaders"
                        :initialDisplayValue="selectedCustomerName"
                        label="Customer:"
                        placeholder="Pilih Customer"
                        modalTitle="Pilih Customer"
                        :disabled="!selectedBranch"
                        :error="form.errors.partner_id"
                        required
                    />
                </div>

                <!-- Sales Orders -->
                <div class="grid grid-cols-1">
                    <AppSelect
                        v-model="selectedSoIds"
                        :options="salesOrderOptions"
                        label="Sales Order:"
                        placeholder="Pilih Sales Order"
                        :error="form.errors.sales_order_ids"
                        :multiple="true"
                        :disabled="!selectedCustomerId"
                        required
                    />
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
                <h3 class="text-lg font-semibold mb-2">Informasi Pengiriman</h3>
                <p class="mb-2">Pengiriman penjualan mencatat barang yang dikirim ke customer berdasarkan Sales Order.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan dan cabang terlebih dahulu</li>
                    <li>Pilih customer untuk melihat SO yang tersedia</li>
                    <li>Pilih satu atau lebih Sales Order</li>
                    <li>Atur jumlah barang yang akan dikirim</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <div class="flex justify-between flex-col lg:flex-row">
                <div>
                    <h2 class="text-lg font-semibold">Detail Pengiriman</h2>
                    <p class="text-sm text-gray-500 mb-4">Masukkan jumlah barang yang akan dikirim.</p>
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
                <p v-if="!selectedCustomerId">Pilih Customer terlebih dahulu untuk melihat Sales Order yang tersedia.</p>
                <p v-else-if="salesOrderOptions.length === 0">Tidak ada Sales Order yang tersedia untuk customer ini.</p>
                <p v-else>Pilih Sales Order untuk melihat detail barang yang dapat dikirim.</p>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="form.processing || !hasSelectedQuantity">
                Posting Pengiriman
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('sales-deliveries.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
