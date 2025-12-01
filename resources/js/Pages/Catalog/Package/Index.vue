<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    items: Object,
    filters: Object,
    categories: Array,
});
</script>

<template>
    <Head title="Catalog - Packages" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Catalog: Packages</h2>
        </template>

        <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4">
            <div class="mb-3 flex justify-between">
                <div></div>
                <Link :href="route('catalog.packages.create')" class="px-3 py-1.5 bg-main-600 hover:bg-main-700 text-white rounded">
                    New Package
                </Link>
            </div>
            <table class="w-full border-collapse border border-gray-200 text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border px-2 py-1.5 text-left">Code</th>
                        <th class="border px-2 py-1.5 text-left">Name</th>
                        <th class="border px-2 py-1.5 text-left">Category</th>
                        <th class="border px-2 py-1.5 text-left">Active</th>
                        <th class="border px-2 py-1.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items.data" :key="item.id">
                        <td class="border px-2 py-1.5">{{ item.code }}</td>
                        <td class="border px-2 py-1.5">{{ item.name }}</td>
                        <td class="border px-2 py-1.5">{{ item.category?.name }}</td>
                        <td class="border px-2 py-1.5">{{ item.is_active ? 'Yes' : 'No' }}</td>
                        <td class="border px-2 py-1.5 text-right">
                            <Link :href="route('catalog.packages.edit', item.id)" class="text-main-600 hover:underline">Edit</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>


