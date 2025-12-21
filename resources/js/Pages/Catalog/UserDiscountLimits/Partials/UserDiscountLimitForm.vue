<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    limit: Object,
    users: Array,
    products: Array,
    categories: Array,
    filters: Object,
});

const getScopeType = () => {
    if (props.limit?.product_id) return 'product';
    if (props.limit?.product_category_id) return 'category';
    return 'global';
};

const form = useForm({
    user_global_id: props.limit?.user_global_id || null,
    scope_type: getScopeType(),
    product_id: props.limit?.product_id || null,
    product_category_id: props.limit?.product_category_id || null,
    max_discount_percent: props.limit?.max_discount_percent || 0,
    is_active: props.limit?.is_active ?? true,
});

const submitted = ref(false);

const userOptions = computed(() => props.users.map(u => ({ value: u.global_id, label: `${u.name} (${u.email})` })));
const productOptions = computed(() => [{ value: null, label: 'Pilih Produk' }, ...props.products.map(p => ({ value: p.id, label: `${p.name} (${p.code})` }))]);
const categoryOptions = computed(() => [{ value: null, label: 'Pilih Kategori' }, ...props.categories.map(c => ({ value: c.id, label: c.name }))]);

const scopeTypeOptions = [
    { value: 'global', label: 'Global (berlaku untuk semua produk)' },
    { value: 'category', label: 'Kategori (berlaku untuk produk dalam kategori)' },
    { value: 'product', label: 'Produk (berlaku untuk produk tertentu)' },
];

// Clear irrelevant scope fields when scope type changes
watch(() => form.scope_type, (newVal) => {
    if (newVal === 'product') {
        form.product_category_id = null;
    } else if (newVal === 'category') {
        form.product_id = null;
    } else {
        form.product_id = null;
        form.product_category_id = null;
    }
});

function submitForm() {
    submitted.value = true;
    
    const payload = {
        user_global_id: form.user_global_id,
        max_discount_percent: form.max_discount_percent,
        is_active: form.is_active,
        product_id: form.scope_type === 'product' ? form.product_id : null,
        product_category_id: form.scope_type === 'category' ? form.product_category_id : null,
    };
    
    if (props.limit) {
        form.transform(() => payload).put(route('catalog.user-discount-limits.update', props.limit.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; }
        });
    } else {
        form.transform(() => payload).post(route('catalog.user-discount-limits.store'), {
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
                    v-model="form.user_global_id"
                    :options="userOptions"
                    label="Pengguna:"
                    :error="form.errors.user_global_id"
                    required
                    placeholder="Pilih pengguna"
                />
                
                <AppSelect
                    v-model="form.scope_type"
                    :options="scopeTypeOptions"
                    label="Tipe Cakupan:"
                    :error="form.errors.scope_type"
                />

                <AppSelect
                    v-if="form.scope_type === 'product'"
                    v-model="form.product_id"
                    :options="productOptions"
                    label="Produk:"
                    :error="form.errors.product_id"
                    required
                    placeholder="Pilih produk"
                />

                <AppSelect
                    v-if="form.scope_type === 'category'"
                    v-model="form.product_category_id"
                    :options="categoryOptions"
                    label="Kategori Produk:"
                    :error="form.errors.product_category_id"
                    required
                    placeholder="Pilih kategori"
                />

                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.max_discount_percent"
                        label="Maksimal Diskon (%):"
                        :error="form.errors.max_discount_percent"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        required
                    />
                    
                    <div class="flex items-center mt-6">
                        <AppCheckbox
                            v-model:checked="form.is_active"
                            label="Aktif"
                        />
                    </div>
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi</h3>
                <p class="mb-2">Batas diskon menentukan maksimal persentase diskon yang dapat diberikan oleh pengguna.</p>
                <ul class="list-disc list-inside">
                    <li><b>Global:</b> Berlaku untuk semua produk</li>
                    <li><b>Kategori:</b> Berlaku untuk produk dalam kategori tertentu</li>
                    <li><b>Produk:</b> Berlaku untuk produk tertentu saja</li>
                </ul>
                <p class="mt-2 text-gray-600">Resolusi dimulai dari yang paling spesifik (Produk → Kategori → Global).</p>
            </div>
        </div>
        
        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ props.limit ? 'Ubah' : 'Tambah' }} Batas Diskon
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('catalog.user-discount-limits.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
