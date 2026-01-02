<script setup>
import { computed, watch, ref, onMounted, reactive } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import AppHint from '@/Components/AppHint.vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const props = defineProps({
    purchaseOrder: {
        type: Object,
        default: null,
    },
    companies: {
        type: Array,
        required: true,
    },
    branches: {
        type: Array,
        required: true,
    },
    currencies: {
        type: Array,
        required: true,
    },
    suppliers: {
        type: Array,
        required: true,
    },
    products: {
        type: Array,
        required: true,
    },
    uoms: {
        type: Array,
        required: true,
    },
    mode: {
        type: String,
        default: 'create',
    },
    submitLabel: {
        type: String,
        default: 'Simpan',
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    company_id: props.purchaseOrder?.company_id || null,
    branch_id: props.purchaseOrder?.branch_id || null,
    partner_id: props.purchaseOrder?.partner_id || null,
    currency_id: props.purchaseOrder?.currency_id || (page.props.primaryCurrency?.id || null),
    order_date: props.purchaseOrder?.order_date ? new Date(props.purchaseOrder?.order_date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    expected_date: props.purchaseOrder?.expected_date || '',
    supplier_reference: props.purchaseOrder?.supplier_reference || '',
    payment_terms: props.purchaseOrder?.payment_terms || '',
    exchange_rate: props.purchaseOrder?.exchange_rate || 1,
    notes: props.purchaseOrder?.notes || '',
    lines: props.purchaseOrder?.lines || [
        {
            product_id: null,
            product_variant_id: null,
            uom_id: null,
            quantity: 1,
            unit_price: 0,
            tax_rate: 0,
            description: '',
            expected_date: '',
            source_plan_line_id: null,
        }
    ],
    create_another: false,
});

const selectedPlanIds = ref([]);
const purchasePlans = ref([]);
const loadingPlans = ref(false);

// Store convertible UOMs per line index
const lineConvertibleUoms = reactive({});

const selectedCompany = ref(
    form.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id)
);

const supplierTableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama Supplier' },
    { key: 'actions', label: '' }
];

const supplierSearchUrl = computed(() => route('api.partners', {
    company_id: form.company_id,
    roles: ['supplier'],
}));

const selectedSupplierLabel = computed(() => {
    const supplier = props.suppliers.find((candidate) => candidate.id === form.partner_id);
    if (!supplier) {
        return '';
    }

    return `${supplier.code} — ${supplier.name}`;
});

// Product search configuration for AppPopoverSearch
const productSearchUrl = computed(() => {
    return route('api.products', { company_id: form.company_id });
});

const productTableHeaders = [
    { key: 'name', label: 'Nama Produk' },
    { key: 'category.name', label: 'Kategori' },
    { key: 'uom_code', label: 'UOM' },
    { key: 'actions', label: '' }
];

function getProductDisplayValue(productId) {
    const product = filteredProducts.value.find(p => p.id === productId);
    return product ? product.name : '';
}

const filteredSuppliers = computed(() => {
    if (!form.company_id) {
        return props.suppliers;
    }

    return props.suppliers.filter((supplier) =>
        Array.isArray(supplier.company_ids) && supplier.company_ids.includes(form.company_id)
    );
});

const filteredProducts = computed(() => {
    if (!form.company_id) {
        return props.products;
    }

    return props.products.filter((product) =>
        Array.isArray(product.company_ids) && product.company_ids.includes(form.company_id)
    );
});

const variantLookup = computed(() => {
    const lookup = {};

    props.products.forEach((product) => {
        product.variants.forEach((variant) => {
            lookup[variant.id] = {
                ...variant,
                product_name: product.name,
                company_ids: product.company_ids,
            };
        });
    });

    return lookup;
});

const productOptions = computed(() =>
    filteredProducts.value.map((product) => ({
        value: product.id,
        label: product.name,
    }))
);

function getVariantsForProduct(productId) {
    if (!productId) {
        return [];
    }

    const product = filteredProducts.value.find((p) => p.id === productId);
    if (!product || !product.variants || product.variants.length === 0) {
        return [];
    }

    return product.variants.map((variant) => ({
        value: variant.id,
        label: variant.sku,
        description: variant.barcode,
    }));
}

const uomOptions = computed(() => {
    if (!form.company_id) {
        return props.uoms;
    }

    return props.uoms.filter((uom) => uom.company_id === form.company_id);
});

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

watch(selectedCompany, (newCompanyId) => {
    if (props.mode === 'create') {
        form.company_id = newCompanyId;
        router.reload({ only: ['branches'], data: { company_id: newCompanyId } });
    }
}, { immediate: true });

watch(
    () => props.branches,
    (newBranches) => {
        if (props.mode === 'create' && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

watch(
    () => form.company_id,
    () => {
        if (form.branch_id && !props.branches.some((branch) => branch.id === form.branch_id)) {
            form.branch_id = '';
        }

        if (form.partner_id && !filteredSuppliers.value.some((supplier) => supplier.id === form.partner_id)) {
            form.partner_id = '';
        }

        form.lines.forEach((line) => {
            if (!line.product_id) {
                return;
            }

            const product = filteredProducts.value.find((p) => p.id === line.product_id);
            if (!product) {
                resetLine(line);
                return;
            }

            if (line.product_variant_id) {
                const variant = product.variants.find((v) => v.id === line.product_variant_id);
                if (!variant) {
                    line.product_variant_id = null;
                    line.uom_id = null;
                }
            }
        });
    }
);

onMounted(() => {
    selectedCompany.value = form.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id);
    if (props.mode === 'create' && props.branches.length === 1) {
        form.branch_id = props.branches[0].id;
    }
});

// Fetch purchase plans when branch changes
watch(
    () => form.branch_id,
    async (newBranchId) => {
        selectedPlanIds.value = [];
        
        if (!newBranchId) {
            purchasePlans.value = [];
            return;
        }
        
        if (props.mode !== 'create') {
            return;
        }
        
        loadingPlans.value = true;
        try {
            const response = await axios.get(route('api.purchase-plans'), {
                params: { branch_id: newBranchId }
            });
            purchasePlans.value = response.data;
        } catch (error) {
            console.error('Error fetching purchase plans:', error);
            purchasePlans.value = [];
        } finally {
            loadingPlans.value = false;
        }
    },
    { immediate: true }
);

function lineUomOptions(line, lineIndex) {
    const convertibleUoms = lineConvertibleUoms[lineIndex];
    
    // If we have fetched convertible UOMs for this line, use them
    if (convertibleUoms && convertibleUoms.length > 0) {
        return convertibleUoms.map(u => ({ value: u.id, label: u.code, description: u.name }));
    }
    
    // Fallback: filter by UOM kind if variant is selected
    if (line.product_id && line.product_variant_id) {
        const product = filteredProducts.value.find((p) => p.id === line.product_id);
        if (product) {
            const variant = product.variants.find((v) => v.id === line.product_variant_id);
            if (variant?.uom?.kind) {
                return uomOptions.value
                    .filter((uom) => uom.kind === variant.uom.kind)
                    .map(u => ({ value: u.id, label: u.code, description: u.name }));
            }
        }
    }
    
    // No restrictions - show all UOMs for the company
    return uomOptions.value.map(u => ({ value: u.id, label: u.code, description: u.name }));
}

function addLine() {
    form.lines.push(createEmptyLine());
}

function removeLine(index) {
    if (form.lines.length === 1) {
        return;
    }

    form.lines.splice(index, 1);
}

function createEmptyLine() {
    return {
        product_id: null,
        product_variant_id: null,
        uom_id: null,
        quantity: 1,
        unit_price: 0,
        tax_rate: 0,
        description: '',
        expected_date: '',
        source_plan_line_id: null,
    };
}

/**
 * Fetch convertible UOMs for a line based on the product's default UOM.
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
                company_id: form.company_id,
            }
        });
        lineConvertibleUoms[lineIndex] = response.data;
    } catch (error) {
        console.error('Error fetching convertible UOMs:', error);
        // On error, don't restrict UOMs
        delete lineConvertibleUoms[lineIndex];
    }
}

/**
 * Populate PO lines from selected Purchase Plans (supports multiple).
 */
async function populateFromPlans() {
    if (selectedPlanIds.value.length === 0) return;
    
    const selectedPlans = purchasePlans.value.filter(p => selectedPlanIds.value.includes(p.id));
    if (selectedPlans.length === 0) return;
    
    // Clear existing convertible UOMs
    Object.keys(lineConvertibleUoms).forEach(key => delete lineConvertibleUoms[key]);
    
    // Collect all lines from selected plans
    const allLines = selectedPlans.flatMap(plan => 
        plan.lines.map(planLine => ({
            product_id: planLine.product_id,
            product_variant_id: planLine.product_variant_id,
            uom_id: planLine.uom_id,
            quantity: planLine.remaining_qty,
            unit_price: 0,
            tax_rate: 0,
            description: planLine.description || planLine.product_name,
            expected_date: '',
            source_plan_line_id: planLine.id,
        }))
    );
    
    if (allLines.length > 0) {
        form.lines = allLines;
        
        // Fetch convertible UOMs for each populated line
        allLines.forEach((line, index) => {
            const product = filteredProducts.value.find(p => p.id === line.product_id);
            const defaultUomId = product?.default_uom_id;
            if (defaultUomId) {
                fetchConvertibleUoms(index, defaultUomId, line.product_id);
            }
        });
    }
}

function resetLine(line) {
    line.product_id = null;
    line.product_variant_id = null;
    line.uom_id = null;
    line.quantity = 1;
    line.unit_price = 0;
    line.tax_rate = 0;
    line.description = '';
    line.expected_date = '';
}

async function handleProductChange(line, productId) {
    const lineIndex = form.lines.indexOf(line);
    const product = filteredProducts.value.find((p) => p.id === productId);
    
    line.product_id = productId;
    line.product_variant_id = null;
    line.uom_id = null;
    
    // Clear existing convertible UOMs for this line
    delete lineConvertibleUoms[lineIndex];
    
    if (!line.description && product) {
        line.description = product.name;
    }
    
    // Set the default UOM for the product and fetch convertible UOMs
    if (product?.default_uom_id) {
        line.uom_id = product.default_uom_id;
        // Fetch convertible UOMs from the default UOM
        await fetchConvertibleUoms(lineIndex, product.default_uom_id, productId);
    }
    
    // If product has exactly one variant, auto-select it and use its UOM if no default
    if (product?.variants?.length === 1) {
        const variant = product.variants[0];
        line.product_variant_id = variant.id;
        if (!line.uom_id && variant.uom_id) {
            line.uom_id = variant.uom_id;
        }
    }
}

function syncVariant(line) {
    if (!line.product_id || !line.product_variant_id) {
        return;
    }

    const product = filteredProducts.value.find((p) => p.id === line.product_id);
    if (!product) {
        return;
    }

    const variant = product.variants.find((v) => v.id === line.product_variant_id);
    if (!variant) {
        return;
    }

    line.uom_id = variant.uom?.id || line.uom_id;

    if (!line.description) {
        line.description = product.name;
    }

    // Auto-fetch suggested tax rate from API
    fetchTaxQuote(line);
}

/**
 * Fetch suggested tax rate from API for a line item.
 * Uses product variant, partner, and company context to resolve tax rule.
 */
async function fetchTaxQuote(line) {
    if (!line.product_variant_id) {
        return;
    }

    try {
        const params = {
            product_variant_id: line.product_variant_id,
        };

        if (form.partner_id) {
            params.partner_id = form.partner_id;
        }

        if (form.company_id) {
            params.company_id = form.company_id;
        }

        if (form.order_date) {
            params.date = form.order_date;
        }

        const response = await axios.get(route('api.tax-quote'), { params });
        
        if (response.data?.success && response.data?.data?.rate !== undefined) {
            // Only auto-fill if tax_rate is still 0 (not manually set)
            if (line.tax_rate === 0 || line.tax_rate === '0' || line.tax_rate === '') {
                line.tax_rate = response.data.data.rate;
            }
        }
    } catch (error) {
        // Silently fail - user can still manually enter tax rate
        console.warn('Failed to fetch tax quote:', error);
    }
}

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

const submitted = ref(false);
function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;
   if (props.purchaseOrder) {
      form.put(route('purchase-orders.update', props.purchaseOrder.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('purchase-orders.store'), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
            if (createAnother) {
               form.reset();
               form.clearErrors();
            }
         },
         onError: () => {
            submitted.value = false;
         }
      });
   }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCompany"
                        :options="companies.map((company) => ({ value: company.id, label: company.name }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        :error="form.errors?.company_id"
                        :disabled="mode === 'edit'"
                        required
                    />

                    <AppSelect
                        v-model="form.branch_id"
                        :options="branches.map((branch) => ({ value: branch.id, label: branch.name }))"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors?.branch_id"
                        :disabled="mode === 'edit' || !form.company_id"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <AppInput
                        v-model="form.order_date"
                        type="date"
                        label="Tanggal PO:"
                        required
                        :error="form.errors?.order_date"
                    />

                    <div>
                        <AppPopoverSearch
                            v-model="form.partner_id"
                            label="Supplier:"
                            placeholder="Pilih Supplier"
                            hint="Gunakan pencarian untuk menemukan supplier berdasarkan perusahaan yang dipilih."
                            :url="supplierSearchUrl"
                            :tableHeaders="supplierTableHeaders"
                            :displayKeys="['code', 'name']"
                            :initialDisplayValue="selectedSupplierLabel"
                            :disabled="!form.company_id"
                            modalTitle="Pilih Supplier"
                            required
                            :error="form.errors?.partner_id"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <AppSelect
                        v-model="form.currency_id"
                        :options="currencies.map((currency) => ({ value: currency.id, label: `${currency.code} — ${currency.name}` }))"
                        label="Mata Uang:"
                        required
                        :error="form.errors?.currency_id"
                    />

                    <AppInput
                        v-model="form.exchange_rate"
                        type="number"
                        step="0.0001"
                        label="Kurs:"
                        required
                        min="0.0001"
                        :error="form.errors?.exchange_rate"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <AppInput
                        v-model="form.expected_date"
                        type="date"
                        label="Estimasi Kedatangan:"
                        :error="form.errors?.expected_date"
                    />

                    <AppInput
                        v-model="form.supplier_reference"
                        label="Referensi Supplier:"
                        placeholder="Nomor referensi (opsional)"
                        :error="form.errors?.supplier_reference"
                    />
                </div>

                <div class="mt-4">
                    <AppInput
                        v-model="form.payment_terms"
                        label="Syarat Pembayaran:"
                        placeholder="Net 30, COD, dll"
                        :error="form.errors?.payment_terms"
                    />
                </div>

                <div class="mt-4">
                    <AppTextarea
                        v-model="form.notes"
                        label="Catatan:"
                        rows="3"
                        placeholder="Instruksi tambahan yang ingin disampaikan ke supplier."
                        :error="form.errors?.notes"
                    />
                </div>

                <div v-if="mode === 'create' && form.branch_id" class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <label class="block text-sm font-medium text-blue-800 mb-2">Isi dari Rencana Pembelian:</label>
                    
                    <div v-if="loadingPlans" class="text-sm text-blue-600 py-2">
                        Memuat rencana pembelian...
                    </div>
                    
                    <div v-else-if="purchasePlans.length === 0" class="text-sm text-blue-600 py-2">
                        Tidak ada rencana pembelian yang tersedia untuk cabang ini.
                    </div>
                    
                    <template v-else>
                        <AppSelect
                            v-model="selectedPlanIds"
                            :options="purchasePlans.map(plan => ({ value: plan.id, label: `${plan.plan_number} (${plan.plan_date})`, description: `${plan.lines.length} item` }))"
                            placeholder="Pilih Rencana Pembelian (dapat lebih dari satu)"
                            :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            :multiple="true"
                        />
                        
                        <button
                            v-if="selectedPlanIds.length > 0"
                            type="button"
                            @click="populateFromPlans"
                            class="mt-2 text-sm text-blue-600 hover:text-blue-800 underline font-medium"
                        >
                            Gunakan {{ selectedPlanIds.length }} rencana terpilih
                        </button>
                    </template>
                    
                    <p class="text-xs text-blue-600 mt-2">Pilih satu atau lebih rencana pembelian untuk mengisi baris PO secara otomatis.</p>
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Purchase Order</h3>
                <p class="mb-2">Purchase Order adalah dokumen yang digunakan untuk memesan barang atau jasa dari supplier. Pastikan informasi yang dimasukkan akurat.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan yang sesuai</li>
                    <li>Pilih cabang yang sesuai</li>
                    <li>Pilih supplier yang akan menerima PO</li>
                    <li>Pilih mata uang dan kurs yang sesuai</li>
                    <li>Tanggal PO adalah tanggal pembuatan purchase order</li>
                    <li>Estimasi kedatangan adalah perkiraan kapan barang akan tiba</li>
                    <li>Referensi supplier adalah nomor referensi dari supplier (opsional)</li>
                    <li>Syarat pembayaran menjelaskan cara pembayaran (opsional)</li>
                    <li>Tambahkan baris untuk setiap item yang akan dipesan</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <h2 class="text-lg font-semibold">Baris Purchase Order</h2>
            <p class="text-sm text-gray-500 mb-4">Lengkapi detail item termasuk kuantitas, satuan, harga, dan pajak.</p>

            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-48 lg:min-w-48 px-1.5 py-1.5">Produk</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Satuan</th>
                        <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Qty</th>
                        <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Harga Satuan</th>
                        <th class="border border-gray-300 text-sm min-w-16 px-1.5 py-1.5">
                            Pajak (%)
                            <AppHint text="Isi 11 untuk PPN 11%. Kosongkan jika non taxable." />
                        </th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Tgl Kedatangan</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Subtotal</th>
                        <th class="border border-gray-300 px-1.5 py-1.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(line, index) in form.lines" :key="index">
                        <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppPopoverSearch
                                v-model="line.product_id"
                                :url="productSearchUrl"
                                :tableHeaders="productTableHeaders"
                                :displayKeys="['name']"
                                :initialDisplayValue="getProductDisplayValue(line.product_id)"
                                placeholder="Pilih Produk"
                                modalTitle="Pilih Produk"
                                :error="form.errors?.[`lines.${index}.product_id`]"
                                @update:modelValue="handleProductChange(line, $event)"
                                :disabled="!form.company_id"
                                required
                            />

                            <AppSelect
                                v-model="line.product_variant_id"
                                :options="getVariantsForProduct(line.product_id)"
                                :placeholder="!line.product_id ? 'Pilih produk terlebih dahulu' : getVariantsForProduct(line.product_id).length === 0 ? 'Produk tidak memiliki varian' : 'Pilih varian'"
                                :error="form.errors?.[`lines.${index}.product_variant_id`]"
                                :disabled="!line.product_id || getVariantsForProduct(line.product_id).length === 0"
                                :required="line.product_id && getVariantsForProduct(line.product_id).length > 0"
                                @update:modelValue="syncVariant(line)"
                                :margins="{ top: 2, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppSelect
                                v-model="line.uom_id"
                                :options="lineUomOptions(line, index)"
                                placeholder="Satuan"
                                :error="form.errors?.[`lines.${index}.uom_id`]"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppInput
                                v-model="line.quantity"
                                :numberFormat="true"
                                required
                                :error="form.errors?.[`lines.${index}.quantity`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppInput
                                v-model="line.unit_price"
                                :numberFormat="true"
                                required
                                :error="form.errors?.[`lines.${index}.unit_price`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppInput
                                v-model="line.tax_rate"
                                hint="Isi 11 untuk PPN 11%. Kosongkan jika non taxable."
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0"
                                :error="form.errors?.[`lines.${index}.tax_rate`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppInput
                                v-model="line.expected_date"
                                type="date"
                                :error="form.errors?.[`lines.${index}.expected_date`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
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
                        <td class="border border-gray-300 px-1.5 py-1.5 text-center align-top">
                            <button type="button" @click="removeLine(index)" :disabled="form.lines.length === 1" class="text-red-500 hover:text-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>

                <tfoot>
                    <tr class="text-sm">
                        <th class="border border-gray-300 px-4 py-2 text-right" colspan="6">Total</th>
                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(totals.subtotal) }}</th>
                        <th class="border border-gray-300 px-4 py-2"></th>
                    </tr>
                    <tr class="text-sm">
                        <th class="border border-gray-300 px-4 py-2 text-right" colspan="6">Total Pajak</th>
                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(totals.tax) }}</th>
                        <th class="border border-gray-300 px-4 py-2"></th>
                    </tr>
                    <tr class="text-sm font-semibold">
                        <th class="border border-gray-300 px-4 py-2 text-right" colspan="6">Grand Total</th>
                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(grandTotal) }}</th>
                        <th class="border border-gray-300 px-4 py-2"></th>
                    </tr>
                </tfoot>
            </table>
            <div class="flex mt-2 mb-4">
                <button type="button" @click="addLine" class="flex items-center text-main-500 hover:text-main-700">
                    <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Baris
                </button>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="form.processing">
                {{ submitLabel }}
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.purchaseOrder" type="button" @click="submitForm(true)" class="mr-2">
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('purchase-orders.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
