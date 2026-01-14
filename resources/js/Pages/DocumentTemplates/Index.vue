<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { DocumentTextIcon, StarIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    templates: Object,
    filters: Object,
    companies: Array,
    documentTypes: Array,
});

const currentSort = ref({ key: 'name', order: 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'name', label: 'Nama Template' },
    { key: 'document_type', label: 'Tipe Dokumen' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'is_default', label: 'Default' },
    { key: 'is_active', label: 'Status' },
    { key: 'page_size', label: 'Ukuran' },
    { key: 'actions', label: '' }
];

const customFilters = [
    {
        name: 'company_id',
        type: 'select',
        label: 'Perusahaan',
        options: props.companies.map(c => ({ value: c.id, label: c.name })),
        placeholder: 'Semua Perusahaan',
    },
    {
        name: 'document_type',
        type: 'select',
        label: 'Tipe Dokumen',
        options: props.documentTypes,
        placeholder: 'Semua Tipe',
    },
];

const sortableColumns = ['name', 'document_type', 'is_default', 'is_active'];
const defaultSort = { key: 'name', order: 'asc' };

const documentTypeLabels = {
    'sales_order': 'Sales Order',
    'sales_delivery': 'Surat Jalan',
    'sales_invoice': 'Faktur Penjualan',
};

const columnRenderers = {
    'document_type': (value) => documentTypeLabels[value] || value,
    'company.name': (value, item) => item.company?.name || '(Default Global)',
    'is_default': (value) => value
        ? '<span class="inline-flex items-center text-yellow-600"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd" /></svg></span>'
        : '',
    'is_active': (value) => value
        ? '<span class="px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full">Aktif</span>'
        : '<span class="px-2 py-1 text-xs font-medium text-gray-500 bg-gray-100 rounded-full">Nonaktif</span>',
};

function deleteTemplate(id) {
    router.delete(route('document-templates.destroy', id), {
        preserveScroll: true,
        preserveState: true,
    });
}

function handleBulkDelete(ids) {
    router.delete(route('document-templates.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: { ids },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('document-templates.index'), {
        ...currentFilters.value,
        sort: newSort.key,
        order: newSort.order,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('document-templates.index'), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Template Dokumen" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Template Dokumen</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="templates"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :createRoute="{ name: 'document-templates.create' }"
                        :editRoute="{ name: 'document-templates.edit' }"
                        :deleteRoute="{ name: 'document-templates.destroy' }"
                        :viewRoute="{ name: 'document-templates.show' }"
                        :indexRoute="{ name: 'document-templates.index' }"
                        :customFilters="customFilters"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :columnRenderers="columnRenderers"
                        routeName="document-templates.index"
                        @delete="deleteTemplate"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <Link
                                v-if="!item.is_default"
                                :href="route('document-templates.set-default', item.id)"
                                method="post"
                                as="button"
                                class="mr-2"
                                title="Jadikan Default"
                            >
                                <StarIcon class="h-4 w-4 text-gray-400 hover:text-yellow-500" />
                            </Link>
                            <Link
                                :href="route('document-templates.duplicate', item.id)"
                                method="post"
                                as="button"
                                class="mr-2"
                                title="Duplikasi"
                            >
                                <DocumentTextIcon class="h-4 w-4 text-gray-400 hover:text-main-500" />
                            </Link>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
