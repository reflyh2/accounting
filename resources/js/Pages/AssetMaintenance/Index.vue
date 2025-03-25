<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    asset: Object,
    maintenanceRecords: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'maintenance_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'maintenance_date', label: 'Tanggal' },
    { key: 'maintenance_type_id', label: 'Jenis' },
    { key: 'cost', label: 'Biaya' },
    { key: 'performed_by', label: 'Dilakukan Oleh' },
    { key: 'next_maintenance_date', label: 'Jadwal Berikutnya' },
    { key: 'completed_at', label: 'Status' },
    { key: 'actions', label: '' }
];

const customFilters = computed(() => [
    {
        name: 'from_date',
        type: 'date',
        placeholder: 'Dari Tanggal',
        label: 'Dari Tanggal'
    },
    {
        name: 'to_date',
        type: 'date',
        placeholder: 'Sampai Tanggal',
        label: 'Sampai Tanggal'
    }
]);

const columnFormatters = {
    maintenance_date: (value) => new Date(value).toLocaleDateString(),
    cost: (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value),
    next_maintenance_date: (value) => value ? new Date(value).toLocaleDateString() : '-',
    completed_at: (value) => value ? 
        '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Selesai</span>' : 
        '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Dalam Proses</span>'
};

const columnRenderers = {
    maintenance_type_id: (value, item) => item.maintenance_type ? item.maintenance_type.name : '-',
};

const sortableColumns = ['maintenance_date', 'maintenance_type', 'cost', 'performed_by', 'next_maintenance_date', 'completed_at'];
const defaultSort = { key: 'maintenance_date', order: 'desc' };

function deleteMaintenance(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-maintenance.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery
        },
    });
}

function handleBulkDelete(ids) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-maintenance.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
            ids: ids,
        },
    });
}

function completeMaintenance(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.post(route('asset-maintenance.complete', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
        },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('asset-maintenance.index', props.asset.id), {
        ...route().params,
        ...currentFilters.value,
        sort: newSort.key,
        order: newSort.order,
        per_page: props.perPage,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('asset-maintenance.index', props.asset.id), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
        per_page: newFilters.per_page || props.perPage,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
   <Head title="Pemeliharaan Aset" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Pemeliharaan Aset</h2>
      </template>

      <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
         <div class="bg-white shadow-sm sm:rounded border border-gray-200 mb-6">
            <div class="p-6">
               <div class="mb-6">
                  <AppBackLink :href="route('assets.show', props.asset.id)" :text="`Kembali ke Detail Aset: ${props.asset.name}`" />
               </div>

               <h3 class="text-lg font-semibold mb-4">Informasi Aset</h3>
               <div class="grid grid-cols-2 gap-4">
                  <div>
                        <p class="text-sm text-gray-600">Nama Aset</p>
                        <p class="font-medium">{{ props.asset?.name }}</p>
                  </div>
                  <div>
                        <p class="text-sm text-gray-600">Kategori</p>
                        <p class="font-medium">{{ props.asset.category.name }}</p>
                  </div>
                  <div>
                        <p class="text-sm text-gray-600">Perusahaan</p>
                        <p class="font-medium">{{ props.asset.branch.branch_group.company.name }}</p>
                  </div>
                  <div>
                        <p class="text-sm text-gray-600">Cabang</p>
                        <p class="font-medium">{{ props.asset.branch.name }}</p>
                  </div>
               </div>
            </div>
            
            <div class="text-gray-900">
               <AppDataTable
                  :data="maintenanceRecords"
                  :filters="currentFilters"
                  :tableHeaders="tableHeaders"
                  :columnFormatters="columnFormatters"
                  :columnRenderers="columnRenderers"
                  :customFilters="customFilters"
                  :createRoute="{ name: 'asset-maintenance.create', params: { asset: asset.id } }"
                  :editRoute="{ name: 'asset-maintenance.edit', params: { asset: asset.id } }"
                  :deleteRoute="{ name: 'asset-maintenance.destroy', params: { asset: asset.id } }"
                  :indexRoute="{ name: 'asset-maintenance.index', params: { asset: asset.id } }"
                  :sortable="sortableColumns"
                  :defaultSort="defaultSort"
                  :currentSort="currentSort"
                  :perPage="perPage"
                  routeName="asset-maintenance.index"
                  @delete="deleteMaintenance"
                  @bulkDelete="handleBulkDelete"
                  @sort="handleSort"
                  @filter="handleFilter"
               >
                  <template #custom_actions="{ item }">
                        <button
                           v-if="!item.completed_at"
                           @click="completeMaintenance(item.id)"
                           class="text-green-600 hover:text-green-900 mr-3"
                        >
                           Selesai
                        </button>
                  </template>
               </AppDataTable>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template> 