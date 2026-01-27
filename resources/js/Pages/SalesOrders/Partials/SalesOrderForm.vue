<script setup>
import { computed, watch, ref, onMounted } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppHint from '@/Components/AppHint.vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const props = defineProps({
    salesOrder: {
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
    customers: {
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
    locations: {
        type: Array,
        required: true,
    },
    channels: {
        type: Object,
        required: true,
    },
    companyBankAccounts: {
        type: Array,
        default: () => [],
    },
    paymentMethods: {
        type: Array,
        default: () => [],
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
    costItems: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    company_id: props.salesOrder?.company_id || null,
    branch_id: props.salesOrder?.branch_id || null,
    partner_id: props.salesOrder?.partner_id || null,
    currency_id: props.salesOrder?.currency_id || (page.props.primaryCurrency?.id || null),
    order_date: props.salesOrder?.order_date ? new Date(props.salesOrder?.order_date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    expected_delivery_date: props.salesOrder?.expected_delivery_date || '',
    quote_valid_until: props.salesOrder?.quote_valid_until || '',
    customer_reference: props.salesOrder?.customer_reference || '',
    sales_channel: props.salesOrder?.sales_channel || null,
    payment_terms: props.salesOrder?.payment_terms || '',
    exchange_rate: props.salesOrder?.exchange_rate || 1,
    reserve_stock: props.salesOrder?.reserve_stock ?? false,
    notes: props.salesOrder?.notes || '',
    payment_method: props.salesOrder?.payment_method || null,
    company_bank_account_id: props.salesOrder?.company_bank_account_id || null,
    midtrans_code: props.salesOrder?.midtrans_code ?? null,
    paypal_code: props.salesOrder?.paypal_code ?? null,
    lines: props.salesOrder?.lines || [createEmptyLine()],
    costs: props.salesOrder?.costs?.map(c => ({
        id: c.id,
        description: c.description,
        cost_item_id: c.cost_item_id,
        amount: c.amount,
        currency_id: c.currency_id,
        exchange_rate: c.exchange_rate,
    })) || [],
    sales_person_id: props.salesOrder?.sales_person_id || page.props.auth?.user?.global_id || null,
    shipping_address_id: props.salesOrder?.shipping_address_id || null,
    invoice_address_id: props.salesOrder?.invoice_address_id || null,
    create_another: false,
});

const selectedCompany = ref(
    form.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id)
);

const partnerAddresses = ref([]);
const availabilityState = ref({});

const customerTableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama Pelanggan' },
    { key: 'actions', label: '' }
];

const customerSearchUrl = computed(() => route('api.partners', {
    company_id: form.company_id,
    roles: ['customer'],
}));

const selectedCustomerLabel = computed(() => {
    const customer = props.customers.find((candidate) => candidate.id === form.partner_id);
    if (!customer) {
        return '';
    }
    return `${customer.code} — ${customer.name}`;
});

const filteredCustomers = computed(() => {
    if (!form.company_id) {
        return props.customers;
    }
    return props.customers.filter((customer) =>
        Array.isArray(customer.company_ids) && customer.company_ids.includes(form.company_id)
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

const filteredLocations = computed(() => {
    if (!form.branch_id) {
        return props.locations;
    }
    return props.locations.filter((location) => location.branch_id === form.branch_id);
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

const channelOptions = computed(() => [
    { value: null, label: 'Pilih channel' },
    ...Object.entries(props.channels).map(([value, label]) => ({ value, label }))
]);

const paymentMethodOptions = computed(() => [
    { value: null, label: 'Pilih Metode Pembayaran' },
    ...props.paymentMethods
]);

const filteredBankAccounts = computed(() => {
    if (!form.company_id) return [];
    return props.companyBankAccounts.filter(ba => ba.company_id === form.company_id);
});

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

function getResourcePools(line) {
    if (!line.product_id) {
        return [];
    }
    const product = filteredProducts.value.find((p) => p.id === line.product_id);
    return product?.resource_pools || [];
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
            const discountRate = Number(line.discount_rate) || 0;
            const taxRate = Number(line.tax_rate) || 0;
            const lineGross = quantity * price;
            const lineDiscountAmt = lineGross * (discountRate / 100);
            const lineSubtotal = lineGross - lineDiscountAmt;
            const lineTax = lineSubtotal * (taxRate / 100);
            carry.subtotal += lineSubtotal;
            carry.discount += lineDiscountAmt;
            carry.tax += lineTax;
            return carry;
        },
        { subtotal: 0, discount: 0, tax: 0 }
    );
});

const grandTotal = computed(() => totals.value.subtotal + totals.value.tax);

// Determine if we have stock-based or booking-based products in the lines
const hasStockProducts = computed(() => {
    return form.lines.some((line) => {
        if (!line.product_id) return false;
        const product = filteredProducts.value.find((p) => p.id === line.product_id);
        return !product?.resource_pools || product.resource_pools.length === 0;
    });
});

const hasBookingProducts = computed(() => {
    return form.lines.some((line) => {
        if (!line.product_id) return false;
        const product = filteredProducts.value.find((p) => p.id === line.product_id);
        return product?.resource_pools && product.resource_pools.length > 0;
    });
});

// Check if product is a booking product
function isBookingProduct(line) {
    if (!line.product_id) return false;
    const product = filteredProducts.value.find((p) => p.id === line.product_id);
    return product?.resource_pools && product.resource_pools.length > 0;
}


watch(selectedCompany, (newCompanyId) => {
    if (props.mode === 'create') {
        form.company_id = newCompanyId;
        router.reload({ only: ['branches'], data: { company_id: newCompanyId } });
    }
    if (props.mode === 'create') {
        form.company_id = newCompanyId;
        router.reload({ only: ['branches'], data: { company_id: newCompanyId } });
    }
}, { immediate: true });

async function fetchPartnerAddresses(partnerId) {
    if (!partnerId) {
        partnerAddresses.value = [];
        return;
    }
    try {
        const response = await axios.get(route('api.partners.show', partnerId));
        partnerAddresses.value = response.data.addresses || [];
    } catch (error) {
        console.error('Failed to fetch partner addresses:', error);
        partnerAddresses.value = [];
    }
}

watch(() => form.partner_id, (newVal) => {
    fetchPartnerAddresses(newVal);
});

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
        if (form.partner_id && !filteredCustomers.value.some((customer) => customer.id === form.partner_id)) {
            form.partner_id = '';
        }
        form.lines.forEach((line, index) => {
            if (!line.product_id) {
                return;
            }
            const product = filteredProducts.value.find((p) => p.id === line.product_id);
            if (!product) {
                resetLine(line);
                availabilityState.value[index] = null;
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
    if (props.mode === 'create' && props.branches.length === 1) {
        form.branch_id = props.branches[0].id;
    }
    if (form.partner_id) {
        fetchPartnerAddresses(form.partner_id);
    }
});

function lineUomOptions(line) {
    // If variant is selected, filter by variant's UOM kind
    if (line.product_id && line.product_variant_id) {
        const product = filteredProducts.value.find((p) => p.id === line.product_id);
        if (product) {
            const variant = product.variants.find((v) => v.id === line.product_variant_id);
            if (variant?.uom?.kind) {
                return uomOptions.value.filter((uom) => uom.kind === variant.uom.kind);
            }
        }
    }
    
    // If only product is selected, filter by product's default UOM kind
    if (line.product_id) {
        const product = filteredProducts.value.find((p) => p.id === line.product_id);
        if (product?.default_uom?.kind) {
            return uomOptions.value.filter((uom) => uom.kind === product.default_uom.kind);
        }
    }
    
    return uomOptions.value;
}

function createEmptyLine() {
    return {
        product_id: null,
        product_variant_id: null,
        uom_id: null,
        quantity: 1,
        unit_price: 0,
        discount_rate: 0,
        tax_rate: 0,
        description: '',
        requested_delivery_date: '',
        reservation_location_id: null,
        start_date: '',
        end_date: '',
        resource_pool_id: null,
    };
}

function addLine() {
    form.lines.push(createEmptyLine());
}

function createEmptyCost() {
    return {
        description: '',
        cost_item_id: null,
        amount: 0,
        currency_id: form.currency_id,
        exchange_rate: form.exchange_rate || 1,
    };
}

function addCost() {
    form.costs.push(createEmptyCost());
}

function removeCost(index) {
    form.costs.splice(index, 1);
}

function removeLine(index) {
    if (form.lines.length === 1) {
        return;
    }
    form.lines.splice(index, 1);
    delete availabilityState.value[index];
}

function resetLine(line) {
    line.product_id = null;
    line.product_variant_id = null;
    line.uom_id = null;
    line.quantity = 1;
    line.unit_price = 0;
    line.tax_rate = 0;
    line.description = '';
    line.requested_delivery_date = '';
    line.reservation_location_id = null;
    line.start_date = '';
    line.end_date = '';
    line.resource_pool_id = null;
}

async function handleProductChange(line, productId, index) {
    const product = filteredProducts.value.find((p) => p.id === productId);

    console.log(product);
    
    line.product_id = productId;
    line.product_variant_id = null;
    line.resource_pool_id = null;
    
    if (!line.description && product) {
        line.description = product.name;
    }
    
    // Set UOM from product's default UOM (access via relationship)
    if (product?.default_uom?.id) {
        line.uom_id = product.default_uom.id;
    } else {
        line.uom_id = null;
    }
    
    // Auto-select pool if only one exists
    if (product?.resource_pools && product.resource_pools.length === 1) {
        line.resource_pool_id = product.resource_pools[0].id;
    }
    
    // If product has exactly one variant, auto-select it
    if (product?.variants?.length === 1) {
        const variant = product.variants[0];
        line.product_variant_id = variant.id;
        if (variant.uom?.id) {
            line.uom_id = variant.uom.id;
        }
    }
    
    // Reset price to allow re-fetch
    line.unit_price = 0;
    line.tax_rate = 0;
    
    // Fetch price quote for the product
    await fetchPriceQuoteForProduct(line);
    // Fetch tax quote for the product (now works with just product_id)
    await fetchTaxQuoteForProduct(line);
}

async function syncVariant(line, index) {
    const product = filteredProducts.value.find((p) => p.id === line.product_id);
    if (!product) {
        return;
    }
    
    // If variant is being removed (cleared)
    if (!line.product_variant_id) {
        // Restore product's default UOM
        if (product?.default_uom?.id) {
            line.uom_id = product.default_uom.id;
        }
        
        // Reset and re-fetch price/tax with just product
        line.unit_price = 0;
        line.tax_rate = 0;
        
        await fetchPriceQuoteForProduct(line);
        await fetchTaxQuoteForProduct(line);
        return;
    }
    
    const variant = product.variants.find((v) => v.id === line.product_variant_id);
    if (!variant) {
        return;
    }
    
    // Always use variant's UOM if available
    if (variant.uom?.id) {
        line.uom_id = variant.uom.id;
    }
    
    if (!line.description) {
        line.description = product.name;
    }
    
    fetchAvailability(index);
    
    // Reset price to force re-fetch with variant context
    line.unit_price = 0;
    line.tax_rate = 0;
    
    fetchPriceQuote(line, index);
    fetchTaxQuote(line, index);
}

async function fetchPriceQuoteForProduct(line) {
    if (!line.product_id || !line.uom_id) {
        return;
    }
    // Only fetch if price is not already set
    if (line.unit_price && Number(line.unit_price) > 0) {
        return;
    }
    try {
        const params = {
            product_id: line.product_id,
            uom_id: line.uom_id,
            quantity: line.quantity || 1,
        };
        if (line.product_variant_id) params.product_variant_id = line.product_variant_id;
        if (form.partner_id) params.partner_id = form.partner_id;
        if (form.company_id) params.company_id = form.company_id;
        if (form.currency_id) params.currency_id = form.currency_id;
        if (form.sales_channel) params.channel = form.sales_channel;
        if (form.order_date) params.date = form.order_date;

        const response = await axios.get(route('api.price-quote'), { params });
        if (response.data?.success && response.data?.data?.price !== undefined) {
            line.unit_price = response.data.data.price;
        }
    } catch (error) {
        console.warn('Failed to fetch price quote:', error);
    }
}

async function fetchTaxQuoteForProduct(line) {
    if (!line.product_id) {
        return;
    }
    if (line.tax_rate && Number(line.tax_rate) > 0) {
        return;
    }
    try {
        const params = {};
        // Prefer variant if available, otherwise use product
        if (line.product_variant_id) {
            params.product_variant_id = line.product_variant_id;
        } else {
            params.product_id = line.product_id;
        }
        if (form.partner_id) params.partner_id = form.partner_id;
        if (form.company_id) params.company_id = form.company_id;
        if (form.order_date) params.date = form.order_date;

        const response = await axios.get(route('api.tax-quote'), { params });
        if (response.data?.success && response.data?.data?.rate !== undefined) {
            line.tax_rate = response.data.data.rate;
        }
    } catch (error) {
        console.warn('Failed to fetch tax quote:', error);
    }
}

async function fetchAvailability(index) {
    const line = form.lines[index];
    if (!line.product_variant_id || !form.branch_id) {
        availabilityState.value[index] = null;
        return;
    }
    try {
        const { data } = await axios.get(route('api.inventory.availability'), {
            params: {
                product_variant_id: line.product_variant_id,
                branch_id: form.branch_id,
            },
        });
        availabilityState.value[index] = data;
    } catch (error) {
        availabilityState.value[index] = null;
    }
}

// Booking availability state
const bookingAvailabilityState = ref({});

async function fetchBookingAvailability(index) {
    const line = form.lines[index];
    if (!line.resource_pool_id || !line.start_date || !line.end_date || !line.quantity) {
        bookingAvailabilityState.value[index] = null;
        return;
    }
    try {
        const { data } = await axios.get(route('api.bookings.check-availability'), {
            params: {
                resource_pool_id: line.resource_pool_id,
                start_datetime: line.start_date,
                end_datetime: line.end_date,
                qty: line.quantity,
            },
        });
        bookingAvailabilityState.value[index] = data;
    } catch (error) {
        bookingAvailabilityState.value[index] = { available: false, error: true };
    }
}

function bookingAvailabilityLabel(index) {
    const state = bookingAvailabilityState.value[index];
    if (!state) return '';
    if (state.error) return 'Error';
    if (state.available) {
        return `Tersedia (${state.available_qty})`;
    }
    return `Tidak tersedia (${state.available_qty}/${state.requested_qty})`;
}

function bookingAvailabilityClass(index) {
    const state = bookingAvailabilityState.value[index];
    if (!state) return '';
    if (state.error) return 'text-red-600';
    return state.available ? 'text-emerald-600' : 'text-red-600';
}

async function fetchPriceQuote(line, index) {
    if (!line.product_variant_id || !line.uom_id) {
        return;
    }
    if (line.unit_price && Number(line.unit_price) > 0) {
        return;
    }
    try {
        const params = {
            product_variant_id: line.product_variant_id,
            uom_id: line.uom_id,
            quantity: line.quantity || 1,
        };
        if (form.partner_id) params.partner_id = form.partner_id;
        if (form.company_id) params.company_id = form.company_id;
        if (form.currency_id) params.currency_id = form.currency_id;
        if (form.sales_channel) params.channel = form.sales_channel;
        if (form.order_date) params.date = form.order_date;

        const response = await axios.get(route('api.price-quote'), { params });
        if (response.data?.success && response.data?.data?.price !== undefined) {
            line.unit_price = response.data.data.price;
        }
    } catch (error) {
        console.warn('Failed to fetch price quote:', error);
    }
}

async function fetchTaxQuote(line, index) {
    if (!line.product_variant_id) {
        return;
    }
    if (line.tax_rate && Number(line.tax_rate) > 0) {
        return;
    }
    try {
        const params = { product_variant_id: line.product_variant_id };
        if (form.partner_id) params.partner_id = form.partner_id;
        if (form.company_id) params.company_id = form.company_id;
        if (form.order_date) params.date = form.order_date;

        const response = await axios.get(route('api.tax-quote'), { params });
        if (response.data?.success && response.data?.data?.rate !== undefined) {
            line.tax_rate = response.data.data.rate;
        }
    } catch (error) {
        console.warn('Failed to fetch tax quote:', error);
    }
}

function availabilityLabel(index) {
    const snapshot = availabilityState.value[index];
    if (!snapshot) return '—';
    return `On hand: ${formatNumber(snapshot.on_hand)} • Reserved: ${formatNumber(snapshot.reserved)} • Tersedia: ${formatNumber(snapshot.available)}`;
}

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
    return (quantity * price) * (discountRate / 100);
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
    if (props.salesOrder) {
        form.put(route('sales-orders.update', props.salesOrder.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; }
        });
    } else {
        form.post(route('sales-orders.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            },
            onError: () => { submitted.value = false; }
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
                        label="Tanggal SO:"
                        required
                        :error="form.errors?.order_date"
                    />

                    <div>
                        <AppPopoverSearch
                            v-model="form.partner_id"
                            label="Pelanggan:"
                            placeholder="Pilih Pelanggan"
                            hint="Gunakan pencarian untuk menemukan pelanggan berdasarkan perusahaan yang dipilih."
                            :url="customerSearchUrl"
                            :tableHeaders="customerTableHeaders"
                            :displayKeys="['code', 'name']"
                            :initialDisplayValue="selectedCustomerLabel"
                            :disabled="!form.company_id"
                            modalTitle="Pilih Pelanggan"
                            required
                            :error="form.errors?.partner_id"
                        />
                    </div>

                    <AppSelect
                        v-model="form.sales_person_id"
                        :options="users"
                        label="Sales Person:"
                        placeholder="Pilih Sales Person"
                        :error="form.errors?.sales_person_id"
                    />

                    <AppSelect
                        v-model="form.shipping_address_id"
                        :options="[{ value: null, label: 'Sama dengan alamat utama' }, ...partnerAddresses.map((addr) => ({ value: addr.id, label: addr.name + ' - ' + addr.address }))]"
                        label="Alamat Pengiriman:"
                        :placeholder="partnerAddresses.length ? 'Pilih Alamat Pengiriman' : 'Partner tidak memiliki alamat tambahan'"
                        :error="form.errors?.shipping_address_id"
                        :disabled="!form.partner_id || partnerAddresses.length === 0"
                    />

                    <AppSelect
                        v-model="form.invoice_address_id"
                        :options="[{ value: null, label: 'Sama dengan alamat utama' }, ...partnerAddresses.map((addr) => ({ value: addr.id, label: addr.name + ' - ' + addr.address }))]"
                        label="Alamat Tagihan:"
                        :placeholder="partnerAddresses.length ? 'Pilih Alamat Tagihan' : 'Partner tidak memiliki alamat tambahan'"
                        :error="form.errors?.invoice_address_id"
                        :disabled="!form.partner_id || partnerAddresses.length === 0"
                    />
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
                        v-if="hasStockProducts"
                        v-model="form.expected_delivery_date"
                        type="date"
                        label="Estimasi Kirim:"
                        :error="form.errors?.expected_delivery_date"
                    />

                    <AppSelect
                        v-model="form.sales_channel"
                        :options="channelOptions"
                        label="Channel Penjualan:"
                        :error="form.errors?.sales_channel"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <AppInput
                        v-model="form.quote_valid_until"
                        type="date"
                        label="Berlaku Sampai:"
                        :error="form.errors?.quote_valid_until"
                    />

                    <AppInput
                        v-model="form.customer_reference"
                        label="Referensi Pelanggan:"
                        placeholder="Nomor referensi (opsional)"
                        :error="form.errors?.customer_reference"
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

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <AppSelect
                        v-model="form.payment_method"
                        :options="paymentMethodOptions"
                        label="Metode Pembayaran:"
                        :error="form.errors?.payment_method"
                    />

                    <AppSelect
                        v-if="form.payment_method === 'transfer'"
                        v-model="form.company_bank_account_id"
                        :options="filteredBankAccounts"
                        label="Rekening Bank Perusahaan:"
                        :error="form.errors?.company_bank_account_id"
                        placeholder="Pilih Rekening Bank"
                    />
                </div>

                <!-- Midtrans Code -->
                <div v-if="form.payment_method === 'midtrans'" class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.midtrans_code"
                        label="Kode Midtrans:"
                        :error="form.errors?.midtrans_code"
                        placeholder="Masukkan kode Midtrans"
                        required
                    />
                </div>

                <!-- Paypal Code -->
                <div v-if="form.payment_method === 'paypal'" class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.paypal_code"
                        label="Kode Paypal:"
                        :error="form.errors?.paypal_code"
                        placeholder="Masukkan kode Paypal"
                        required
                    />
                </div>

                <div v-if="hasStockProducts" class="mt-4 bg-gray-50 border border-gray-200 p-3 rounded">
                    <AppCheckbox v-model:checked="form.reserve_stock" label="Reservasi stok ketika order dikonfirmasi" />
                    <p class="text-xs text-gray-500 mt-1">
                        Saat diaktifkan, stok akan otomatis disisihkan setelah Sales Order dikonfirmasi.
                    </p>
                </div>

                <div class="mt-4">
                    <AppTextarea
                        v-model="form.notes"
                        label="Catatan:"
                        rows="3"
                        placeholder="Instruksi tambahan untuk tim fulfillment atau pelanggan."
                        :error="form.errors?.notes"
                    />
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Sales Order</h3>
                <p class="mb-2">Sales Order adalah dokumen yang digunakan untuk mencatat pesanan pelanggan. Pastikan informasi yang dimasukkan akurat.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan yang sesuai</li>
                    <li>Pilih cabang yang sesuai</li>
                    <li>Pilih pelanggan yang akan menerima SO</li>
                    <li>Pilih mata uang dan kurs yang sesuai</li>
                    <li>Tanggal SO adalah tanggal pembuatan sales order</li>
                    <li>Estimasi kirim adalah perkiraan kapan barang akan dikirim</li>
                    <li>Channel menentukan harga yang digunakan dari daftar harga</li>
                    <li>Tambahkan baris untuk setiap item yang dipesan</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <h2 class="text-lg font-semibold">Baris Sales Order</h2>
            <p class="text-sm text-gray-500 mb-4">Lengkapi detail item termasuk kuantitas, satuan, harga, dan pajak.</p>

            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-48 lg:min-w-48 px-1.5 py-1.5">Produk</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Satuan</th>
                        <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Qty</th>
                        <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Harga Satuan</th>
                        <th class="border border-gray-300 text-sm min-w-16 px-1.5 py-1.5">Diskon (%)</th>
                        <th class="border border-gray-300 text-sm min-w-16 px-1.5 py-1.5">Pajak (%)</th>
                        <th v-if="hasStockProducts" class="border border-gray-300 text-sm min-w-32 px-1.5 py-1.5">Tgl Kirim</th>
                        <th v-if="hasStockProducts" class="border border-gray-300 text-sm min-w-32 px-1.5 py-1.5">Lokasi</th>
                        <th v-if="hasBookingProducts" class="border border-gray-300 text-sm min-w-32 px-1.5 py-1.5">Mulai</th>
                        <th v-if="hasBookingProducts" class="border border-gray-300 text-sm min-w-32 px-1.5 py-1.5">Selesai</th>
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
                                @update:modelValue="handleProductChange(line, $event, index)"
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
                                @update:modelValue="syncVariant(line, index)"
                                :margins="{ top: 2, right: 0, bottom: 0, left: 0 }"
                            />
                            <p class="text-xs text-gray-500 mt-1">{{ availabilityLabel(index) }}</p>
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppSelect
                                v-model="line.uom_id"
                                :options="lineUomOptions(line).map((uom) => ({ value: uom.id, label: `${uom.code}`, description: `${uom.name}` }))"
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
                                v-model="line.discount_rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                placeholder="0"
                                :error="form.errors?.[`lines.${index}.discount_rate`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
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
                        <td v-if="hasStockProducts" class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppInput
                                v-if="!isBookingProduct(line)"
                                v-model="line.requested_delivery_date"
                                type="date"
                                :error="form.errors?.[`lines.${index}.requested_delivery_date`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td v-if="hasStockProducts" class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppSelect
                                v-if="!isBookingProduct(line)"
                                v-model="line.reservation_location_id"
                                :options="filteredLocations.map((loc) => ({ value: loc.id, label: `${loc.code} — ${loc.name}` }))"
                                placeholder="Pilih lokasi"
                                :error="form.errors?.[`lines.${index}.reservation_location_id`]"
                                :required="form.reserve_stock"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td v-if="hasBookingProducts" class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppInput
                                v-if="isBookingProduct(line)"
                                v-model="line.start_date"
                                type="datetime-local"
                                :error="form.errors?.[`lines.${index}.start_date`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                @blur="fetchBookingAvailability(index)"
                            />
                        </td>
                        <td v-if="hasBookingProducts" class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppInput
                                v-if="isBookingProduct(line)"
                                v-model="line.end_date"
                                type="datetime-local"
                                :error="form.errors?.[`lines.${index}.end_date`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                @blur="fetchBookingAvailability(index)"
                            />
                            <div v-if="isBookingProduct(line) && bookingAvailabilityLabel(index)" class="text-xs mt-1" :class="bookingAvailabilityClass(index)">
                                {{ bookingAvailabilityLabel(index) }}
                            </div>
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
                        <th class="border border-gray-300 px-4 py-2 text-right" :colspan="6 + (hasStockProducts ? 2 : 0) + (hasBookingProducts ? 2 : 0)">Total</th>
                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(totals.subtotal) }}</th>
                        <th class="border border-gray-300 px-4 py-2"></th>
                    </tr>
                    <tr class="text-sm">
                        <th class="border border-gray-300 px-4 py-2 text-right" :colspan="6 + (hasStockProducts ? 2 : 0) + (hasBookingProducts ? 2 : 0)">Total Pajak</th>
                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(totals.tax) }}</th>
                        <th class="border border-gray-300 px-4 py-2"></th>
                    </tr>
                    <tr class="text-sm font-semibold">
                        <th class="border border-gray-300 px-4 py-2 text-right" :colspan="6 + (hasStockProducts ? 2 : 0) + (hasBookingProducts ? 2 : 0)">Grand Total</th>
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

        <!-- Direct Costs Section -->
        <div class="mt-6 border-t pt-4">
            <h3 class="text-lg font-semibold mb-3">Biaya Langsung</h3>
            <table v-if="form.costs.length > 0" class="min-w-full bg-white border border-gray-300 text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-2 py-1.5 min-w-40">Biaya</th>
                        <th class="border border-gray-300 px-2 py-1.5 min-w-48">Catatan</th>
                        <th class="border border-gray-300 px-2 py-1.5 min-w-28">Jumlah</th>
                        <th class="border border-gray-300 px-2 py-1.5 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(cost, index) in form.costs" :key="index">
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppSelect
                                v-model="cost.cost_item_id"
                                :options="costItems.filter(i => !selectedCompany || i.company_id === selectedCompany).map(i => ({ value: i.id, label: `${i.code} - ${i.name}` }))"
                                placeholder="Pilih Biaya"
                                :error="form.errors?.[`costs.${index}.cost_item_id`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="cost.description"
                                placeholder="Catatan (opsional)"
                                :error="form.errors?.[`costs.${index}.description`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="cost.amount"
                                :numberFormat="true"
                                :error="form.errors?.[`costs.${index}.amount`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-center">
                            <button type="button" @click="removeCost(index)" class="text-red-500 hover:text-red-700">
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="flex mt-2">
                <button type="button" @click="addCost" class="flex items-center text-main-500 hover:text-main-700">
                    <PlusCircleIcon class="w-5 h-5 mr-1" /> Tambah Biaya
                </button>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="form.processing">
                {{ submitLabel }}
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.salesOrder" type="button" @click="submitForm(true)" class="mr-2">
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('sales-orders.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
