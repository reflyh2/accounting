<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    product: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteProduct = () => {
    form.delete(route('catalog.services.destroy', props.product.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
        onError: () => {
            showDeleteConfirmation.value = false;
        }
    });
};
</script>

<template>
    <Head title="Detail Jasa" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Jasa</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('catalog.services.index')" text="Back to Service List" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ product.code }} - {{ product.name }}</h3>
                            <div class="flex items-center">
                                <Link :href="route('catalog.services.edit', product.id)" class="mr-1">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Category:</p>
                                <p>{{ product.category?.name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tax Category:</p>
                                <p>{{ product.tax_category?.name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Active:</p>
                                <p>{{ product.is_active ? 'Yes' : 'No' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Companies:</p>
                                <p>{{ product.companies?.length ? product.companies.map(c => c.name).join(', ') : '-' }}</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="text-md font-semibold mb-2">Attributes</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Code</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(value, key) in product.attrs_json || {}" :key="key">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ key }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ value }}</td>
                                    </tr>
                                    <tr v-if="!product.attrs_json || Object.keys(product.attrs_json).length === 0">
                                        <td colspan="2" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">No attributes.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            <h4 class="text-md font-semibold mb-2">Variants</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">SKU</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Attributes</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Track Inventory</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="variant in product.variants || []" :key="variant.id">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ variant.sku }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">
                                            <span v-for="(val, key) in variant.attrs_json" :key="key" class="inline-block mr-2 text-xs bg-gray-100 px-2 py-0.5 rounded">
                                                {{ key }}: {{ val }}
                                            </span>
                                        </td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ variant.track_inventory ? 'Yes' : 'No' }}</td>
                                    </tr>
                                    <tr v-if="!product.variants || product.variants.length === 0">
                                        <td colspan="3" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">No variants.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Delete Product"
            message="Are you sure you want to delete this product?"
            @close="showDeleteConfirmation = false"
            @confirm="deleteProduct"
        />
    </AuthenticatedLayout>
</template>

