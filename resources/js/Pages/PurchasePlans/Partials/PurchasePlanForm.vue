<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref, watch, onMounted, computed, reactive } from 'vue';
import axios from 'axios';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';

// Store convertible UOMs per line index
const lineConvertibleUoms = reactive({});

const props = defineProps({
    purchasePlan: Object,
    companies: Array,
    branches: Array,
    products: Array,
    uoms: Array,
    filters: Object,
    isEdit: {
        type: Boolean,
        default: false,
    },
});

const submitted = ref(false);
const selectedCompany = ref(
    props.purchasePlan?.branch?.branch_group?.company_id ||
    (props.companies?.length > 1 ? null : props.companies?.[0]?.id) ||
    null
);

const form = useForm({
    branch_id: props.purchasePlan?.branch_id || null,
    plan_date: props.purchasePlan?.plan_date?.slice(0, 10) || new Date().toISOString().split('T')[0],
    required_date: props.purchasePlan?.required_date?.slice(0, 10) || '',
    notes: props.purchasePlan?.notes || '',
    lines: props.purchasePlan?.lines?.map(line => ({
        id: line.id,
        product_id: line.product_id,
        product_variant_id: line.product_variant_id,
        uom_id: line.uom_id,
        planned_qty: line.planned_qty,
        required_date: line.required_date?.slice(0, 10) || '',
        description: line.description || '',
    })) || [createEmptyLine()],
    create_another: false,
});

const filteredBranches = computed(() => {
    if (!selectedCompany.value) return [];
    return props.branches.filter(b => b.company_id === selectedCompany.value);
});

// Product search URL for AppPopoverSearch
const productSearchUrl = computed(() => {
    return route('api.products', { company_id: selectedCompany.value });
});

// Product table headers for AppPopoverSearch modal
const productTableHeaders = [
    { key: 'name', label: 'Nama Produk' },
    { key: 'category.name', label: 'Kategori' },
    { key: 'uom_code', label: 'UOM' },
    { key: 'actions', label: '' }
];

watch(selectedCompany, (newValue, oldValue) => {
    if (newValue !== oldValue && !props.purchasePlan) {
        form.branch_id = null;
    }
});

watch(
    () => filteredBranches.value,
    (newBranches) => {
        if (!props.purchasePlan && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

onMounted(() => {
    selectedCompany.value = props.purchasePlan?.branch?.branch_group?.company_id ||
        (props.companies?.length > 1 ? null : props.companies?.[0]?.id) ||
        null;
    if (!props.purchasePlan && filteredBranches.value.length === 1) {
        form.branch_id = filteredBranches.value[0].id;
    }
});

function createEmptyLine() {
    return {
        product_id: null,
        product_variant_id: null,
        uom_id: null,
        planned_qty: 1,
        required_date: '',
        description: '',
    };
}

function addLine() {
    form.lines.push(createEmptyLine());
}

function removeLine(index) {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1);
    }
}

function getProductById(productId) {
    return props.products?.find(p => p.id === productId);
}

async function handleProductChange(line, productId) {
    const product = getProductById(productId);
    const lineIndex = form.lines.indexOf(line);
    
    line.product_id = productId;
    line.product_variant_id = null;
    line.description = product?.name || '';
    
    // Set the default UOM for the product
    if (product?.default_uom_id) {
        line.uom_id = product.default_uom_id;
        // Fetch convertible UOMs from the default UOM
        await fetchConvertibleUoms(lineIndex, product.default_uom_id, productId);
    } else {
        // No default UOM, clear the convertible UOMs (show all)
        delete lineConvertibleUoms[lineIndex];
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
        lineConvertibleUoms[lineIndex] = response.data;
    } catch (error) {
        console.error('Error fetching convertible UOMs:', error);
        // On error, don't restrict UOMs
        delete lineConvertibleUoms[lineIndex];
    }
}

function getUomOptionsForLine(lineIndex) {
    const convertibleUoms = lineConvertibleUoms[lineIndex];
    
    // If no convertible UOMs fetched (or product has no default UOM), show all UOMs
    if (!convertibleUoms || convertibleUoms.length === 0) {
        return props.uoms.map(u => ({ value: u.id, label: u.code, description: u.name }));
    }
    
    // Filter to only show convertible UOMs
    return convertibleUoms.map(u => ({ value: u.id, label: u.code, description: u.name }));
}

function getVariantsForProduct(productId) {
    const product = getProductById(productId);
    if (!product?.variants?.length) return [];
    return product.variants.map(v => ({
        value: v.id,
        label: v.sku || v.barcode || 'Default'
    }));
}

function syncVariant(line) {
    const product = getProductById(line.product_id);
    const variant = product?.variants?.find(v => v.id === line.product_variant_id);
    if (variant) {
        line.uom_id = variant.uom_id;
    }
}

function getProductDisplayValue(productId) {
    const product = getProductById(productId);
    return product ? product.name : '';
}

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    
    if (props.isEdit) {
        form.put(route('purchase-plans.update', props.purchasePlan.id), {
            preserveScroll: true,
            onSuccess: () => submitted.value = false,
            onError: () => submitted.value = false,
        });
    } else {
        form.post(route('purchase-plans.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            },
            onError: () => submitted.value = false,
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
                        :options="props.companies.map(c => ({ value: c.id, label: c.name }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        :disabled="!!props.purchasePlan"
                        required
                    />

                    <AppSelect
                        v-model="form.branch_id"
                        :options="filteredBranches.map(b => ({ value: b.id, label: b.name }))"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!!props.purchasePlan"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.plan_date"
                        type="date"
                        label="Tanggal Rencana:"
                        :error="form.errors.plan_date"
                        required
                    />

                    <AppInput
                        v-model="form.required_date"
                        type="date"
                        label="Tanggal Dibutuhkan:"
                        :error="form.errors.required_date"
                    />
                </div>

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                />
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Rencana Pembelian</h3>
                <p class="mb-2">Rencana Pembelian adalah dokumen perencanaan untuk pengadaan barang. Pastikan informasi yang dimasukkan akurat.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan dan cabang yang sesuai</li>
                    <li>Pilih tanggal rencana pembelian</li>
                    <li>Tanggal dibutuhkan adalah estimasi kapan barang diperlukan</li>
                    <li>Masukkan item yang akan dibeli beserta kuantitasnya</li>
                    <li>Setelah dikonfirmasi, rencana dapat digunakan saat membuat PO</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <h2 class="text-lg font-semibold">Item Rencana Pembelian</h2>
            <p class="text-sm text-gray-500 mb-4">Masukkan produk, kuantitas, dan satuan yang dibutuhkan.</p>

            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-48 lg:min-w-72 px-1.5 py-1.5">Produk</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Kuantitas</th>
                        <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">UOM</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Tgl Dibutuhkan</th>
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
                                :disabled="!selectedCompany"
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
                            <AppInput
                                v-model="line.planned_qty"
                                :numberFormat="true"
                                required
                                :error="form.errors?.[`lines.${index}.planned_qty`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppSelect
                                v-model="line.uom_id"
                                :options="getUomOptionsForLine(index)"
                                placeholder="Satuan"
                                :error="form.errors?.[`lines.${index}.uom_id`]"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 align-top">
                            <AppInput
                                v-model="line.required_date"
                                type="date"
                                :error="form.errors?.[`lines.${index}.required_date`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                            <button 
                                type="button" 
                                @click="removeLine(index)" 
                                class="text-red-500 hover:text-red-700"
                                :disabled="form.lines.length === 1"
                            >
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="flex mt-2 mb-4">
                <button type="button" @click="addLine" class="flex items-center text-main-500 hover:text-main-700">
                    <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Item
                </button>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="submitted">
                {{ props.isEdit ? 'Ubah' : 'Tambah' }} Rencana
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.isEdit" type="button" @click="submitForm(true)" class="mr-2" :disabled="submitted">
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('purchase-plans.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
