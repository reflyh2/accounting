<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { computed, watch } from 'vue';

const props = defineProps({
    mode: String,
    limit: Object,
    users: Array,
    products: Array,
    categories: Array,
});

const form = useForm({
    user_global_id: props.limit?.user_global_id ?? null,
    scope_type: props.limit?.product_id ? 'product' : (props.limit?.product_category_id ? 'category' : 'global'),
    product_id: props.limit?.product_id ?? null,
    product_category_id: props.limit?.product_category_id ?? null,
    max_discount_percent: props.limit?.max_discount_percent ?? 0,
    is_active: props.limit?.is_active ?? true,
});

const heading = computed(() => (props.mode === 'edit' ? 'Edit Discount Limit' : 'Create Discount Limit'));

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

function submit() {
    // Clear scope fields based on scope_type before submit
    const payload = {
        user_global_id: form.user_global_id,
        max_discount_percent: form.max_discount_percent,
        is_active: form.is_active,
        product_id: form.scope_type === 'product' ? form.product_id : null,
        product_category_id: form.scope_type === 'category' ? form.product_category_id : null,
    };

    if (props.mode === 'edit') {
        form.transform(() => payload).put(route('catalog.user-discount-limits.update', props.limit.id));
    } else {
        form.transform(() => payload).post(route('catalog.user-discount-limits.store'));
    }
}

const scopeTypeOptions = [
    { value: 'global', label: 'Global (applies to all products)' },
    { value: 'category', label: 'Category (applies to products in category)' },
    { value: 'product', label: 'Product (applies to specific product)' },
];
</script>

<template>
    <Head :title="heading" />

    <AuthenticatedLayout>
        <template #header>
            <h2>{{ heading }}</h2>
        </template>

        <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4 max-w-3xl">
            <form class="space-y-4" @submit.prevent="submit">
                <AppSelect
                    v-model="form.user_global_id"
                    :options="users.map(u => ({ value: u.value, label: `${u.label} (${u.email})` }))"
                    label="User"
                    :error="form.errors.user_global_id"
                    required
                />

                <AppSelect
                    v-model="form.scope_type"
                    :options="scopeTypeOptions"
                    label="Scope Type"
                />

                <AppSelect
                    v-if="form.scope_type === 'product'"
                    v-model="form.product_id"
                    :options="[{ value: null, label: 'Select Product' }, ...products]"
                    label="Product"
                    :error="form.errors.product_id"
                    required
                />

                <AppSelect
                    v-if="form.scope_type === 'category'"
                    v-model="form.product_category_id"
                    :options="[{ value: null, label: 'Select Category' }, ...categories]"
                    label="Product Category"
                    :error="form.errors.product_category_id"
                    required
                />

                <div class="grid md:grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.max_discount_percent"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        label="Max Discount (%)"
                        :error="form.errors.max_discount_percent"
                        required
                    />
                    <div class="flex items-center mt-6">
                        <AppCheckbox v-model="form.is_active" label="Active" />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <AppPrimaryButton type="submit">
                        Save
                    </AppPrimaryButton>
                    <AppSecondaryButton :href="route('catalog.user-discount-limits.index')" as="a">
                        Cancel
                    </AppSecondaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
