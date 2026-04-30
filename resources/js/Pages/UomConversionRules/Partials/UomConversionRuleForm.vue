<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    rule: Object,
    uoms: Array,
    companies: Array,
    methods: Array,
    contexts: Array,
    roundingModes: Array,
    filters: Object,
});

const form = useForm({
    from_uom_id: props.rule?.from_uom_id ?? null,
    to_uom_id: props.rule?.to_uom_id ?? null,
    method: props.rule?.method ?? 'fixed_ratio',
    numerator: props.rule?.numerator ?? null,
    denominator: props.rule?.denominator ?? null,
    avg_weight_g: props.rule?.avg_weight_g ?? null,
    density_kg_per_l: props.rule?.density_kg_per_l ?? null,
    product_id: props.rule?.product_id ?? null,
    variant_id: props.rule?.variant_id ?? null,
    company_id: props.rule?.company_id ?? null,
    partner_id: props.rule?.partner_id ?? null,
    context: props.rule?.context ?? null,
    rounding_mode: props.rule?.rounding_mode ?? 'nearest',
    decimal_places: props.rule?.decimal_places ?? 3,
    effective_from: props.rule?.effective_from ?? null,
    effective_to: props.rule?.effective_to ?? null,
    notes: props.rule?.notes ?? '',
    create_another: false,
});

const fromUom = computed(() => props.uoms.find((u) => u.id === form.from_uom_id) ?? null);
const toUom = computed(() => props.uoms.find((u) => u.id === form.to_uom_id) ?? null);

const uomOptions = computed(() => props.uoms.map((u) => ({
    value: u.id,
    label: `${u.code} — ${u.name} (${u.kind})`,
})));

const companyOptions = computed(() => [
    { value: null, label: '— semua perusahaan —' },
    ...props.companies.map((c) => ({ value: c.id, label: c.name })),
]);

const contextOptions = computed(() => [
    { value: null, label: '— semua konteks —' },
    ...(props.contexts || []),
]);

// Product popover (search)
const productSearchUrl = computed(() => route('api.products'));
const productTableHeaders = [
    { key: 'name', label: 'Nama Produk' },
    { key: 'category.name', label: 'Kategori' },
    { key: 'uom_code', label: 'UOM' },
    { key: 'actions', label: '' },
];
const productDisplay = ref(props.rule?.product?.name ?? '');

// Track variants for the selected product
const variantOptions = ref([]);
async function loadVariantsForProduct(productId) {
    if (!productId) {
        variantOptions.value = [];
        return;
    }
    try {
        const response = await axios.get(route('api.products.show', productId));
        const product = response.data;
        productDisplay.value = product.name;
        variantOptions.value = (product.variants || []).map((v) => ({
            value: v.id,
            label: v.sku || `Variant #${v.id}`,
        }));
    } catch {
        variantOptions.value = [];
    }
}

// Initial load if editing
if (props.rule?.product?.variants) {
    variantOptions.value = props.rule.product.variants.map((v) => ({
        value: v.id,
        label: v.sku || `Variant #${v.id}`,
    }));
}

watch(() => form.product_id, (id, oldId) => {
    if (id !== oldId) {
        if (id !== props.rule?.product_id) {
            form.variant_id = null;
        }
        loadVariantsForProduct(id);
    }
});

// Partner popover
const partnerSearchUrl = computed(() => route('api.partners'));
const partnerTableHeaders = [
    { key: 'name', label: 'Nama Partner' },
    { key: 'code', label: 'Kode' },
    { key: 'actions', label: '' },
];
const partnerDisplay = ref(props.rule?.partner?.name ?? '');

const previewText = computed(() => {
    if (!fromUom.value || !toUom.value) return '';
    if (form.method === 'fixed_ratio') {
        const num = Number(form.numerator) || 0;
        const den = Number(form.denominator) || 0;
        if (num <= 0 || den <= 0) return '';
        const f = num / den;
        return `1 ${fromUom.value.code} = ${f.toLocaleString('id-ID', { maximumFractionDigits: 6 })} ${toUom.value.code}`;
    }
    if (form.method === 'avg_weight') {
        const w = Number(form.avg_weight_g) || 0;
        if (w <= 0) return '';
        return `1 ${fromUom.value.code} ≈ ${w} gram (${(w / 1000).toLocaleString('id-ID', { maximumFractionDigits: 6 })} kg)`;
    }
    if (form.method === 'density') {
        const d = Number(form.density_kg_per_l) || 0;
        if (d <= 0) return '';
        return `1 liter = ${d} kg`;
    }
    return '';
});

function resetForm() {
    form.reset();
    form.clearErrors();
    productDisplay.value = '';
    partnerDisplay.value = '';
}

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    if (props.rule) {
        form.put(route('uom-conversion-rules.update', props.rule.id), { preserveScroll: true });
    } else {
        form.post(route('uom-conversion-rules.store'), {
            preserveScroll: true,
            onSuccess: () => { if (createAnother) resetForm(); },
        });
    }
}
</script>

<template>
    <div class="flex justify-between">
        <form @submit.prevent="submitForm(false)" class="w-2/3 max-w-2xl mr-8 space-y-3">
            <!-- UoM pair -->
            <div class="grid grid-cols-2 gap-3">
                <AppSelect
                    v-model="form.from_uom_id"
                    label="Dari Satuan:"
                    :options="uomOptions"
                    placeholder="Pilih satuan asal"
                    :error="form.errors.from_uom_id"
                    required
                />
                <AppSelect
                    v-model="form.to_uom_id"
                    label="Ke Satuan:"
                    :options="uomOptions"
                    placeholder="Pilih satuan tujuan"
                    :error="form.errors.to_uom_id"
                    required
                />
            </div>

            <!-- Method -->
            <AppSelect
                v-model="form.method"
                label="Metode:"
                :options="methods"
                :error="form.errors.method"
                required
            />

            <!-- Method-specific inputs -->
            <div v-if="form.method === 'fixed_ratio'" class="grid grid-cols-2 gap-3">
                <AppInput
                    v-model="form.numerator"
                    label="Pembilang:"
                    type="number"
                    step="any"
                    :error="form.errors.numerator"
                    required
                />
                <AppInput
                    v-model="form.denominator"
                    label="Penyebut:"
                    type="number"
                    step="any"
                    :error="form.errors.denominator"
                    required
                />
            </div>
            <div v-else-if="form.method === 'avg_weight'">
                <AppInput
                    v-model="form.avg_weight_g"
                    label="Berat Rata-rata (gram per unit):"
                    type="number"
                    step="any"
                    placeholder="mis. 200 untuk 1 buah ≈ 200g"
                    :error="form.errors.avg_weight_g"
                    required
                />
            </div>
            <div v-else-if="form.method === 'density'">
                <AppInput
                    v-model="form.density_kg_per_l"
                    label="Densitas (kg per liter):"
                    type="number"
                    step="any"
                    placeholder="mis. 0.92 untuk minyak goreng"
                    :error="form.errors.density_kg_per_l"
                    required
                />
            </div>

            <p v-if="previewText" class="text-sm text-gray-700 bg-blue-50 border border-blue-200 px-3 py-2 rounded">
                {{ previewText }}
            </p>

            <!-- Scoping -->
            <h3 class="text-sm font-semibold text-gray-700 pt-2 border-t">Cakupan (kosongkan untuk berlaku umum)</h3>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Produk:</label>
                    <AppPopoverSearch
                        v-model="form.product_id"
                        :url="productSearchUrl"
                        :tableHeaders="productTableHeaders"
                        :displayKeys="['name']"
                        :initialDisplayValue="productDisplay"
                        placeholder="— semua produk —"
                        modalTitle="Pilih Produk"
                        :error="form.errors.product_id"
                    />
                </div>
                <AppSelect
                    v-model="form.variant_id"
                    label="Varian:"
                    :options="[{ value: null, label: '— semua varian —' }, ...variantOptions]"
                    :placeholder="form.product_id ? 'Pilih varian' : 'Pilih produk dulu'"
                    :disabled="!form.product_id"
                    :error="form.errors.variant_id"
                />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <AppSelect
                    v-model="form.company_id"
                    label="Perusahaan:"
                    :options="companyOptions"
                    placeholder="— semua perusahaan —"
                    :error="form.errors.company_id"
                />
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Partner:</label>
                    <AppPopoverSearch
                        v-model="form.partner_id"
                        :url="partnerSearchUrl"
                        :tableHeaders="partnerTableHeaders"
                        :displayKeys="['name']"
                        :initialDisplayValue="partnerDisplay"
                        placeholder="— semua partner —"
                        modalTitle="Pilih Partner"
                        :error="form.errors.partner_id"
                    />
                </div>
            </div>

            <AppSelect
                v-model="form.context"
                label="Konteks:"
                :options="contextOptions"
                placeholder="— semua konteks —"
                :error="form.errors.context"
            />

            <!-- Effective period -->
            <h3 class="text-sm font-semibold text-gray-700 pt-2 border-t">Periode Berlaku (opsional)</h3>
            <div class="grid grid-cols-2 gap-3">
                <AppInput
                    v-model="form.effective_from"
                    label="Berlaku Dari:"
                    type="date"
                    :error="form.errors.effective_from"
                />
                <AppInput
                    v-model="form.effective_to"
                    label="Sampai:"
                    type="date"
                    :error="form.errors.effective_to"
                />
            </div>

            <!-- Rounding -->
            <h3 class="text-sm font-semibold text-gray-700 pt-2 border-t">Pembulatan</h3>
            <div class="grid grid-cols-2 gap-3">
                <AppSelect
                    v-model="form.rounding_mode"
                    label="Mode Pembulatan:"
                    :options="roundingModes"
                    :error="form.errors.rounding_mode"
                    required
                />
                <AppInput
                    v-model="form.decimal_places"
                    label="Jumlah Desimal:"
                    type="number"
                    min="0"
                    max="9"
                    :error="form.errors.decimal_places"
                    required
                />
            </div>

            <AppTextarea
                v-model="form.notes"
                label="Catatan:"
                :error="form.errors.notes"
            />

            <div class="flex items-center pt-2">
                <AppPrimaryButton type="submit" class="mr-2">
                    {{ rule ? 'Ubah' : 'Tambah' }} Aturan
                </AppPrimaryButton>
                <AppUtilityButton v-if="!rule" type="button" @click="submitForm(true)" class="mr-2">
                    Tambah & Buat Lagi
                </AppUtilityButton>
                <AppSecondaryButton @click="$inertia.visit(route('uom-conversion-rules.index', filters))">
                    Batal
                </AppSecondaryButton>
            </div>
        </form>

        <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm h-fit">
            <h3 class="text-lg font-semibold mb-2">Aturan Konversi Spesifik</h3>
            <p class="mb-2">Konfigurasikan konversi yang berlaku hanya pada cakupan tertentu — misalnya 1 kardus = 24 pcs khusus untuk Produk A pada konteks pembelian.</p>
            <ul class="list-disc list-inside space-y-1">
                <li><strong>Rasio Tetap:</strong> konversi seperti tabel Konversi Satuan tapi terbatas cakupan tertentu.</li>
                <li><strong>Berat Rata-rata:</strong> mis. 1 buah apel ≈ 200g — untuk konversi pcs ke kg.</li>
                <li><strong>Densitas:</strong> mis. minyak goreng 0.92 kg/L — untuk konversi liter ke kg.</li>
                <li>Cakupan kosong = berlaku umum, tetapi aturan dengan cakupan lebih spesifik akan diutamakan.</li>
            </ul>
        </div>
    </div>
</template>
