<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { computed, watch, ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import { TrashIcon, PlusCircleIcon } from '@heroicons/vue/24/outline';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    invoice: Object,
    purchaseOrders: {
        type: Array,
        default: () => [],
    },
    selectedPurchaseOrders: {
        type: Array,
        default: () => [],
    },
    selectedPartnerId: Number,
    suppliers: {
        type: Array,
        default: () => [],
    },
    paymentMethods: {
        type: Array,
        default: () => [],
    },
    partnerBankAccounts: {
        type: Array,
        default: () => [],
    },
    primaryCurrency: Object,
    products: {
        type: Array,
        default: () => [],
    },
    uoms: {
        type: Array,
        default: () => [],
    },
    companies: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
    currencies: {
        type: Array,
        default: () => [],
    },
    filters: Object,
});

const isEditMode = computed(() => !!props.invoice);
const isDirectInvoice = computed(() => !form.purchase_order_ids || form.purchase_order_ids.length === 0);
const today = new Date().toISOString().split('T')[0];

// Selected company (separate from form for chained loading)
const selectedCompany = ref(
    props.invoice?.company_id || 
    (props.companies.length > 1 ? null : props.companies[0]?.value)
);

// Selected supplier for popover search
const selectedSupplierId = ref(props.selectedPartnerId || props.invoice?.partner_id || null);
const selectedSupplierName = ref('');

// Set initial supplier name
if (isEditMode.value && props.invoice?.partner) {
    selectedSupplierName.value = props.invoice.partner.name;
} else if (props.selectedPartnerId && props.suppliers) {
    const supplier = props.suppliers.find(s => s.value === props.selectedPartnerId);
    if (supplier) selectedSupplierName.value = supplier.label;
}

const selectedPoIds = ref((props.selectedPurchaseOrders || []).map(po => po.id));

// Build initial lines from selected POs or existing invoice
function buildInitialLines() {
    if (isEditMode.value && props.invoice?.lines) {
        return props.invoice.lines.map(line => ({
            id: line.id,
            purchase_order_line_id: line.purchase_order_line_id,
            goods_receipt_line_id: line.goods_receipt_line_id,
            po_number: line.purchase_order_line?.purchase_order?.order_number,
            goods_receipt_number: line.goods_receipt_line?.goods_receipt?.receipt_number,
            description: line.description,
            uom_label: line.uom?.code,
            product_id: line.product_variant?.product_id,
            product_variant_id: line.product_variant_id,
            uom_id: line.uom_id,
            quantity: Number(line.quantity),
            unit_price: Number(line.unit_price),
            tax_rate: Number(line.tax_rate || 0),
            max_quantity: null,
        }));
    }

    const lines = [];
    for (const po of (props.selectedPurchaseOrders || [])) {
        for (const line of (po.lines || [])) {
            lines.push({
                purchase_order_line_id: line.purchase_order_line_id,
                goods_receipt_line_id: line.goods_receipt_line_id,
                goods_receipt_number: line.goods_receipt_number,
                po_number: po.order_number,
                description: line.description,
                product_variant_id: line.product_variant_id,
                uom_id: line.uom_id,
                uom_label: line.uom_label,
                quantity: Number(line.quantity),
                unit_price: Number(line.unit_price),
                tax_rate: Number(line.tax_rate || 0),
                max_quantity: Number(line.available_quantity),
            });
        }
    }
    return lines;
}

const form = useForm({
    company_id: selectedCompany.value,
    branch_id: props.invoice?.branch_id || (props.branches.length === 1 ? props.branches[0]?.value : null),
    currency_id: props.invoice?.currency_id || props.primaryCurrency?.id || null,
    partner_id: selectedSupplierId.value,
    purchase_order_ids: selectedPoIds.value,
    invoice_date: props.invoice?.invoice_date ?? today,
    due_date: props.invoice?.due_date ?? null,
    vendor_invoice_number: props.invoice?.vendor_invoice_number ?? '',
    exchange_rate: props.invoice?.exchange_rate ?? 1,
    notes: props.invoice?.notes ?? '',
    payment_method: props.invoice?.payment_method ?? null,
    partner_bank_account_id: props.invoice?.partner_bank_account_id ?? null,
    lines: buildInitialLines(),
});

// Watch for company changes - reload branches, suppliers, POs
watch(selectedCompany, (newCompanyId) => {
    form.company_id = newCompanyId;
    if (!isEditMode.value) {
        // Reset downstream selections
        form.branch_id = null;
        selectedSupplierId.value = null;
        selectedSupplierName.value = '';
        selectedPoIds.value = [];
        form.lines = [];
        
        router.reload({
            only: ['branches', 'suppliers', 'purchaseOrders', 'selectedPurchaseOrders'],
            data: { company_id: newCompanyId },
        });
    }
}, { immediate: true });

// Watch for branches prop changes - auto-select if only one
watch(
    () => props.branches,
    (newBranches) => {
        if (!isEditMode.value && newBranches.length === 1) {
            form.branch_id = newBranches[0].value;
        }
    },
    { immediate: true }
);

// Watch for branch changes - reload suppliers and POs
watch(() => form.branch_id, (newBranchId) => {
    if (!isEditMode.value && newBranchId) {
        selectedSupplierId.value = null;
        selectedPoIds.value = [];
        form.lines = [];
        
        router.reload({
            only: ['suppliers', 'purchaseOrders', 'selectedPurchaseOrders'],
            data: { 
                company_id: selectedCompany.value,
                branch_id: newBranchId,
                currency_id: form.currency_id,
            },
        });
    }
}, {immediate: true});

// Watch for currency changes - reload POs
watch(() => form.currency_id, (newCurrencyId) => {
    if (!isEditMode.value && newCurrencyId) {
        selectedPoIds.value = [];
        form.lines = [];
        
        router.reload({
            only: ['purchaseOrders', 'selectedPurchaseOrders'],
            data: { 
                company_id: selectedCompany.value,
                branch_id: form.branch_id,
                currency_id: newCurrencyId,
                partner_id: selectedSupplierId.value,
            },
        });
    }
}, { immediate: true });

// Watch for supplier selection changes
watch(selectedSupplierId, (newId) => {
    form.partner_id = newId;
    if (!isEditMode.value) {
        selectedPoIds.value = [];
        form.partner_bank_account_id = null;
        router.reload({
            only: ['purchaseOrders', 'selectedPurchaseOrders', 'partnerBankAccounts'],
            data: { 
                company_id: selectedCompany.value,
                branch_id: form.branch_id,
                currency_id: form.currency_id,
                partner_id: newId,
            },
        });
    }
}, { immediate: true });

// Watch for PO selection changes
watch(selectedPoIds, (newIds) => {
    form.purchase_order_ids = newIds;
    if (!isEditMode.value && newIds.length > 0) {
        router.get(route('purchase-invoices.create'), {
            company_id: selectedCompany.value,
            branch_id: form.branch_id,
            currency_id: form.currency_id,
            partner_id: selectedSupplierId.value,
            purchase_order_ids: newIds,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['selectedPurchaseOrders', 'partnerBankAccounts'],
        });
    } else if (!isEditMode.value && newIds.length === 0) {
        form.lines = [];
        addDirectLine();
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
        if (!form.lines.length) addDirectLine();
        return;
    }

    const newLines = [];
    for (const po of props.selectedPurchaseOrders) {
        for (const line of (po.lines || [])) {
            newLines.push({
                purchase_order_line_id: line.purchase_order_line_id,
                goods_receipt_line_id: line.goods_receipt_line_id,
                goods_receipt_number: line.goods_receipt_number,
                po_number: po.order_number,
                description: line.description,
                product_variant_id: line.product_variant_id,
                uom_id: line.uom_id,
                uom_label: line.uom_label,
                quantity: Number(line.quantity),
                unit_price: Number(line.unit_price),
                tax_rate: Number(line.tax_rate || 0),
                max_quantity: Number(line.available_quantity),
            });
        }
    }
    form.lines = newLines;
}

onMounted(() => {
    // Auto-select company if only one
    if (!isEditMode.value && props.companies.length === 1) {
        selectedCompany.value = props.companies[0].value;
    }
    // Auto-select branch if only one
    if (!isEditMode.value && props.branches.length === 1) {
        form.branch_id = props.branches[0].value;
    }
    // Initialize lines
    if (!isEditMode.value && props.selectedPurchaseOrders?.length > 0 && form.lines.length === 0) {
        repopulateLinesFromPOs();
    } else if (!isEditMode.value && form.purchase_order_ids.length === 0 && form.lines.length === 0) {
        addDirectLine();
    }
});

// Direct Invoice Line Logic
function addDirectLine() {
    form.lines.push({
        product_id: null,
        product_variant_id: null,
        uom_id: null,
        description: '',
        quantity: 1,
        unit_price: 0,
        tax_rate: 0,
    });
}

function removeLine(index) {
    if (form.lines.length === 1 && isDirectInvoice.value) {
        return;
    }
    form.lines.splice(index, 1);
}

// Line calculation functions (matching PurchaseOrderForm)
function lineSubtotal(line) {
    const quantity = Number(line.quantity) || 0;
    const price = Number(line.unit_price) || 0;
    return quantity * price;
}

function lineTax(line) {
    const subtotal = lineSubtotal(line);
    const taxRate = Number(line.tax_rate) || 0;
    return subtotal * (taxRate / 100);
}

// Computeds
const totals = computed(() => {
    return form.lines.reduce(
        (carry, line) => {
            const quantity = Number(line.quantity) || 0;
            const price = Number(line.unit_price) || 0;
            const taxRate = Number(line.tax_rate) || 0;

            const lineSubtotal = quantity * price;
            const lineTax = lineSubtotal * (taxRate / 100);

            carry.subtotal += lineSubtotal;
            carry.tax += lineTax;

            return carry;
        },
        { subtotal: 0, tax: 0 }
    );
});

const grandTotal = computed(() => totals.value.subtotal + totals.value.tax);

const baseTotal = computed(() => grandTotal.value * Number(form.exchange_rate || 1));

const hasLines = computed(() => form.lines.length > 0);

// Helper Options
const companyOptions = computed(() => 
    props.companies.map(c => ({ value: c.value, label: c.label }))
);

const branchOptions = computed(() => 
    props.branches.map(b => ({ value: b.value, label: b.label }))
);

const currencyOptions = computed(() => 
    props.currencies.map(c => ({ value: c.value, label: c.label }))
);

const purchaseOrderOptions = computed(() => 
    (props.purchaseOrders || []).map(po => ({
        value: po.id,
        label: po.order_number,
        description: `Tanggal: ${po.order_date}, Total: ${formatNumber(po.total_amount)}`,
    }))
);

const bankAccountOptions = computed(() => 
    (props.partnerBankAccounts || []).map(ba => ({
        value: ba.id,
        label: `${ba.bank_name} - ${ba.account_number} (${ba.account_holder_name})`,
    }))
);

const supplierTableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'actions', label: '' },
];

// Product/Variant Logic for Direct Invoices
function getVariantsForProduct(productId) {
    if (!productId) return [];
    const product = props.products.find(p => p.id === productId);
    return product?.variants?.map(v => ({
        value: v.id,
        label: v.sku,
        description: v.barcode,
    })) || [];
}

// Store convertible UOMs per line index for direct invoices
const lineConvertibleUoms = reactive({});

/**
 * Fetch convertible UOMs for a direct invoice line based on variant's UOM.
 */
async function fetchConvertibleUoms(lineIndex, baseUomId, productId) {
    if (!baseUomId) {
        delete lineConvertibleUoms[lineIndex];
        return;
    }
    
    try {
        const response = await axios.get(route('api.convertible-uoms'), {
            params: {
                base_uom_id: baseUomId,
                product_id: productId,
                company_id: selectedCompany.value,
            }
        });
        lineConvertibleUoms[lineIndex] = response.data.map(u => ({
            value: u.id,
            label: u.code,
            description: u.name,
        }));
    } catch (error) {
        console.error('Error fetching convertible UOMs:', error);
        delete lineConvertibleUoms[lineIndex];
    }
}

function lineUomOptions(line, lineIndex) {
    // For direct invoices, use fetched convertible UOMs if available
    if (lineConvertibleUoms[lineIndex] && lineConvertibleUoms[lineIndex].length > 0) {
        return lineConvertibleUoms[lineIndex];
    }
    
    // Fallback: filter by UOM kind from variant
    if (!line.product_variant_id) return props.uoms.map(u => ({ value: u.id, label: u.code, description: u.name }));
    
    const product = props.products.find(p => p.id === line.product_id);
    const variant = product?.variants?.find(v => v.id === line.product_variant_id);
    
    if (variant?.uom?.kind) {
        return props.uoms.filter(u => u.kind === variant.uom.kind).map(u => ({ value: u.id, label: u.code, description: u.name }));
    }
    return props.uoms.map(u => ({ value: u.id, label: u.code, description: u.name }));
}

function handleProductChange(line, lineIndex) {
    line.product_variant_id = null;
    line.uom_id = null;
    // Clear cached convertible UOMs for this line
    delete lineConvertibleUoms[lineIndex];
    const product = props.products.find(p => p.id === line.product_id);
    if (product) line.description = product.name;
    
    if (product?.variants?.length === 1) {
        line.product_variant_id = product.variants[0].id;
        syncVariant(line, lineIndex);
    }
}

function syncVariant(line, lineIndex) {
    if (!line.product_variant_id) return;
    const product = props.products.find(p => p.id === line.product_id);
    const variant = product?.variants?.find(v => v.id === line.product_variant_id);
    if (variant) {
        line.uom_id = variant.uom?.id;
        // Fetch convertible UOMs for this variant's UOM
        if (variant.uom?.id && lineIndex !== undefined) {
            fetchConvertibleUoms(lineIndex, variant.uom.id, product.id);
        }
    }
}

function submitForm(createAnother = false) {
    if (!hasLines.value) {
        form.setError('lines', 'Minimal satu baris harus diisi.');
        return;
    }

    form.transform(data => ({
        ...data,
        create_another: createAnother,
    }));

    if (isEditMode.value) {
        form.put(route('purchase-invoices.update', props.invoice.id), {
            preserveScroll: true,
            onFinish: () => form.transform(data => data),
        });
    } else {
        form.post(route('purchase-invoices.store'), {
            preserveScroll: true,
            onFinish: () => form.transform(data => data),
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <!-- Company & Branch -->
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCompany"
                        :options="companyOptions"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        :error="form.errors.company_id"
                        :disabled="isEditMode"
                        required
                    />
                    
                    <AppSelect
                        v-model="form.branch_id"
                        :options="branchOptions"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="isEditMode || !selectedCompany"
                        required
                    />
                </div>

                <!-- Currency & Supplier -->
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.currency_id"
                        :options="currencyOptions"
                        label="Mata Uang:"
                        placeholder="Pilih Mata Uang"
                        :error="form.errors.currency_id"
                        :disabled="isEditMode"
                        required
                    />

                    <AppPopoverSearch
                        v-model="selectedSupplierId"
                        :url="route('api.suppliers-with-pos')"
                        :displayKeys="['name']"
                        :tableHeaders="supplierTableHeaders"
                        :initialDisplayValue="selectedSupplierName"
                        label="Supplier:"
                        placeholder="Pilih Supplier"
                        modalTitle="Pilih Supplier"
                        :disabled="isEditMode || !form.branch_id"
                        :error="form.errors.partner_id"
                        required
                    />
                </div>

                <!-- Purchase Orders -->
                <div class="grid grid-cols-1">
                    <AppSelect
                        v-model="selectedPoIds"
                        :options="purchaseOrderOptions"
                        label="Purchase Order:"
                        placeholder="Pilih Purchase Order (Opsional - kosongkan untuk Direct Invoice)"
                        :error="form.errors.purchase_order_ids"
                        :multiple="true"
                        :disabled="!selectedSupplierId || isEditMode"
                    />
                </div>

                <!-- Dates -->
                <div class="grid grid-cols-2 gap-4">
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
                        label="Jatuh Tempo:"
                        :error="form.errors.due_date"
                    />
                </div>

                <!-- Vendor Invoice Number & Exchange Rate -->
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.vendor_invoice_number"
                        label="No. Dokumen Vendor:"
                        :error="form.errors.vendor_invoice_number"
                        placeholder="Nomor invoice dari supplier"
                    />

                    <AppInput
                        v-model="form.exchange_rate"
                        label="Kurs:"
                        :error="form.errors.exchange_rate"
                        :numberFormat="true"
                        required
                    />
                </div>

                <!-- Payment Details -->
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.payment_method"
                        :options="paymentMethods"
                        label="Metode Pembayaran:"
                        placeholder="Pilih Metode"
                        :error="form.errors.payment_method"
                    />

                    <AppSelect
                        v-if="form.payment_method == 'transfer'"
                        v-model="form.partner_bank_account_id"
                        :options="bankAccountOptions"
                        label="Rekening Supplier:"
                        placeholder="Pilih Rekening"
                        :error="form.errors.partner_bank_account_id"
                        :disabled="!form.partner_id"
                    />
                </div>

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                />
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Faktur</h3>
                <p class="mb-2">Faktur pembelian adalah dokumen yang mencatat tagihan dari supplier berdasarkan barang yang diterima.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan dan cabang terlebih dahulu</li>
                    <li>Pilih mata uang transaksi</li>
                    <li>Pilih supplier</li>
                    <li>Pilih satu atau lebih Purchase Order, atau kosongkan untuk Direct Invoice</li>
                    <li>Isi metode pembayaran jika diperlukan</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <h2 class="text-lg font-semibold">Detail Item Invoice</h2>
            <p class="text-sm text-gray-500 mb-4">Lengkapi detail item termasuk kuantitas, satuan, harga, dan pajak.</p>

            <div v-if="form.lines.length > 0">
                <table class="min-w-full bg-white border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th v-if="!isDirectInvoice" class="border border-gray-300 text-sm px-1.5 py-1.5">Referensi</th>
                            <th class="border border-gray-300 text-sm min-w-48 lg:min-w-48 px-1.5 py-1.5">Produk</th>
                            <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Satuan</th>
                            <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Qty</th>
                            <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Harga Satuan</th>
                            <th class="border border-gray-300 text-sm min-w-16 px-1.5 py-1.5">Pajak (%)</th>
                            <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Subtotal</th>
                            <th class="border border-gray-300 px-1.5 py-1.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in form.lines" :key="index">
                            <!-- Referensi (PO/GRN) -->
                            <td v-if="!isDirectInvoice" class="border border-gray-300 px-1.5 py-1.5 text-xs text-gray-500 align-top">
                                <div v-if="line.goods_receipt_number">GRN: {{ line.goods_receipt_number }}</div>
                                <div v-if="line.po_number">PO: {{ line.po_number }}</div>
                            </td>
                            
                            <!-- Produk -->
                            <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                                <div v-if="!isDirectInvoice">
                                    <div class="font-medium text-gray-900">{{ line.description }}</div>
                                    <div class="text-xs text-gray-500" v-if="line.max_quantity">Available: {{ formatNumber(line.max_quantity) }}</div>
                                </div>
                                <div v-else>
                                    <AppSelect 
                                        v-model="line.product_id" 
                                        :options="products.map(p => ({value: p.id, label: p.name}))"
                                        placeholder="Pilih produk"
                                        :error="form.errors?.[`lines.${index}.product_id`]"
                                        required
                                        @update:modelValue="handleProductChange(line, index)"
                                        :margins="{ top: 0, right: 0, bottom: 2, left: 0 }"
                                    />
                                    <AppSelect 
                                        v-model="line.product_variant_id"
                                        :options="getVariantsForProduct(line.product_id)"
                                        :placeholder="!line.product_id ? 'Pilih produk terlebih dahulu' : getVariantsForProduct(line.product_id).length === 0 ? 'Produk tidak memiliki varian' : 'Pilih varian'"
                                        :error="form.errors?.[`lines.${index}.product_variant_id`]"
                                        :disabled="!line.product_id || getVariantsForProduct(line.product_id).length === 0"
                                        :required="line.product_id && getVariantsForProduct(line.product_id).length > 0"
                                        @update:modelValue="syncVariant(line, index)"
                                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                    />
                                </div>
                            </td>
                            
                            <!-- Satuan (UOM) -->
                            <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                                <div v-if="!isDirectInvoice" class="text-sm text-gray-600">
                                    {{ line.uom_label }}
                                </div>
                                <AppSelect 
                                    v-else
                                    v-model="line.uom_id"
                                    :options="lineUomOptions(line, index)"
                                    placeholder="Satuan"
                                    :error="form.errors?.[`lines.${index}.uom_id`]"
                                    required
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                            </td>
                            
                            <!-- Qty -->
                            <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                                <AppInput 
                                    v-model="line.quantity" 
                                    :numberFormat="true"
                                    required
                                    :error="form.errors?.[`lines.${index}.quantity`]"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                            </td>
                            
                            <!-- Harga Satuan -->
                            <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                                <AppInput 
                                    v-model="line.unit_price" 
                                    :numberFormat="true"
                                    required
                                    :error="form.errors?.[`lines.${index}.unit_price`]"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                            </td>
                            
                            <!-- Pajak (%) -->
                            <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                                <AppInput 
                                    v-model="line.tax_rate" 
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0"
                                    :error="form.errors?.[`lines.${index}.tax_rate`]"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                            </td>
                            
                            <!-- Subtotal -->
                            <td class="border border-gray-300 px-1.5 py-1.5 text-sm text-right whitespace-nowrap align-top">
                                <div class="flex justify-between">
                                    <div>Subtotal:</div>
                                    <div>{{ formatNumber(lineSubtotal(line)) }}</div>
                                </div>
                                <div class="flex justify-between">
                                    <div>Pajak:</div>
                                    <div>{{ formatNumber(lineTax(line)) }}</div>
                                </div>
                            </td>
                            
                            <!-- Action -->
                            <td class="border border-gray-300 px-1.5 py-1.5 text-center align-top">
                                <button type="button" @click="removeLine(index)" :disabled="form.lines.length === 1 && isDirectInvoice" class="text-red-500 hover:text-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="text-sm">
                            <th :colspan="isDirectInvoice ? 5 : 6" class="border border-gray-300 px-4 py-2 text-right">Total</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(totals.subtotal) }}</th>
                            <th class="border border-gray-300 px-4 py-2"></th>
                        </tr>
                        <tr class="text-sm">
                            <th :colspan="isDirectInvoice ? 5 : 6" class="border border-gray-300 px-4 py-2 text-right">Total Pajak</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(totals.tax) }}</th>
                            <th class="border border-gray-300 px-4 py-2"></th>
                        </tr>
                        <tr class="text-sm font-semibold">
                            <th :colspan="isDirectInvoice ? 5 : 6" class="border border-gray-300 px-4 py-2 text-right">Grand Total</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(grandTotal) }}</th>
                            <th class="border border-gray-300 px-4 py-2"></th>
                        </tr>
                    </tfoot>
                </table>

                <div class="flex mt-2 mb-4">
                    <button v-if="isDirectInvoice" type="button" @click="addDirectLine" class="flex items-center text-main-500 hover:text-main-700">
                        <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Baris
                    </button>
                </div>

                <div v-if="form.errors.lines" class="text-red-500 text-sm mt-2">{{ form.errors.lines }}</div>
            </div>

            <div v-else class="text-center py-8 text-gray-500">
                <p v-if="!selectedCompany">Pilih Perusahaan terlebih dahulu.</p>
                <p v-else-if="!form.branch_id">Pilih Cabang terlebih dahulu.</p>
                <p v-else-if="!selectedSupplierId">Pilih Supplier terlebih dahulu.</p>
                <p v-else-if="purchaseOrderOptions.length === 0">Tidak ada Purchase Order yang tersedia. Anda dapat membuat Direct Invoice.</p>
                <p v-else>Pilih Purchase Order, atau klik "Tambah Baris" untuk Direct Invoice.</p>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="form.processing || !hasLines">
                {{ isEditMode ? 'Ubah' : 'Tambah' }} Faktur
            </AppPrimaryButton>
            <AppUtilityButton v-if="!isEditMode" type="button" @click="submitForm(true)" class="mr-2" :disabled="form.processing || !hasLines">
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('purchase-invoices.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
