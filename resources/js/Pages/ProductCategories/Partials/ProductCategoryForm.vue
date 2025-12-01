<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    category: Object,
    companies: Array,
    attributeSets: Array,
    parentCategories: Array,
    filters: Object,
});

const form = useForm({
    company_id: props.category?.company_id ?? '',
    attribute_set_id: props.category?.attribute_set_id ?? '',
    parent_id: props.category?.parent_id ?? null,
    code: props.category?.code ?? '',
    name: props.category?.name ?? '',
    sort_order: props.category?.sort_order ?? 0,
    path: props.category?.path ?? '',
    create_another: false,
});

const submitted = ref(false);

const companyOptions = computed(() => props.companies?.map(company => ({
    value: company.id,
    label: company.name,
})) ?? []);

const attributeSetOptions = computed(() => props.attributeSets?.map(set => ({
    value: set.id,
    label: set.name,
})) ?? []);

const parentSource = computed(() => props.parentCategories?.map(category => ({
    value: category.id,
    label: category.name,
    company_id: category.company_id,
})) ?? []);

const filteredParentOptions = computed(() => {
    if (!form.company_id) {
        return parentSource.value;
    }
    return parentSource.value.filter(option => Number(option.company_id) === Number(form.company_id));
});

watch(() => form.company_id, () => {
    if (!filteredParentOptions.value.some(option => option.value === form.parent_id)) {
        form.parent_id = null;
    }
});

function submitForm() {
    submitted.value = true;
    if (props.category) {
        form.put(route('catalog.product-categories.update', props.category.id), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
            },
            onError: () => {
                submitted.value = false;
            },
        });
    } else {
        form.post(route('catalog.product-categories.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (form.create_another) {
                    form.reset();
                    form.clearErrors();
                    form.create_another = false;
                }
            },
            onError: () => {
                submitted.value = false;
            },
        });
    }
}

function submitAndCreateAnother() {
    form.create_another = true;
    submitForm();
}
</script>

<template>
    <div class="flex flex-col lg:flex-row gap-8">
        <form @submit.prevent="submitForm" class="w-full lg:w-2/3 max-w-2xl">
            <AppSelect
                v-model="form.company_id"
                :options="companyOptions"
                label="Perusahaan"
                :error="form.errors.company_id"
                placeholder="Pilih perusahaan"
                required
            />

            <AppSelect
                v-model="form.attribute_set_id"
                :options="attributeSetOptions"
                label="Set Atribut"
                :error="form.errors.attribute_set_id"
                placeholder="Pilih set atribut"
                required
            />

            <AppSelect
                v-model="form.parent_id"
                :options="filteredParentOptions"
                label="Kategori Induk"
                :error="form.errors.parent_id"
                placeholder="(Opsional)"
                :disabled="!form.company_id || filteredParentOptions.length === 0"
            />

            <AppInput
                v-model="form.code"
                label="Kode Kategori"
                :error="form.errors.code"
                placeholder="Contoh: CAT-001"
                required
            />

            <AppInput
                v-model="form.name"
                label="Nama Kategori"
                :error="form.errors.name"
                placeholder="Nama kategori"
                required
            />

            <AppInput
                v-model="form.sort_order"
                label="Urutan"
                type="number"
                min="0"
                :error="form.errors.sort_order"
                placeholder="0"
            />

            <div class="mt-6 flex items-center flex-wrap gap-2">
                <AppPrimaryButton type="submit" :disabled="submitted">
                    {{ props.category ? 'Simpan Perubahan' : 'Tambah Kategori' }}
                </AppPrimaryButton>
                <AppUtilityButton
                    v-if="!props.category"
                    type="button"
                    @click="submitAndCreateAnother"
                    :disabled="submitted"
                >
                    Tambah &amp; Buat Lagi
                </AppUtilityButton>
                <AppSecondaryButton type="button" @click="$inertia.visit(route('catalog.product-categories.index', filters))">
                    Batal
                </AppSecondaryButton>
            </div>
        </form>

        <div class="w-full lg:w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Tips Kategori Produk</h3>
            <ul class="list-disc list-inside space-y-1">
                <li>Gunakan kode unik untuk setiap kategori.</li>
                <li>Set atribut menentukan atribut produk yang tersedia.</li>
                <li>Pastikan kategori induk berada dalam perusahaan yang sama.</li>
                <li>Path akan dibuat otomatis jika dibiarkan kosong.</li>
            </ul>
        </div>
    </div>
</template>

