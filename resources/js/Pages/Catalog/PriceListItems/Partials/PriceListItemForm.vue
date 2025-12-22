<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    item: Object,
    priceLists: Array,
    products: Array,
    variants: Array,
    uoms: Array,
    filters: Object,
});

const form = useForm({
    price_list_id: props.item?.price_list_id || null,
    product_id: props.item?.product_id || null,
    product_variant_id: props.item?.product_variant_id || null,
    uom_id: props.item?.uom_id || null,
    min_qty: props.item?.min_qty || 1,
    price: props.item?.price || 0,
    tax_included: props.item?.tax_included ?? false,
});

const submitted = ref(false);
const availableVariants = ref(props.variants || []);

const priceListOptions = computed(() => props.priceLists.map(p => ({ value: p.id, label: `${p.name} (${p.code})` })));
const productOptions = computed(() => props.products.map(p => ({ value: p.id, label: `${p.name} (${p.code})` })));
const variantOptions = computed(() => [
    { value: null, label: 'Tidak ada varian' },
    ...availableVariants.value.map(v => ({ value: v.id, label: v.sku }))
]);
const uomOptions = computed(() => props.uoms.map(u => ({ value: u.id, label: `${u.name} (${u.code})` })));

// Fetch variants when product changes
watch(() => form.product_id, async (newProductId) => {
    if (!newProductId) {
        availableVariants.value = [];
        form.product_variant_id = null;
        return;
    }

    try {
        const response = await fetch(route('api.price-list-items.variants', { product_id: newProductId }));
        availableVariants.value = await response.json();
        
        // Reset variant if not in new list
        if (!availableVariants.value.find(v => v.id === form.product_variant_id)) {
            form.product_variant_id = null;
        }
    } catch (error) {
        console.error('Failed to fetch variants:', error);
        availableVariants.value = [];
    }
});

function submitForm() {
    submitted.value = true;
    
    if (props.item) {
        form.put(route('catalog.price-list-items.update', props.item.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; }
        });
    } else {
        form.post(route('catalog.price-list-items.store'), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; }
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <AppSelect
                    v-model="form.price_list_id"
                    :options="priceListOptions"
                    label="Daftar Harga:"
                    :error="form.errors.price_list_id"
                    required
                    placeholder="Pilih daftar harga"
                />

                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.product_id"
                        :options="productOptions"
                        label="Produk:"
                        :error="form.errors.product_id"
                        required
                        placeholder="Pilih produk"
                    />
                    
                    <AppSelect
                        v-model="form.product_variant_id"
                        :options="variantOptions"
                        label="Varian (opsional):"
                        :error="form.errors.product_variant_id"
                        :disabled="availableVariants.length === 0"
                    />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <AppSelect
                        v-model="form.uom_id"
                        :options="uomOptions"
                        label="UOM:"
                        :error="form.errors.uom_id"
                        required
                        placeholder="Pilih UOM"
                    />
                    
                    <AppInput
                        v-model="form.min_qty"
                        label="Min Qty:"
                        :error="form.errors.min_qty"
                        type="number"
                        step="0.001"
                        min="0"
                        required
                    />
                    
                    <AppInput
                        v-model="form.price"
                        label="Harga:"
                        :error="form.errors.price"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                    />
                </div>
                
                <div class="mt-4">
                    <AppCheckbox
                        v-model:checked="form.tax_included"
                        label="Harga termasuk pajak"
                    />
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi</h3>
                <p class="mb-2">Item harga menentukan harga spesifik untuk produk dalam daftar harga.</p>
                <ul class="list-disc list-inside">
                    <li><b>Min Qty:</b> Jumlah minimum untuk harga ini berlaku</li>
                    <li><b>Varian:</b> Opsional, untuk harga varian spesifik</li>
                    <li><b>Termasuk Pajak:</b> Apakah harga sudah termasuk pajak</li>
                </ul>
            </div>
        </div>
        
        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ props.item ? 'Simpan Perubahan' : 'Buat Harga' }}
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('catalog.price-list-items.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
