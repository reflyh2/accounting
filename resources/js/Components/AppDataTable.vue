<script setup>
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import { router, Link } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import AppViewButton from '@/Components/AppViewButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { PlusIcon, ArrowDownTrayIcon, TrashIcon } from '@heroicons/vue/16/solid';
import { FunnelIcon } from '@heroicons/vue/24/outline';
import AppTable from '@/Components/AppTable.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppDangerButton from './AppDangerButton.vue';

const props = defineProps({
    data: Object,
    filters: Object,
    tableHeaders: Array,
    createRoute: [String, Object],
    editRoute: [String, Object],
    deleteRoute: [String, Object],
    viewRoute: [String, Object],
    indexRoute: [String, Object],
    customFilters: Array,
    downloadOptions: Array,
    perPageOptions: {
        type: Array,
        default: () => [10, 25, 50, 100]
    },
    downloadBaseRoute: String,
    sortable: {
        type: Array,
        default: () => []
    },
    defaultSort: {
        type: Object,
        default: () => ({ key: null, order: 'asc' })
    },
    currentSort: {
        type: Object,
        default: () => ({ key: null, order: 'asc' })
    },
    columnFormatters: {
        type: Object,
        default: () => []
    },
    columnRenderers: {
        type: Object,
        default: () => []
    },
    enableBulkActions: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['delete', 'sort', 'filter', 'bulkDelete']);

const search = ref(props.filters?.search || '');
const customFilterValues = ref({});
const showFilters = ref(false);
const rowsPerPage = ref(props.data.per_page || 10);
const showDeleteConfirmation = ref(false);
const itemToDelete = ref(null);
const showDownloadOptions = ref(false);
const selectedItems = ref([]);
const showBulkDeleteConfirmation = ref(false);

props.customFilters?.forEach(filter => {
    customFilterValues.value[filter.name] = props.filters?.[filter.name] || '';
});

// Create a debounced function to emit filter changes
const debouncedEmitFilter = debounce(() => {
    emit('filter', {
        search: search.value,
        ...customFilterValues.value,
        per_page: rowsPerPage.value
    });
}, 300); // Wait for 300ms of inactivity before emitting

// Replace the watch function with this:
watch([search, customFilterValues, rowsPerPage], () => {
    debouncedEmitFilter();
}, { deep: true });

function getRoute(routeInfo, params = {}) {
    if (typeof routeInfo === 'string') {
        return route(routeInfo, params);
    } else if (typeof routeInfo === 'object' && routeInfo.name) {
        return route(routeInfo.name, { ...routeInfo.params, ...params });
    }
    return '#';
}

function confirmDelete(id) {
    itemToDelete.value = id;
    showDeleteConfirmation.value = true;
}

function deleteItem() {
    if (itemToDelete.value) {
        emit('delete', itemToDelete.value);
        showDeleteConfirmation.value = false;
        itemToDelete.value = null;
    }
}

function downloadData(format) {
    if (!props.downloadBaseRoute) {
        console.error('Download base route not provided');
        return;
    }

    let routeName = `${props.downloadBaseRoute}.export-${format}`;

    // Construct the full URL with current filters
    const url = route(routeName, {
        ...customFilterValues.value,
        search: search.value,
        per_page: rowsPerPage.value
    });

    // Trigger the download by opening the URL in a new tab
    window.open(url, '_blank');
    
    showDownloadOptions.value = false;
}

function handleSort(sort) {
    router.get(route(props.indexRoute.name), {
        ...route().params,
        sort: sort.key,
        order: sort.order
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
    emit('sort', sort);
}

function handleFilterChange(filterName, value) {
    customFilterValues.value[filterName] = value;
    // The debounced function will be called automatically due to the watch
}

function handleSelectionChange(items) {
    selectedItems.value = items;
}

function confirmBulkDelete() {
    showBulkDeleteConfirmation.value = true;
}

function bulkDelete() {
    emit('bulkDelete', selectedItems.value);
    showBulkDeleteConfirmation.value = false;
}
</script>

<template>
    <div>
        <div class="mb-4 px-6 pt-6 flex justify-between items-center">
            <div class="flex items-center">
                <Link :href="getRoute(createRoute)" class="mr-2">
                    <AppPrimaryButton>
                        <PlusIcon class="w-5 h-5 mr-1" />
                        Buat
                    </AppPrimaryButton>
                </Link>
                <AppDangerButton 
                    v-if="enableBulkActions && selectedItems.length > 0"
                    @click="confirmBulkDelete"
                    class="mr-2"
                >
                    <TrashIcon class="w-5 h-5 mr-1" />
                    Hapus ({{ selectedItems.length }})
                </AppDangerButton>
                <div v-if="downloadOptions" class="relative">
                    <AppUtilityButton @click="showDownloadOptions = !showDownloadOptions">
                        <ArrowDownTrayIcon class="w-5 h-5 mr-1" />
                        Download
                    </AppUtilityButton>
                    <div v-if="showDownloadOptions" class="absolute left-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                        <div class="py-1">
                            <button v-for="option in downloadOptions" :key="option.format" @click="downloadData(option.format)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <AppUtilityButton @click="showFilters = !showFilters">
                <FunnelIcon class="w-5 h-5 mr-1" />
                Filter
            </AppUtilityButton>
        </div>

        <div v-if="showFilters">
            <div class="bg-slate-50 px-4 py-3 border-y border-gray-200">
                <div class="mt-4 flex flex-wrap">
                    <div class="w-full md:w-1/2 lg:w-1/4 px-2">
                        <AppInput
                            v-model="search"
                            placeholder="Cari..."
                            class="focus:ring-main-500"
                            label="Cari data"
                            @update:modelValue="handleFilterChange('search', $event)"
                        />
                    </div>
                    <template v-for="filter in customFilters" :key="filter.name">
                        <div v-if="filter.type === 'select'" class="w-full md:w-1/2 lg:w-1/4 px-2">
                            <AppSelect
                                v-model="customFilterValues[filter.name]"
                                :options="filter.options"
                                :class="filter.class"
                                :multiple="filter.multiple"
                                :placeholder="filter.placeholder"
                                :label="filter.label"
                                @update:modelValue="handleFilterChange(filter.name, $event)"
                            />
                        </div>
                        <div v-else class="w-full md:w-1/2 lg:w-1/4 px-2">
                            <AppInput
                                v-model="customFilterValues[filter.name]"
                                :type="filter.type"
                                :placeholder="filter.placeholder"
                                :class="filter.class"
                                :label="filter.label"
                                @update:modelValue="handleFilterChange(filter.name, $event)"
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <AppTable
            :headers="tableHeaders"
            :data="data.data"
            :sortable="sortable"
            :defaultSort="defaultSort"
            :currentSort="currentSort"
            :routeName="indexRoute.name"
            :columnFormatters="columnFormatters"
            :columnRenderers="columnRenderers"
            :enableBulkActions="enableBulkActions"
            @sort="handleSort"
            @selectionChange="handleSelectionChange"
        >
            <template #actions="{ item }">
                <slot name="custom_actions" :item="item" />
                <Link v-if="viewRoute" :href="getRoute(viewRoute, { id: item.id })">
                    <AppViewButton title="Detail" />
                </Link>
                <Link v-if="editRoute" :href="getRoute(editRoute, { id: item.id })">
                    <AppEditButton title="Ubah" />
                </Link>
                <AppDeleteButton v-if="deleteRoute" @click="confirmDelete(item.id)" title="Hapus" />
            </template>
        </AppTable>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Data"
            @close="showDeleteConfirmation = false"
            @confirm="deleteItem"
        />

        <DeleteConfirmationModal
            :show="showBulkDeleteConfirmation"
            title="Hapus Data"
            message="Apakah Anda yakin ingin menghapus data yang dipilih?"
            @close="showBulkDeleteConfirmation = false"
            @confirm="bulkDelete"
        />

        <div class="mt-6 px-6 pb-6">         
            <div class="flex flex-col xl:flex-row xl:justify-between xl:items-center">
                <div class="flex items-center text-sm">
                    <span class="mr-2">Showing</span>
                    <select v-model="rowsPerPage" class="pl-2 pr-8 py-1 text-sm text-left border-gray-300 focus:border-main-500 focus:ring-main-500 rounded">
                        <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
                    </select>
                    <span class="ml-2">rows out of {{ data.total }}</span>
                </div>
                <Pagination :links="data.links" class="mb-4 xl:mb-0" />
            </div>
        </div>
    </div>
</template>