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
import LotFormModal from './LotFormModal.vue';
import SerialFormModal from './SerialFormModal.vue';
import { ArchiveBoxArrowDownIcon, PlusCircleIcon } from '@heroicons/vue/24/outline';
import { formatNumber } from '@/utils/numberFormat';
import axios from 'axios';

const props = defineProps({
    goodsReceipt: Object,
    companies: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
    purchaseOrders: Array,
    selectedPurchaseOrders: Array,
    selectedCompanyId: Number,
    selectedBranchId: Number,
    selectedPartnerId: Number,
    suppliers: Array,
    locations: Array,
    filters: Object,
});

const isEditMode = computed(() => !!props.goodsReceipt);
const today = new Date().toISOString().split('T')[0];

// Selected company (separate ref for chained loading)
const selectedCompany = ref(
    props.selectedCompanyId || 
    props.goodsReceipt?.company_id ||
    (props.companies.length > 1 ? null : props.companies[0]?.value)
);

// Selected branch
const selectedBranch = ref(
    props.selectedBranchId || 
    props.goodsReceipt?.branch_id ||
    (props.branches.length === 1 ? props.branches[0]?.value : null)
);

// Selected supplier for filtering POs
const selectedSupplierId = ref(props.selectedPartnerId || null);
const selectedSupplierName = ref('');

// Set initial supplier name if editing
if (isEditMode.value && props.goodsReceipt?.supplier) {
    selectedSupplierName.value = props.goodsReceipt.supplier.name;
} else if (props.selectedPartnerId && props.suppliers) {
    const supplier = props.suppliers.find(s => s.value === props.selectedPartnerId);
    if (supplier) selectedSupplierName.value = supplier.label;
}

// Lot/Serial modals state
const showLotModal = ref(false);
const showSerialModal = ref(false);
const currentLineForModal = ref(null);

// Lot and serial options cache per product variant
const lotsCache = ref({});
const serialsCache = ref({});

// Build initial lines from selected POs or existing GRN
function buildInitialLines() {
    if (isEditMode.value && props.goodsReceipt?.lines) {
        return props.goodsReceipt.lines.map(line => ({
            purchase_order_line_id: line.purchase_order_line_id,
            quantity: line.quantity,
            lot_id: line.lot_id || null,
            serial_id: line.serial_id || null,
        }));
    }

    const lines = [];
    for (const po of (props.selectedPurchaseOrders || [])) {
        for (const line of po.lines) {
            if (line.remaining_quantity > 0) {
                lines.push({
                    purchase_order_line_id: line.id,
                    quantity: line.remaining_quantity,
                    lot_id: null,
                    serial_id: null,
                });
            }
        }
    }
    return lines;
}

const selectedPoIds = ref((props.selectedPurchaseOrders || []).map(po => po.id));

const form = useForm({
    company_id: selectedCompany.value,
    branch_id: selectedBranch.value,
    supplier_id: selectedSupplierId.value,
    purchase_order_ids: selectedPoIds.value,
    receipt_date: props.goodsReceipt?.receipt_date ?? today,
    location_id: props.goodsReceipt?.location_id ?? (props.locations?.[0]?.id ?? null),
    notes: props.goodsReceipt?.notes ?? '',
    lines: buildInitialLines(),
});

const allPoLines = computed(() => {
    const lines = [];
    for (const po of (props.selectedPurchaseOrders || [])) {
        for (const line of po.lines) {
            lines.push({
                ...line,
                purchase_order_number: po.order_number,
                partner_name: po.partner?.name,
            });
        }
    }
    return lines;
});

const formLinesByPoLineId = computed(() => {
    const map = {};
    for (const line of form.lines) {
        map[line.purchase_order_line_id] = line;
    }
    return map;
});

function getQuantity(poLineId) {
    return formLinesByPoLineId.value[poLineId]?.quantity ?? 0;
}

function setQuantity(poLineId, value) {
    const existingIndex = form.lines.findIndex(l => l.purchase_order_line_id === poLineId);
    if (existingIndex >= 0) {
        form.lines[existingIndex].quantity = parseFloat(value) || 0;
    } else {
        form.lines.push({
            purchase_order_line_id: poLineId,
            quantity: parseFloat(value) || 0,
            lot_id: null,
            serial_id: null,
        });
    }
}

function getLotId(poLineId) {
    return formLinesByPoLineId.value[poLineId]?.lot_id ?? null;
}

function setLotId(poLineId, value) {
    const existingIndex = form.lines.findIndex(l => l.purchase_order_line_id === poLineId);
    if (existingIndex >= 0) {
        form.lines[existingIndex].lot_id = value;
    }
}

function getSerialId(poLineId) {
    return formLinesByPoLineId.value[poLineId]?.serial_id ?? null;
}

function setSerialId(poLineId, value) {
    const existingIndex = form.lines.findIndex(l => l.purchase_order_line_id === poLineId);
    if (existingIndex >= 0) {
        form.lines[existingIndex].serial_id = value;
    }
}

// Fetch lots for a product variant
async function fetchLots(productVariantId) {
    if (!productVariantId) return [];
    if (lotsCache.value[productVariantId]) return lotsCache.value[productVariantId];
    
    try {
        const response = await axios.get(route('api.lots'), {
            params: {
                product_variant_id: productVariantId,
                receipt_date: form.receipt_date,
            }
        });
        const options = response.data.map(lot => ({
            value: lot.id,
            label: lot.lot_code,
            description: lot.expiry_date ? `Exp: ${new Date(lot.expiry_date).toLocaleDateString('ID-id')}` : null,
        }));
        lotsCache.value[productVariantId] = options;
        return options;
    } catch {
        return [];
    }
}

// Fetch serials for a product variant
async function fetchSerials(productVariantId) {
    if (!productVariantId) return [];
    if (serialsCache.value[productVariantId]) return serialsCache.value[productVariantId];
    
    try {
        const response = await axios.get(route('api.serials'), {
            params: { product_variant_id: productVariantId }
        });
        const options = response.data.map(serial => ({
            value: serial.id,
            label: serial.serial_no,
        }));
        serialsCache.value[productVariantId] = options;
        return options;
    } catch {
        return [];
    }
}

// Open lot modal for a line
function openLotModal(line) {
    currentLineForModal.value = line;
    showLotModal.value = true;
}

// Open serial modal for a line
function openSerialModal(line) {
    currentLineForModal.value = line;
    showSerialModal.value = true;
}

// Handle lot created from modal
function handleLotCreated(lot) {
    if (currentLineForModal.value) {
        const variantId = currentLineForModal.value.variant.id;
        // Invalidate cache and set the new lot
        delete lotsCache.value[variantId];
        // Re-fetch to update options
        fetchLots(variantId).then(options => {
            lotOptionsMap.value[currentLineForModal.value.variant.id] = options;
        });
        setLotId(currentLineForModal.value.id, lot.id);
    }
}

// Handle serial created from modal
function handleSerialCreated(serial) {
    if (currentLineForModal.value) {
        const variantId = currentLineForModal.value.variant.id;
        // Invalidate cache and set the new serial
        delete serialsCache.value[variantId];
        // Re-fetch to update options
        fetchSerials(variantId).then(options => {
            serialOptionsMap.value[currentLineForModal.value.variant.id] = options;
        });
        setSerialId(currentLineForModal.value.id, serial.id);
    }
}

// Click on remaining quantity to receive all for that row
function receiveRemainingForLine(line) {
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

function receiveAllRemaining() {
    for (const poLine of allPoLines.value) {
        if (poLine.remaining_quantity > 0) {
            setQuantity(poLine.id, poLine.remaining_quantity);
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
    if (!isEditMode.value) {
        // Reset downstream selections
        selectedBranch.value = null;
        selectedSupplierId.value = null;
        selectedSupplierName.value = '';
        selectedPoIds.value = [];
        form.lines = [];
        
        router.reload({
            only: ['branches', 'suppliers', 'purchaseOrders', 'selectedPurchaseOrders', 'locations'],
            data: { company_id: newCompanyId },
        });
    }
}, { immediate: true });

// Watch for branches prop changes - auto-select if only one
watch(
    () => props.branches,
    (newBranches) => {
        if (!isEditMode.value && newBranches.length === 1) {
            selectedBranch.value = newBranches[0].value;
        }
    },
    { immediate: true }
);

// Watch for branch selection changes
watch(selectedBranch, (newBranchId) => {
    form.branch_id = newBranchId;
    if (!isEditMode.value && newBranchId) {
        // Reset downstream selections
        selectedSupplierId.value = null;
        selectedSupplierName.value = '';
        selectedPoIds.value = [];
        form.lines = [];
        
        router.reload({
            only: ['suppliers', 'purchaseOrders', 'selectedPurchaseOrders', 'locations'],
            data: { 
                company_id: selectedCompany.value,
                branch_id: newBranchId,
            },
        });
    }
}, { immediate: true });

// Watch for supplier selection changes
watch(selectedSupplierId, (newId) => {
    form.supplier_id = newId;
    if (!isEditMode.value) {
        // Clear selected POs when supplier changes
        selectedPoIds.value = [];
        form.lines = [];
        router.get(route('goods-receipts.create'), {
            company_id: selectedCompany.value,
            branch_id: selectedBranch.value,
            partner_id: newId,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['purchaseOrders', 'selectedPurchaseOrders', 'locations', 'selectedPartnerId'],
        });
    }
}, { immediate: true });

// Watch for PO selection changes
watch(selectedPoIds, (newIds) => {
    form.purchase_order_ids = newIds;
    if (!isEditMode.value && newIds.length > 0) {
        router.get(route('goods-receipts.create'), {
            company_id: selectedCompany.value,
            branch_id: selectedBranch.value,
            partner_id: selectedSupplierId.value,
            purchase_order_ids: newIds,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['selectedPurchaseOrders', 'locations'],
        });
    }
}, { deep: true, immediate: true });

// Watch for selectedPurchaseOrders prop changes to populate lines
watch(
    () => props.selectedPurchaseOrders,
    (newPOs) => {
        if (!isEditMode.value && newPOs && newPOs.length > 0) {
            repopulateLinesFromPOs();
        }
    },
    { immediate: true }
);

function repopulateLinesFromPOs() {
    if (isEditMode.value) return;
    
    if (!props.selectedPurchaseOrders || props.selectedPurchaseOrders.length === 0) {
        form.lines = [];
        return;
    }

    const newLines = [];
    for (const po of props.selectedPurchaseOrders) {
        for (const line of (po.lines || [])) {
            if (line.remaining_quantity > 0) {
                newLines.push({
                    purchase_order_line_id: line.id,
                    quantity: line.remaining_quantity,
                    lot_id: null,
                    serial_id: null,
                });
            }
        }
    }
    form.lines = newLines;
}

// Auto-select on mount
onMounted(() => {
    // Auto-select company if only one
    if (!isEditMode.value && props.companies.length === 1) {
        selectedCompany.value = props.companies[0].value;
    }
    // Auto-select branch if only one
    if (!isEditMode.value && props.branches.length === 1) {
        selectedBranch.value = props.branches[0].value;
    }
});

const purchaseOrderOptions = computed(() => {
    return (props.purchaseOrders || []).map(po => ({
        value: po.id,
        label: `${po.order_number}`,
        description: `Sisa: ${po.remaining_quantity}`,
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
      form.setError('lines', 'Minimal satu baris harus memiliki jumlah penerimaan.');
      return;
   }

   form.transform(data => ({
      ...data,
      lines: filteredLinesPayload(data.lines),
   }));

   if (isEditMode.value) {
      form.put(route('goods-receipts.update', props.goodsReceipt.id), {
         preserveScroll: true,
         onFinish: () => form.transform(data => data),
      });
   } else {
      form.post(route('goods-receipts.store'), {
         preserveScroll: true,
         onFinish: () => form.transform(data => data),
      });
   }
}

// Supplier table headers for AppPopoverSearch
const supplierTableHeaders = [
   { key: 'code', label: 'Kode' },
   { key: 'name', label: 'Nama' },
   { key: 'actions', label: '' },
];

// Lot options per line (reactive)
const lotOptionsMap = ref({});
const serialOptionsMap = ref({});

// Load lot/serial options when PO lines change
watch(allPoLines, async (lines) => {
    for (const line of lines) {
        if (line.variant.id) {
            fetchLots(line.variant.id).then(options => {
                lotOptionsMap.value[line.variant.id] = options;
            });
            fetchSerials(line.variant.id).then(options => {
                serialOptionsMap.value[line.variant.id] = options;
            });
        }
    }
}, { immediate: true });

const lotOptions = computed(() => {
    return lotOptionsMap.value;
});

const serialOptions = computed(() => {
    return serialOptionsMap.value;
});
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
                        :disabled="isEditMode"
                        required
                    />
                    
                    <AppSelect
                        v-model="selectedBranch"
                        :options="branches"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="isEditMode || !selectedCompany"
                        required
                    />
                </div>

                <!-- Supplier -->
                <div class="grid grid-cols-1">
                    <AppPopoverSearch
                        v-model="selectedSupplierId"
                        :url="route('api.suppliers-with-pos')"
                        :displayKeys="['name']"
                        :tableHeaders="supplierTableHeaders"
                        :initialDisplayValue="selectedSupplierName"
                        label="Supplier:"
                        placeholder="Pilih Supplier"
                        modalTitle="Pilih Supplier"
                        :disabled="isEditMode || !selectedBranch"
                        :error="form.errors.supplier_id"
                        required
                    />
                </div>

                <!-- Purchase Orders -->
                <div class="grid grid-cols-1">
                    <AppSelect
                        v-model="selectedPoIds"
                        :options="purchaseOrderOptions"
                        label="Purchase Order:"
                        placeholder="Pilih Purchase Order"
                        :error="form.errors.purchase_order_ids"
                        :multiple="true"
                        :disabled="!selectedSupplierId || isEditMode"
                        required
                    />
                </div>

                <!-- Date & Location -->
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.receipt_date"
                        type="date"
                        label="Tanggal Penerimaan:"
                        :error="form.errors.receipt_date"
                        required
                    />

                    <AppSelect
                        v-model="form.location_id"
                        :options="locationOptions"
                        label="Lokasi Penyimpanan:"
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
                <h3 class="text-lg font-semibold mb-2">Informasi Penerimaan</h3>
                <p class="mb-2">Penerimaan pembelian adalah dokumen yang mencatat barang yang diterima dari supplier berdasarkan Purchase Order.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan dan cabang terlebih dahulu</li>
                    <li>Pilih supplier yang akan menerima barang</li>
                    <li>Pilih satu atau lebih Purchase Order</li>
                    <li>Pilih Lot atau Serial jika diperlukan</li>
                    <li>Metode penilaian akan otomatis mengikuti pengaturan perusahaan</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <div class="flex justify-between flex-col lg:flex-row">
                <div>
                    <h2 class="text-lg font-semibold">Detail Penerimaan</h2>
                    <p class="text-sm text-gray-500 mb-4">Masukkan jumlah barang yang diterima. Pilih Lot/Serial jika diperlukan.</p>
                </div>

                <div class="flex mb-2 py-4 min-w-max">
                    <AppUtilityButton @click="receiveAllRemaining" class="mr-2 h-8 text-xs">
                        <ArchiveBoxArrowDownIcon class="w-4 h-4 mr-1" /> Terima Semua Sisa
                    </AppUtilityButton>
                    <AppSecondaryButton @click="clearAllQuantities" class="h-8 text-xs">
                        Reset
                    </AppSecondaryButton>
                </div>
            </div>

            <div v-if="allPoLines.length > 0">
                <table class="min-w-full bg-white border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 text-sm min-w-48 px-1.5 py-1.5">Produk</th>
                            <th class="border border-gray-300 text-sm px-1.5 py-1.5">PO #</th>
                            <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Sisa</th>
                            <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Qty Terima</th>
                            <th class="border border-gray-300 text-sm min-w-40 px-1.5 py-1.5">Lot</th>
                            <th class="border border-gray-300 text-sm min-w-40 px-1.5 py-1.5">Serial</th>
                            <th class="border border-gray-300 text-sm px-1.5 py-1.5">UOM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in allPoLines" :key="line.id">
                            <td class="border border-gray-300 px-1.5 py-1.5">
                                <div class="font-medium text-gray-900">{{ line.variant?.product_name ?? '-' }}</div>
                                <div class="text-xs text-gray-500">{{ line.variant?.sku ?? line.description }}</div>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-sm text-gray-500">
                                {{ line.purchase_order_number }}
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
                                            @click.stop="receiveRemainingForLine(line)"
                                            class="w-full h-full text-main-600 hover:text-main-800 rounded transition-colors"
                                            title="Terima Semua Sisa"
                                        >
                                            <ArchiveBoxArrowDownIcon class="w-4 h-4" />
                                        </button>
                                    </template>
                                </AppInput>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5">
                                <AppSelect
                                    :modelValue="getLotId(line.id)"
                                    @update:modelValue="setLotId(line.id, $event)"
                                    :options="lotOptions[line.variant.id]"
                                    placeholder="Pilih Lot"
                                    :disabled="line.remaining_quantity <= 0"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                    :addNewButton="{
                                       label: 'Tambah Lot Baru',
                                       action: () => openLotModal(line)
                                    }"
                                />
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5">
                                <AppSelect
                                    :modelValue="getSerialId(line.id)"
                                    @update:modelValue="setSerialId(line.id, $event)"
                                    :options="serialOptions[line.variant.id]"
                                    placeholder="Pilih Serial"
                                    :disabled="line.remaining_quantity <= 0"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                    :addNewButton="{
                                       label: 'Tambah Serial Baru',
                                       action: () => openSerialModal(line)
                                    }"
                                />
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-sm text-gray-500">
                                {{ line.uom?.code ?? '-' }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="text-sm">
                            <th colspan="3" class="border border-gray-300 px-4 py-2 text-right">Total Diterima</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">{{ formatNumber(totalQuantity, 2) }}</th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
                </table>

                <div v-if="form.errors.lines" class="text-red-500 text-sm mt-2">{{ form.errors.lines }}</div>
            </div>

            <div v-else class="text-center py-8 text-gray-500">
                <p v-if="!selectedSupplierId">Pilih Supplier terlebih dahulu untuk melihat Purchase Order yang tersedia.</p>
                <p v-else-if="purchaseOrderOptions.length === 0">Tidak ada Purchase Order yang tersedia untuk supplier ini.</p>
                <p v-else>Pilih Purchase Order untuk melihat detail barang yang dapat diterima.</p>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="form.processing || !hasSelectedQuantity">
                {{ isEditMode ? 'Ubah' : 'Tambah' }} Penerimaan
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('goods-receipts.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>

    <!-- Lot Modal -->
    <LotFormModal
        :show="showLotModal"
        :productVariantId="currentLineForModal?.variant?.id"
        :productName="currentLineForModal?.variant?.product_name"
        @close="showLotModal = false"
        @created="handleLotCreated"
    />

    <!-- Serial Modal -->
    <SerialFormModal
        :show="showSerialModal"
        :productVariantId="currentLineForModal?.variant?.id"
        :productName="currentLineForModal?.variant?.product_name"
        @close="showSerialModal = false"
        @created="handleSerialCreated"
    />
</template>
