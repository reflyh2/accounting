<script setup>
import { ref, computed } from 'vue';
import { router, Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    conversions: Object,
    filters: Object,
    perPage: [String, Number],
    kinds: Array,
});

const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'from_uom.code', label: 'Dari' },
    { key: 'to_uom.code', label: 'Ke' },
    { key: 'numerator', label: 'Pembilang' },
    { key: 'denominator', label: 'Penyebut' },
    { key: 'factor', label: 'Faktor' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    factor: (value) => Number(value).toLocaleString('id-ID', { maximumFractionDigits: 6 }),
};

const columnRenderers = {
    'from_uom.code': (_, item) => item?.from_uom ? `${item.from_uom.code} (${item.from_uom.name})` : '-',
    'to_uom.code': (_, item) => item?.to_uom ? `${item.to_uom.code} (${item.to_uom.name})` : '-',
};

const customFilters = computed(() => [
    {
        name: 'kind',
        type: 'select',
        options: props.kinds || [],
        multiple: true,
        placeholder: 'Filter jenis',
        label: 'Jenis',
    },
]);

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('uom-conversions.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery },
    });
}

function handleBulkDelete(ids) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('uom-conversions.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery, ids },
    });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('uom-conversions.index'), {
        ...currentFilters.value,
        per_page: newFilters.per_page || props.perPage,
    }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <Head title="Konversi Satuan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Konversi Satuan</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="conversions"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'uom-conversions.create' }"
                        :editRoute="{ name: 'uom-conversions.edit' }"
                        :deleteRoute="{ name: 'uom-conversions.destroy' }"
                        :indexRoute="{ name: 'uom-conversions.index' }"
                        :perPage="perPage"
                        routeName="uom-conversions.index"
                        @delete="deleteItem"
                        @bulkDelete="handleBulkDelete"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
