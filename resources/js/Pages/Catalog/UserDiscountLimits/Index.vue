<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    limits: Object,
    filters: Object,
});

const deleteForm = useForm({});
const showConfirm = ref(null);

const rows = computed(() => props.limits?.data ?? []);

function destroy(id) {
    deleteForm.delete(route('catalog.user-discount-limits.destroy', id), {
        preserveScroll: true,
        onFinish: () => (showConfirm.value = null),
    });
}

function scopeLabel(limit) {
    if (limit.product) {
        return `Product: ${limit.product.name}`;
    }
    if (limit.product_category) {
        return `Category: ${limit.product_category.name}`;
    }
    return 'Global';
}
</script>

<template>
    <Head title="User Discount Limits" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2>User Discount Limits</h2>
                <AppPrimaryButton :href="route('catalog.user-discount-limits.create')" as="a">
                    Add Limit
                </AppPrimaryButton>
            </div>
        </template>

        <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 uppercase text-xs border-b">
                            <th class="py-2">User</th>
                            <th class="py-2">Scope</th>
                            <th class="py-2">Max Discount</th>
                            <th class="py-2">Status</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="limit in rows" :key="limit.id" class="border-b last:border-b-0">
                            <td class="py-3">
                                <div class="font-semibold">{{ limit.user?.name ?? '-' }}</div>
                                <div class="text-xs text-gray-500">{{ limit.user?.email }}</div>
                            </td>
                            <td class="py-3">
                                {{ scopeLabel(limit) }}
                            </td>
                            <td class="py-3">
                                <span class="font-semibold">{{ limit.max_discount_percent }}%</span>
                            </td>
                            <td class="py-3">
                                <span
                                    class="px-2 py-0.5 rounded text-xs"
                                    :class="limit.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ limit.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-3 text-right">
                                <Link
                                    :href="route('catalog.user-discount-limits.edit', limit.id)"
                                    class="text-main-600 hover:underline text-sm mr-2"
                                >
                                    Edit
                                </Link>
                                <AppDeleteButton @click="showConfirm = limit.id" title="Delete" />
                                <div
                                    v-if="showConfirm === limit.id"
                                    class="mt-2 text-xs text-gray-600 flex items-center gap-2 justify-end"
                                >
                                    <span>Delete this limit?</span>
                                    <button class="text-red-600" @click="destroy(limit.id)">Yes</button>
                                    <button class="text-gray-500" @click="showConfirm = null">No</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="5" class="py-6 text-center text-gray-500">
                                No discount limits configured yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-end">
                <Pagination v-if="limits" :links="limits.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
