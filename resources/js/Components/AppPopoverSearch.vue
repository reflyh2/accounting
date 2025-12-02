<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import axios from 'axios';
import AppInput from '@/Components/AppInput.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import AppSecondaryButton from './AppSecondaryButton.vue';

const props = defineProps({
    modelValue: [String, Number, null],
    label: String,
    hint: String,
    placeholder: String,
    url: {
        type: String,
        required: true
    },
    valueKey: {
        type: String,
        default: 'id'
    },
    displayKeys: {
        type: Array,
        required: true
    },
    tableHeaders: {
        type: Array,
        required: true
    },
    initialDisplayValue: {
        type: String,
        default: ''
    },
    required: {
        type: Boolean,
        default: false
    },
    error: String,
    sort: {
        type: Object,
        default: () => ({ key: 'id', order: 'asc' })
    },
    disabled: {
        type: Boolean,
        default: false
    },
    modalTitle: {
        type: String,
        default: 'Select Data'
    }
});

const emit = defineEmits(['update:modelValue']);

const showModal = ref(false);
const tableData = ref({ data: [], links: [], total: 0, per_page: 10 });
const filters = ref({
    search: '',
    per_page: 10,
    sort: props.sort.key,
    order: props.sort.order
});
const loading = ref(false);
const displayValue = ref(props.initialDisplayValue);
const isFetchingItem = ref(false);

const displayedPlaceholder = computed(() => {
    if (isFetchingItem.value) {
        return 'Loading...';
    }
    else if (props.placeholder) {
        return props.placeholder;
    }

    return 'Pilih Data';
});

const fetchItemById = (id) => {
    if (!id) {
        displayValue.value = '';
        return;
    }

    const item = tableData.value.data.find(item => item[props.valueKey] === id);
    if (item) {
        displayValue.value = props.displayKeys.map(key => item[key]).join(' - ');
        return;
    }
};

onMounted(() => {
    displayValue.value = props.initialDisplayValue;
    if (props.modelValue && !props.initialDisplayValue) {
        fetchItemById(props.modelValue);
    }
});

watch(() => props.modelValue, (newValue) => {
    if (newValue) {
        // When modelValue changes, fetch new display value, but only if an initial one isn't provided.
        // The parent can provide initialDisplayValue to prevent this fetch.
        if (!props.initialDisplayValue) {
            fetchItemById(newValue);
        }
    } else {
        displayValue.value = '';
    }
});

watch(() => props.initialDisplayValue, (newValue) => {
    displayValue.value = newValue;
});

watch(() => props.url, (newValue) => {
    fetchData();
});


const openModal = () => {
    if (props.disabled) return;
    showModal.value = true;
    if (tableData.value.data.length === 0) {
        fetchData();
    }
};

const closeModal = () => {
    showModal.value = false;
};

const fetchData = async () => {
    loading.value = true;

    try {
        const response = await axios.get(props.url, { params: filters.value });
        tableData.value = response.data;
    } catch (error) {
        console.error('Error fetching data for AppPopoverSearch:', error);
    } finally {
        loading.value = false;
    }
};

const handleFilter = (newFilters) => {
    filters.value = { ...filters.value, ...newFilters, page: 1 };
    fetchData();
};

const handleSort = (sort) => {
    filters.value.sort = sort.key;
    filters.value.order = sort.order;
    fetchData();
};

const handleSelect = (item) => {
    emit('update:modelValue', item[props.valueKey]);
    displayValue.value = props.displayKeys.map(key => item[key]).join(' - ');
    closeModal();
};

const clearSelection = () => {
    emit('update:modelValue', null);
    displayValue.value = '';
}

const handlePageChange = async (url) => {
    loading.value = true;

    try {
        const response = await axios.get(url);
        tableData.value = response.data;
    } catch (error) {
        console.error('Error fetching data for AppPopoverSearch:', error);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div>
        <div class="relative">
            <AppInput
                :modelValue="displayValue"
                :label="props.label"
                :hint="props.hint"
                :placeholder="displayedPlaceholder"
                :required="props.required"
                :error="props.error"
                :disabled="props.disabled"
                readonly
                @click="openModal"
                :class="{ 'cursor-pointer': !props.disabled, 'cursor-not-allowed': props.disabled }"
            />
            <div v-if="modelValue && !props.disabled" class="absolute right-0 bottom-0 pr-3 py-2 mb-[1px] flex items-center">
                <button @click.stop="clearSelection" type="button" class="text-gray-400 hover:text-gray-500">
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-[110] overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-0 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="closeModal"></div>
                    <div class="relative inline-block px-0 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full sm:p-0">
                        <div class="flex items-center justify-between px-4 sm:px-6 sm:py-4 border-b border-gray-200">
                            <h2 class="text-lg font-medium text-gray-900">
                                {{ modalTitle }}
                            </h2>
                            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <XMarkIcon class="w-6 h-6" />
                            </button>
                        </div>

                        <div class="px-0 -mt-10">
                            <AppDataTable
                                :data="tableData"
                                :filters="filters"
                                :tableHeaders="tableHeaders"
                                :enableBulkActions="false"
                                :indexRoute="false"
                                :deleteRoute="false"
                                :editRoute="false"
                                :viewRoute="false"
                                :createRoute="false"
                                :currentSort="{ key: filters.sort, order: filters.order }"
                                :showFilterButton="false"
                                :perPageOptions="[10]"
                                :useInertia="false"
                                :showFilterLabels="false"
                                @filter="handleFilter"
                                @sort="handleSort"
                                @changePage="handlePageChange"
                            >
                                <template #custom_actions="{ item }">
                                    <button @click="handleSelect(item)" class="text-main-600 hover:text-main-800 hover:underline">
                                        Select
                                    </button>
                                </template>
                            </AppDataTable>
                        </div>

                        <div class="flex justify-end px-4 sm:px-6 sm:py-4 border-t border-gray-200">
                            <AppSecondaryButton @click="closeModal">
                                Close
                            </AppSecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template> 