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
import { TrashIcon, PlusCircleIcon } from '@heroicons/vue/24/outline';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    invoice: Object,
    salesOrders: {
        type: Array,
        default: () => [],
    },
    selectedSalesOrders: {
        type: Array,
        default: () => [],
    },
    selectedPartnerId: Number,
    customers: {
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
    paymentMethods: {
        type: Array,
        default: () => [],
    },
    companyBankAccounts: {
        type: Array,
        default: () => [],
    },
    filters: Object,
});

const isEditMode = computed(() => !!props.invoice);
const isDirectInvoice = computed(() => !form.sales_order_ids || form.sales_order_ids.length === 0);
const today = new Date().toISOString().split('T')[0];

const paymentMethodOptions = computed(() => [
    { value: null, label: 'Pilih Metode Pembayaran' },
    ...props.paymentMethods
]);

const filteredBankAccounts = computed(() => {
    if (!form.company_id) return [];
    return props.companyBankAccounts.filter(ba => ba.company_id === form.company_id);
});

// Selected company (separate from form for chained loading)
const selectedCompany = ref(
    props.invoice?.company_id || 
    (props.companies.length > 1 ? null : props.companies[0]?.value)
);

// Selected customer for popover search
const selectedCustomerId = ref(props.selectedPartnerId || props.invoice?.partner_id || null);
const selectedCustomerName = ref('');

// Set initial customer name
if (isEditMode.value && props.invoice?.partner) {
    selectedCustomerName.value = props.invoice.partner.name;
} else if (props.selectedPartnerId && props.customers) {
    const customer = props.customers.find(c => c.value === props.selectedPartnerId);
    if (customer) selectedCustomerName.value = customer.label;
}

const selectedSoIds = ref((props.selectedSalesOrders || []).map(so => so.id));

// Build initial lines from selected SOs or existing invoice
function buildInitialLines() {
    if (isEditMode.value && props.invoice?.lines) {
        return props.invoice.lines.map(line => ({
            id: line.id,
            sales_order_line_id: line.sales_order_line_id,
            sales_delivery_line_id: line.sales_delivery_line_id,
            so_number: line.so_order_number,
            delivery_number: line.delivery_number,
            description: line.description,
            uom_label: line.uom_label,
            product_id: line.product_id,
            product_variant_id: line.product_variant_id,
            quantity: Number(line.quantity),
            unit_price: Number(line.unit_price),
            discount_rate: Number(line.discount_rate || 0),
            discount_amount: Number(line.discount_amount || 0),
            tax_rate: Number(line.tax_rate || 0),
            tax_amount: Number(line.tax_amount || 0),
            max_quantity: null,
        }));
    }

    const lines = [];
    for (const so of (props.selectedSalesOrders || [])) {
        for (const line of (so.lines || [])) {
            lines.push({
                sales_order_line_id: line.sales_order_line_id,
                sales_delivery_line_id: line.sales_delivery_line_id,
                delivery_number: line.delivery_number,
                so_number: so.order_number,
                description: line.description,
                uom_label: line.uom_label,
                quantity: Number(line.quantity),
                unit_price: Number(line.unit_price),
                discount_rate: Number(line.discount_rate || 0),
                discount_amount: Number(line.discount_amount || 0),
                tax_rate: Number(line.tax_rate || 0),
                tax_amount: Number(line.tax_amount || 0),
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
    partner_id: selectedCustomerId.value,
    sales_order_ids: selectedSoIds.value,
    is_direct_invoice: false,
    invoice_date: props.invoice?.invoice_date ?? today,
    due_date: props.invoice?.due_date ?? null,
    customer_invoice_number: props.invoice?.customer_invoice_number ?? '',
    exchange_rate: props.invoice?.exchange_rate ?? 1,
    notes: props.invoice?.notes ?? '',
    payment_method: props.invoice?.payment_method ?? null,
    company_bank_account_id: props.invoice?.company_bank_account_id ?? null,
    lines: buildInitialLines(),
});

// Watch for company changes - reload branches, customers, SOs
watch(selectedCompany, (newCompanyId) => {
    form.company_id = newCompanyId;
    if (!isEditMode.value) {
        // Reset downstream selections
        form.branch_id = null;
        selectedCustomerId.value = null;
        selectedCustomerName.value = '';
        selectedSoIds.value = [];
        form.lines = [];
        
        router.reload({
            only: ['branches', 'customers', 'salesOrders', 'selectedSalesOrders'],
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

// Watch for branch changes - reload customers and SOs
watch(() => form.branch_id, (newBranchId) => {
    if (!isEditMode.value && newBranchId) {
        selectedCustomerId.value = null;
        selectedSoIds.value = [];
        form.lines = [];
        
        router.reload({
            only: ['customers', 'salesOrders', 'selectedSalesOrders'],
            data: { 
                company_id: selectedCompany.value,
                branch_id: newBranchId,
                currency_id: form.currency_id,
            },
        });
    }
}, {immediate: true});

// Watch for currency changes - reload SOs
watch(() => form.currency_id, (newCurrencyId) => {
    if (!isEditMode.value && newCurrencyId) {
        selectedSoIds.value = [];
        form.lines = [];
        
        router.reload({
            only: ['salesOrders', 'selectedSalesOrders'],
            data: { 
                company_id: selectedCompany.value,
                branch_id: form.branch_id,
                currency_id: newCurrencyId,
                partner_id: selectedCustomerId.value,
            },
        });
    }
}, { immediate: true });

// Watch for customer selection changes
watch(selectedCustomerId, (newId) => {
    form.partner_id = newId;
    if (!isEditMode.value) {
        selectedSoIds.value = [];
        router.reload({
            only: ['salesOrders', 'selectedSalesOrders'],
            data: { 
                company_id: selectedCompany.value,
                branch_id: form.branch_id,
                currency_id: form.currency_id,
                partner_id: newId,
            },
        });
    }
}, { immediate: true });

// Watch for SO selection changes
watch(selectedSoIds, (newIds) => {
    form.sales_order_ids = newIds;
    form.is_direct_invoice = newIds.length === 0;
    
    if (!isEditMode.value && newIds.length > 0) {
        router.get(route('sales-invoices.create'), {
            company_id: selectedCompany.value,
            branch_id: form.branch_id,
            currency_id: form.currency_id,
            partner_id: selectedCustomerId.value,
            sales_order_ids: newIds,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['selectedSalesOrders'],
        });
    } else if (!isEditMode.value && newIds.length === 0) {
        form.lines = [];
        addDirectLine();
    }
}, { deep: true, immediate: true });

// Watch for selectedSalesOrders prop changes to populate lines
watch(
    () => props.selectedSalesOrders,
    (newSOs) => {
        if (!isEditMode.value && newSOs && newSOs.length > 0) {
            repopulateLinesFromSOs();
        }
    },
    { immediate: true }
);

function repopulateLinesFromSOs() {
    if (isEditMode.value) return;
    
    if (!props.selectedSalesOrders || props.selectedSalesOrders.length === 0) {
        if (!form.lines.length) addDirectLine();
        return;
    }

    const newLines = [];
    for (const so of props.selectedSalesOrders) {
        for (const line of (so.lines || [])) {
            newLines.push({
                sales_order_line_id: line.sales_order_line_id,
                sales_delivery_line_id: line.sales_delivery_line_id,
                delivery_number: line.delivery_number,
                so_number: so.order_number,
                description: line.description,
                uom_label: line.uom_label,
                quantity: Number(line.quantity),
                unit_price: Number(line.unit_price),
                discount_rate: Number(line.discount_rate || 0),
                discount_amount: Number(line.discount_amount || 0),
                tax_rate: Number(line.tax_rate || 0),
                tax_amount: Number(line.tax_amount || 0),
                max_quantity: Number(line.available_quantity),
            });
        }
    }
    form.lines = newLines;

    // Set payment defaults from first SO
    if (props.selectedSalesOrders && props.selectedSalesOrders.length > 0) {
        const firstSO = props.selectedSalesOrders[0];
        if (firstSO.payment_method && !form.payment_method) {
            form.payment_method = firstSO.payment_method;
        }
        if (firstSO.company_bank_account_id && !form.company_bank_account_id) {
            form.company_bank_account_id = firstSO.company_bank_account_id;
        }
    }
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
    if (!isEditMode.value && props.selectedSalesOrders?.length > 0 && form.lines.length === 0) {
        repopulateLinesFromSOs();
    } else if (!isEditMode.value && form.sales_order_ids.length === 0 && form.lines.length === 0) {
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
        uom_label: '',
        quantity: 1,
        unit_price: 0,
        discount_rate: 0,
        discount_amount: 0,
        tax_rate: 0,
        tax_amount: 0,
    });
}

function removeLine(index) {
    if (form.lines.length === 1 && isDirectInvoice.value) {
        return;
    }
    form.lines.splice(index, 1);
}

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

function lineUomOptions(line) {
    return props.uoms;
}

function handleProductChange(line) {
    line.product_variant_id = null;
    line.uom_id = null;
    const product = props.products.find(p => p.id === line.product_id);
    if (product) line.description = product.name;
    
    if (product?.variants?.length === 1) {
        line.product_variant_id = product.variants[0].id;
        syncVariant(line);
    }
}

function syncVariant(line) {
    if (!line.product_variant_id) return;
    const product = props.products.find(p => p.id === line.product_id);
    const variant = product?.variants?.find(v => v.id === line.product_variant_id);
    if (variant) {
        line.uom_id = variant.uom?.id;
    }
}

// Line calculation functions
function lineSubtotal(line) {
    const quantity = Number(line.quantity) || 0;
    const price = Number(line.unit_price) || 0;
    const discountRate = Number(line.discount_rate) || 0;
    const gross = quantity * price;
    const discount = gross * (discountRate / 100);
    return gross - discount;
}

function lineDiscount(line) {
    const quantity = Number(line.quantity) || 0;
    const price = Number(line.unit_price) || 0;
    const discountRate = Number(line.discount_rate) || 0;
    return quantity * price * (discountRate / 100);
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
            const discountRate = Number(line.discount_rate) || 0;
            const taxRate = Number(line.tax_rate) || 0;

            const lineGross = quantity * price;
            const lineDiscount = lineGross * (discountRate / 100);
            const lineSubtotal = lineGross - lineDiscount;
            const lineTax = lineSubtotal * (taxRate / 100);

            carry.subtotal += lineSubtotal;
            carry.tax += lineTax;
            carry.discount += lineDiscount;

            return carry;
        },
        { subtotal: 0, tax: 0, discount: 0 }
    );
});

const grandTotal = computed(() => totals.value.subtotal + totals.value.tax);

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

const salesOrderOptions = computed(() => 
    (props.salesOrders || []).map(so => ({
        value: so.id,
        label: so.order_number,
        description: `Tanggal: ${so.order_date}, Total: ${formatNumber(so.total_amount)}`,
    }))
);

const customerTableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'actions', label: '' },
];

function submitForm(createAnother = false) {
    if (!hasLines.value) {
        form.setError('lines', 'Minimal satu baris harus diisi.');
        return;
    }

    form.transform(data => ({
        ...data,
        is_direct_invoice: isDirectInvoice.value,
        create_another: createAnother,
    }));

    if (isEditMode.value) {
        form.put(route('sales-invoices.update', props.invoice.id), {
            preserveScroll: true,
            onFinish: () => form.transform(data => data),
        });
    } else {
        form.post(route('sales-invoices.store'), {
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

                <!-- Currency & Customer -->
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
                        v-model="selectedCustomerId"
                        :url="route('api.customers-with-sos')"
                        :displayKeys="['name']"
                        :tableHeaders="customerTableHeaders"
                        :initialDisplayValue="selectedCustomerName"
                        label="Customer:"
                        placeholder="Pilih Customer"
                        modalTitle="Pilih Customer"
                        :disabled="isEditMode || !form.branch_id"
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
                        placeholder="Pilih Sales Order (Opsional - kosongkan untuk Direct Invoice)"
                        :error="form.errors.sales_order_ids"
                        :multiple="true"
                        :disabled="!selectedCustomerId || isEditMode"
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

                <!-- Customer Invoice Number & Exchange Rate -->
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.customer_invoice_number"
                        label="No. Faktur Customer:"
                        :error="form.errors.customer_invoice_number"
                        placeholder="Nomor referensi dari customer"
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
                        :options="paymentMethodOptions"
                        label="Metode Pembayaran:"
                        placeholder="Pilih Metode"
                        :error="form.errors.payment_method"
                    />

                    <AppSelect
                        v-if="form.payment_method === 'transfer'"
                        v-model="form.company_bank_account_id"
                        :options="filteredBankAccounts"
                        label="Rekening Bank Perusahaan:"
                        placeholder="Pilih Rekening"
                        :error="form.errors.company_bank_account_id"
                    />
                </div>

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                />
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Faktur Penjualan</h3>
                <p class="mb-2">Faktur penjualan adalah dokumen yang dikeluarkan kepada customer untuk menagih pembayaran.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan dan cabang terlebih dahulu</li>
                    <li>Pilih mata uang transaksi</li>
                    <li>Pilih customer</li>
                    <li>Pilih satu atau lebih Sales Order, atau kosongkan untuk Direct Invoice</li>
                    <li>Detail faktur akan otomatis terisi dari delivery yang tersedia</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <h2 class="text-lg font-semibold">Detail Item Faktur</h2>
            <p class="text-sm text-gray-500 mb-4">Lengkapi detail item termasuk kuantitas, harga, dan pajak.</p>

            <div v-if="form.lines.length > 0">
                <table class="min-w-full bg-white border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th v-if="!isDirectInvoice" class="border border-gray-300 text-sm px-1.5 py-1.5">Referensi</th>
                            <th class="border border-gray-300 text-sm min-w-48 lg:min-w-48 px-1.5 py-1.5">Deskripsi</th>
                            <th class="border border-gray-300 text-sm min-w-16 px-1.5 py-1.5">Satuan</th>
                            <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Qty</th>
                            <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Harga Satuan</th>
                            <th class="border border-gray-300 text-sm min-w-16 px-1.5 py-1.5">Diskon (%)</th>
                            <th class="border border-gray-300 text-sm min-w-16 px-1.5 py-1.5">Pajak (%)</th>
                            <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Subtotal</th>
                            <th class="border border-gray-300 px-1.5 py-1.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in form.lines" :key="index">
                            <!-- Referensi (SO/Delivery) -->
                            <td v-if="!isDirectInvoice" class="border border-gray-300 px-1.5 py-1.5 text-xs text-gray-500 align-top">
                                <div v-if="line.delivery_number">DN: {{ line.delivery_number }}</div>
                                <div v-if="line.so_number">SO: {{ line.so_number }}</div>
                            </td>
                            
                            <!-- Deskripsi -->
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
                                        @update:modelValue="handleProductChange(line)"
                                        :margins="{ top: 0, right: 0, bottom: 2, left: 0 }"
                                    />
                                    <AppSelect 
                                        v-model="line.product_variant_id"
                                        :options="getVariantsForProduct(line.product_id)"
                                        :placeholder="!line.product_id ? 'Pilih produk terlebih dahulu' : getVariantsForProduct(line.product_id).length === 0 ? 'Produk tidak memiliki varian' : 'Pilih varian'"
                                        :error="form.errors?.[`lines.${index}.product_variant_id`]"
                                        :disabled="!line.product_id || getVariantsForProduct(line.product_id).length === 0"
                                        :required="line.product_id && getVariantsForProduct(line.product_id).length > 0"
                                        @update:modelValue="syncVariant(line)"
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
                                    :options="lineUomOptions(line).map(u => ({value: u.id, label: u.code, description: u.name}))"
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
                            
                            <!-- Diskon (%) -->
                            <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                                <AppInput 
                                    v-model="line.discount_rate" 
                                    :numberFormat="true"
                                    :error="form.errors?.[`lines.${index}.discount_rate`]"
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                            </td>
                            
                            <!-- Pajak (%) -->
                            <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                                <AppInput 
                                    v-model="line.tax_rate" 
                                    :numberFormat="true"
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
                            <th :colspan="isDirectInvoice ? 6 : 7" class="border border-gray-300 px-4 py-2 text-right">Subtotal (sebelum diskon)</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(totals.subtotal + totals.discount) }}</th>
                            <th class="border border-gray-300 px-4 py-2"></th>
                        </tr>
                        <tr class="text-sm">
                            <th :colspan="isDirectInvoice ? 6 : 7" class="border border-gray-300 px-4 py-2 text-right">Total Diskon</th>
                            <th class="border border-gray-300 px-4 py-2 text-right text-red-600">-{{ formatNumber(totals.discount) }}</th>
                            <th class="border border-gray-300 px-4 py-2"></th>
                        </tr>
                        <tr class="text-sm">
                            <th :colspan="isDirectInvoice ? 6 : 7" class="border border-gray-300 px-4 py-2 text-right">Total Pajak</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(totals.tax) }}</th>
                            <th class="border border-gray-300 px-4 py-2"></th>
                        </tr>
                        <tr class="text-sm font-semibold">
                            <th :colspan="isDirectInvoice ? 6 : 7" class="border border-gray-300 px-4 py-2 text-right">Grand Total</th>
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
                <p v-else-if="!selectedCustomerId">Pilih Customer terlebih dahulu.</p>
                <p v-else-if="salesOrderOptions.length === 0">Tidak ada Sales Order yang tersedia. Anda dapat membuat Direct Invoice.</p>
                <p v-else>Pilih Sales Order, atau klik "Tambah Baris" untuk Direct Invoice.</p>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="form.processing || !hasLines">
                {{ isEditMode ? 'Ubah' : 'Tambah' }} Faktur
            </AppPrimaryButton>
            <AppUtilityButton v-if="!isEditMode" type="button" @click="submitForm(true)" class="mr-2" :disabled="form.processing || !hasLines">
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('sales-invoices.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
