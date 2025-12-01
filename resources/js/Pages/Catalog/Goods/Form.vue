<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    mode: String,
    product: Object,
    categories: Array,
    uoms: Array,
    taxCategories: Array,
});

const form = useForm({
    code: props.product?.code ?? '',
    name: props.product?.name ?? '',
    product_category_id: props.product?.product_category_id ?? null,
    default_uom_id: props.product?.default_uom_id ?? null,
    tax_category_id: props.product?.tax_category_id ?? null,
    is_active: props.product?.is_active ?? true,
    attributes: props.product?.attrs_json ?? {},
    capabilities: ['variantable','inventory_tracked'],
});

function submit() {
    if (props.mode === 'edit') {
        form.put(route('catalog.goods.update', props.product.id));
    } else {
        form.post(route('catalog.goods.store'));
    }
}
</script>

<template>
    <Head :title="props.mode === 'edit' ? 'Edit Goods' : 'Create Goods'" />
    <AuthenticatedLayout>
        <template #header>
            <h2>{{ props.mode === 'edit' ? 'Edit Goods' : 'Create Goods' }}</h2>
        </template>

        <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4">
            <form @submit.prevent="submit" class="space-y-4 max-w-2xl">
                <div class="grid grid-cols-2 gap-4">
                    <AppInput v-model="form.code" label="Code" :error="form.errors.code" required />
                    <AppInput v-model="form.name" label="Name" :error="form.errors.name" required />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.product_category_id"
                        :options="categories.map(c => ({ value: c.id, label: c.name }))"
                        label="Category"
                        :error="form.errors.product_category_id"
                        placeholder="Select category"
                    />
                    <AppSelect
                        v-model="form.default_uom_id"
                        :options="uoms.map(u => ({ value: u.id, label: `${u.code} - ${u.name}` }))"
                        label="Default UOM"
                        :error="form.errors.default_uom_id"
                        placeholder="Select UOM"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.tax_category_id"
                        :options="taxCategories.map(t => ({ value: t.id, label: t.name }))"
                        label="Tax Category"
                        :error="form.errors.tax_category_id"
                        placeholder="Select Tax Category"
                    />
                    <div class="flex items-center mt-6">
                        <input id="is_active" v-model="form.is_active" type="checkbox" class="mr-2">
                        <label for="is_active">Active</label>
                    </div>
                </div>

                <div class="flex items-center">
                    <AppPrimaryButton type="submit" class="mr-2">Save</AppPrimaryButton>
                    <AppSecondaryButton :href="route('catalog.goods.index')" as="a">Cancel</AppSecondaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>


