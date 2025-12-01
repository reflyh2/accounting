<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import DynamicAttributesForm from '@/Components/Catalog/DynamicAttributesForm.vue';

const props = defineProps({
    mode: String,
    product: Object,
    categories: Array,
    taxCategories: Array,
    attributeSets: Array,
    typeTemplate: Object,
    companies: Array,
});

const form = useForm({
    code: props.product?.code ?? '',
    name: props.product?.name ?? '',
    product_category_id: props.product?.product_category_id ?? null,
    attribute_set_id: props.product?.attribute_set_id ?? null,
    tax_category_id: props.product?.tax_category_id ?? null,
    is_active: props.product?.is_active ?? true,
    attributes: props.product?.attrs_json ?? {},
    capabilities: ['rental','serialized','bookable'],
    company_ids: props.product?.companies?.map(company => company.id) ?? [],
});

if (!form.attribute_set_id) {
    const cat = props.categories.find(c => c.id === form.product_category_id);
    form.attribute_set_id = cat?.attribute_set_id ?? props.attributeSets?.[0]?.id ?? null;
}

function submit() {
    if (props.mode === 'edit') {
        form.put(route('catalog.rental.update', props.product.id));
    } else {
        form.post(route('catalog.rental.store'));
    }
}

function onCategoryChange() {
    const selected = props.categories.find(c => c.id === form.product_category_id);
    if (selected?.attribute_set_id) {
        form.attribute_set_id = selected.attribute_set_id;
    }
}

const currentDefs = computed(() => {
    const set = props.attributeSets.find(s => s.id === form.attribute_set_id);
    return set?.attributes ?? [];
});
</script>

<template>
    <Head :title="props.mode === 'edit' ? 'Edit Rental' : 'Create Rental'" />
    <AuthenticatedLayout>
        <template #header>
            <h2>{{ props.mode === 'edit' ? 'Edit Rental' : 'Create Rental' }}</h2>
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
                        @update:modelValue="onCategoryChange"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.attribute_set_id"
                        :options="attributeSets.map(s => ({ value: s.id, label: s.name }))"
                        label="Attribute Set"
                        :error="form.errors.attribute_set_id"
                        placeholder="Select Attribute Set"
                    />
                    <AppSelect
                        v-model="form.tax_category_id"
                        :options="taxCategories.map(t => ({ value: t.id, label: t.name }))"
                        label="Tax Category"
                        :error="form.errors.tax_category_id"
                        placeholder="Select Tax Category"
                    />
                </div>
                <AppSelect
                    v-model="form.company_ids"
                    :options="companies.map(company => ({ value: company.id, label: company.name }))"
                    label="Companies"
                    :error="form.errors.company_ids"
                    placeholder="Select Companies"
                    multiple
                />
                <div class="flex items-center mt-2">
                    <input id="is_active_rent" v-model="form.is_active" type="checkbox" class="mr-2">
                    <label for="is_active_rent">Active</label>
                </div>

                <div class="mt-6">
                    <h3 class="text-lg font-semibold mb-2">Attributes</h3>
                    <DynamicAttributesForm
                        v-model="form.attributes"
                        :defs="currentDefs"
                        :errors="form.errors"
                    />
                </div>

                <div class="flex items-center">
                    <AppPrimaryButton type="submit" class="mr-2">Save</AppPrimaryButton>
                    <AppSecondaryButton :href="route('catalog.rental.index')" as="a">Cancel</AppSecondaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>


