<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import DynamicAttributesForm from '@/Components/Catalog/DynamicAttributesForm.vue';

const props = defineProps({
    mode: String,
    product: Object,
    template: Object,
    templateCode: String,
    group: String,
    categories: Array,
    uoms: Array,
    taxCategories: Array,
    attributeSet: Object,
    companies: Array,
    accounts: Array,
    costPools: Array,
    rulesBundle: Object,
});

const form = useForm({
    template_code: props.templateCode ?? props.template?.template_code ?? '',
    code: props.product?.code ?? '',
    name: props.product?.name ?? '',
    product_category_id: props.product?.product_category_id ?? null,
    attribute_set_id: props.product?.attribute_set_id ?? props.attributeSet?.id ?? null,
    default_uom_id: props.product?.default_uom_id ?? null,
    tax_category_id: props.product?.tax_category_id ?? null,
    revenue_account_id: props.product?.revenue_account_id ?? null,
    cogs_account_id: props.product?.cogs_account_id ?? null,
    inventory_account_id: props.product?.inventory_account_id ?? null,
    prepaid_account_id: props.product?.prepaid_account_id ?? null,
    default_cost_pool_id: props.product?.default_cost_pool_id ?? null,
    cost_model: props.product?.cost_model ?? props.template?.cost_model ?? 'direct_expense_per_sale',
    is_active: props.product?.is_active ?? true,
    attributes: props.product?.attrs_json ?? {},
    capabilities: props.product?.capabilities?.map(c => c.capability) ?? props.template?.capabilities ?? [],
    company_ids: props.product?.companies?.map(company => company.id) ?? [],
});

function submit() {
    if (props.mode === 'edit') {
        form.put(route('catalog.products.update', props.product.id));
    } else {
        form.post(route('catalog.products.store'));
    }
}

function onCategoryChange() {
    const selected = props.categories.find(c => c.id === form.product_category_id);
    if (selected?.attribute_set_id) {
        form.attribute_set_id = selected.attribute_set_id;
    }
}

const currentDefs = computed(() => {
    return props.attributeSet?.attributes ?? [];
});

// Check if template requires certain capabilities
const showInventoryFields = computed(() => {
    return form.capabilities.includes('inventory_tracked');
});

const showBookingFields = computed(() => {
    return form.capabilities.includes('bookable');
});

const showRentalFields = computed(() => {
    return form.capabilities.includes('rental');
});

const showPrepaidFields = computed(() => {
    return props.template?.kind?.includes('_resale') || props.template?.cost_model === 'prepaid_consumption';
});

const showCostPoolField = computed(() => {
    // Show cost pool field only for non-inventory cost models
    return form.cost_model && form.cost_model !== 'inventory_layer';
});

const pageTitle = computed(() => {
    if (props.mode === 'edit') {
        return `Edit: ${props.product?.name ?? 'Produk'}`;
    }
    return `Buat ${props.template?.label ?? 'Produk'}`;
});

const costModelOptions = computed(() => {
    return (props.rulesBundle?.cost_models ?? []).map(m => ({
        value: m,
        label: formatCostModel(m),
    }));
});

function formatCostModel(model) {
    const labels = {
        'inventory_layer': 'Inventory Layer (FIFO/LIFO/Average)',
        'direct_expense_per_sale': 'Direct Expense per Sale',
        'job_costing': 'Job Costing',
        'asset_usage_costing': 'Asset Usage Costing',
        'prepaid_consumption': 'Prepaid/Deposit Consumption',
        'hybrid': 'Hybrid (Mixed Components)',
        'none': 'None (No COGS)',
    };
    return labels[model] || model;
}

const capabilityOptions = computed(() => {
    return (props.rulesBundle?.capabilities ?? []).map(c => ({
        value: c,
        label: formatCapability(c),
        disabled: isCapabilityDisabled(c),
    }));
});

function formatCapability(cap) {
    const labels = {
        'inventory_tracked': 'Inventori Terlacak',
        'variantable': 'Mendukung Varian',
        'bookable': 'Dapat di-Booking',
        'rental': 'Rental / Sewa',
        'serialized': 'Serial Number',
        'package': 'Paket/Bundle',
    };
    return labels[cap] || cap;
}

function isCapabilityDisabled(cap) {
    const matrix = props.rulesBundle?.capability_matrix?.[props.template?.kind] ?? {};
    return matrix[cap] === 'forbidden';
}
</script>

<template>
    <Head :title="pageTitle" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2>{{ pageTitle }}</h2>
                <Link
                    :href="route('catalog.products.index', { group })"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    &larr; Kembali ke Daftar
                </Link>
            </div>
        </template>

        <div class="mx-auto">
            <!-- Template info -->
            <div v-if="template" class="mb-4 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-indigo-900">{{ template.label }}</span>
                        <span class="text-sm text-indigo-700 ml-2">{{ template.description }}</span>
                    </div>
                    <div class="flex gap-2">
                        <span
                            v-for="cap in template.capabilities"
                            :key="cap"
                            class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-800 rounded"
                        >
                            {{ formatCapability(cap) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Info Section -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Dasar</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <AppInput v-model="form.code" label="Kode" :error="form.errors.code" required />
                            <AppInput v-model="form.name" label="Nama" :error="form.errors.name" required />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <AppSelect
                                v-model="form.product_category_id"
                                :options="categories.map(c => ({ value: c.id, label: c.name }))"
                                label="Kategori"
                                :error="form.errors.product_category_id"
                                placeholder="Pilih kategori"
                                @update:modelValue="onCategoryChange"
                            />
                            <AppSelect
                                v-if="showInventoryFields"
                                v-model="form.default_uom_id"
                                :options="uoms.map(u => ({ value: u.id, label: `${u.code} - ${u.name}` }))"
                                label="UOM Default"
                                :error="form.errors.default_uom_id"
                                placeholder="Pilih UOM"
                                required
                            />
                            <AppSelect
                                v-else
                                v-model="form.default_uom_id"
                                :options="uoms.map(u => ({ value: u.id, label: `${u.code} - ${u.name}` }))"
                                label="UOM Default"
                                :error="form.errors.default_uom_id"
                                placeholder="Pilih UOM"
                            />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <AppSelect
                                v-model="form.tax_category_id"
                                :options="taxCategories.map(t => ({ value: t.id, label: t.name }))"
                                label="Kategori Pajak"
                                :error="form.errors.tax_category_id"
                                placeholder="Pilih Kategori Pajak"
                            />
                            <AppSelect
                                v-model="form.cost_model"
                                :options="costModelOptions"
                                label="Cost Model"
                                :error="form.errors.cost_model"
                            />
                        </div>
                    </div>

                    <!-- Companies Section -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Perusahaan</h3>
                        <AppSelect
                            v-model="form.company_ids"
                            :options="companies.map(company => ({ value: company.id, label: company.name }))"
                            label="Pilih Perusahaan"
                            :error="form.errors.company_ids"
                            placeholder="Pilih perusahaan..."
                            multiple
                        />
                    </div>

                    <!-- Accounts Section -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Akun</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <AppSelect
                                v-model="form.revenue_account_id"
                                :options="accounts.map(a => ({ value: a.id, label: `${a.code} - ${a.name}` }))"
                                label="Akun Pendapatan"
                                :error="form.errors.revenue_account_id"
                                placeholder="Pilih akun pendapatan"
                            />
                            <AppSelect
                                v-model="form.cogs_account_id"
                                :options="accounts.map(a => ({ value: a.id, label: `${a.code} - ${a.name}` }))"
                                label="Akun HPP"
                                :error="form.errors.cogs_account_id"
                                placeholder="Pilih akun HPP"
                            />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <AppSelect
                                v-if="showInventoryFields"
                                v-model="form.inventory_account_id"
                                :options="accounts.map(a => ({ value: a.id, label: `${a.code} - ${a.name}` }))"
                                label="Akun Inventori"
                                :error="form.errors.inventory_account_id"
                                placeholder="Pilih akun inventori"
                            />
                            <AppSelect
                                v-if="showPrepaidFields"
                                v-model="form.prepaid_account_id"
                                :options="accounts.map(a => ({ value: a.id, label: `${a.code} - ${a.name}` }))"
                                label="Akun Prepaid/Deposit"
                                :error="form.errors.prepaid_account_id"
                                placeholder="Pilih akun prepaid"
                            />
                        </div>
                        <div v-if="showCostPoolField" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <AppSelect
                                v-model="form.default_cost_pool_id"
                                :options="(costPools ?? []).map(p => ({ value: p.id, label: `${p.code} - ${p.name}` }))"
                                label="Cost Pool Default"
                                :error="form.errors.default_cost_pool_id"
                                placeholder="Pilih cost pool untuk alokasi biaya"
                            />
                        </div>
                    </div>

                    <!-- Capabilities Section -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Kemampuan Produk</h3>
                        <div class="flex flex-wrap gap-4">
                            <label
                                v-for="cap in capabilityOptions"
                                :key="cap.value"
                                class="flex items-center space-x-2"
                                :class="{ 'opacity-50': cap.disabled }"
                            >
                                <input
                                    type="checkbox"
                                    :value="cap.value"
                                    v-model="form.capabilities"
                                    :disabled="cap.disabled"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-sm text-gray-700">{{ cap.label }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Attributes Section -->
                    <div v-if="currentDefs.length > 0">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Atribut</h3>
                        <DynamicAttributesForm
                            v-model="form.attributes"
                            :defs="currentDefs"
                            :errors="form.errors"
                        />
                    </div>

                    <!-- Status -->
                    <div class="flex items-center mt-4">
                        <input id="is_active" v-model="form.is_active" type="checkbox" class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="is_active" class="text-sm text-gray-700">Aktif</label>
                    </div>

                    <!-- Submit -->
                    <div class="flex items-center pt-4 border-t border-gray-200">
                        <AppPrimaryButton type="submit" :disabled="form.processing" class="mr-2">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </AppPrimaryButton>
                        <AppSecondaryButton :href="route('catalog.products.index', { group })" as="a">
                            Batal
                        </AppSecondaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
