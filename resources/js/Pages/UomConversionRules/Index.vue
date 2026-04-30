<script setup>
import { ref, computed } from 'vue';
import { router, Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import UomTabs from '@/Tabs/UomTabs.vue';

const props = defineProps({
    rules: Object,
    filters: Object,
    perPage: [String, Number],
    contexts: Array,
});

const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'from_uom.code', label: 'Dari' },
    { key: 'to_uom.code', label: 'Ke' },
    { key: 'method', label: 'Metode' },
    { key: 'product.name', label: 'Produk' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'partner.name', label: 'Partner' },
    { key: 'context', label: 'Konteks' },
    { key: 'effective_from', label: 'Berlaku Dari' },
    { key: 'effective_to', label: 'Sampai' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    method: (value) => {
        const map = { fixed_ratio: 'Rasio Tetap', avg_weight: 'Berat Rata-rata', density: 'Densitas' };
        return map[value] || value;
    },
    context: (value) => {
        if (!value) return '— semua —';
        const map = { purchase: 'Pembelian', sales: 'Penjualan', inventory: 'Inventaris', pricing: 'Harga' };
        return map[value] || value;
    },
    effective_from: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '—',
    effective_to: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '—',
};

const columnRenderers = {
    'from_uom.code': (_, item) => item?.from_uom ? `${item.from_uom.code} (${item.from_uom.name})` : '—',
    'to_uom.code': (_, item) => item?.to_uom ? `${item.to_uom.code} (${item.to_uom.name})` : '—',
    'product.name': (_, item) => item?.product ? item.product.name : '— semua —',
    'company.name': (_, item) => item?.company ? item.company.name : '— semua —',
    'partner.name': (_, item) => item?.partner ? item.partner.name : '— semua —',
};

const customFilters = computed(() => [
    {
        name: 'context',
        type: 'select',
        options: props.contexts || [],
        multiple: true,
        placeholder: 'Filter konteks',
        label: 'Konteks',
    },
]);

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('uom-conversion-rules.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery },
    });
}

function handleBulkDelete(ids) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('uom-conversion-rules.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery, ids },
    });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('uom-conversion-rules.index'), {
        ...currentFilters.value,
        per_page: newFilters.per_page || props.perPage,
    }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <Head title="Aturan Konversi Satuan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Satuan (UoM)</h2>
        </template>

        <div class="mx-auto">
            <UomTabs activeTab="uom-conversion-rules.index" />

            <div class="bg-yellow-50 border border-yellow-200 text-yellow-900 text-sm rounded p-3 mb-3">
                <strong>Catatan:</strong> Aturan konversi spesifik (per produk, perusahaan, partner, atau konteks) belum diintegrasikan ke mesin konversi inti. Saat ini aturan ini disimpan untuk referensi dan dropdown ketersediaan UOM, tetapi konversi aktual masih menggunakan tabel Konversi Satuan utama.
            </div>

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="rules"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'uom-conversion-rules.create' }"
                        :editRoute="{ name: 'uom-conversion-rules.edit' }"
                        :deleteRoute="{ name: 'uom-conversion-rules.destroy' }"
                        :indexRoute="{ name: 'uom-conversion-rules.index' }"
                        :perPage="perPage"
                        routeName="uom-conversion-rules.index"
                        @delete="deleteItem"
                        @bulkDelete="handleBulkDelete"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
