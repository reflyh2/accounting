<script setup>
import { ref, computed, watch } from 'vue';
import { ChevronUpIcon, ChevronDownIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  headers: {
    type: Array,
    required: true
  },
  data: {
    type: Array,
    required: true
  },
  editingId: {
    type: [Number, String],
    default: null
  },
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
  routeName: {
    type: String,
    required: true
  },
  columnFormatters: {
    type: Object,
    default: () => ({})
  },
  columnRenderers: {
    type: Object,
    default: () => ({})
  },
  enableBulkActions: {
    type: Boolean,
    default: true
  }
});

const emit = defineEmits(['sort', 'selectionChange']);

const sort = ref(props.currentSort);
const selectedItems = ref(new Set());

const allSelected = computed(() => {
  return props.data.length > 0 && selectedItems.value.size === props.data.length;
});

const someSelected = computed(() => {
  return selectedItems.value.size > 0 && !allSelected.value;
});

function toggleAll() {
  if (allSelected.value) {
    selectedItems.value.clear();
  } else {
    selectedItems.value = new Set(props.data.map(item => item.id));
  }
  emitSelectionChange();
}

function toggleSelection(id) {
  if (selectedItems.value.has(id)) {
    selectedItems.value.delete(id);
  } else {
    selectedItems.value.add(id);
  }
  emitSelectionChange();
}

function emitSelectionChange() {
  emit('selectionChange', Array.from(selectedItems.value));
}

function getNestedValue(obj, path) {
  return path.split('.').reduce((acc, part) => acc && acc[part], obj);
}

function toggleSort(key) {
  if (sort.value.key === key) {
    sort.value.order = sort.value.order === 'asc' ? 'desc' : 'asc';
  } else {
    sort.value = { key, order: 'asc' };
  }
  updateSort();
}

function updateSort() {
  router.get(route(props.routeName), {
    ...route().params,
    sort: sort.value.key,
    order: sort.value.order
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  });
  emit('sort', sort.value);
}

watch(() => props.currentSort, (newSort) => {
  sort.value = newSort;
}, { deep: true });

function formatColumnValue(item, key) {
  if (props.columnRenderers[key]) {
    return props.columnRenderers[key](getNestedValue(item, key), item);
  }
  if (props.columnFormatters[key]) {
    return props.columnFormatters[key](getNestedValue(item, key));
  }
  const value = getNestedValue(item, key);
  if (Array.isArray(value)) {
    return value.map(v => v.name || v).join(', ');
  }
  return value;
}

// Clear selection when data changes
watch(() => props.data, () => {
  selectedItems.value.clear();
  emitSelectionChange();
});
</script>

<template>
  <div>
    <table class="relative w-full border-collapse border-y border-gray-300">
      <thead>
        <tr>
          <th v-if="enableBulkActions" class="sticky border-y pl-6 pr-2 py-4 w-8 bg-main-100" style="top:-1px">
            <input
              type="checkbox"
              :checked="allSelected"
              :indeterminate="someSelected"
              @change="toggleAll"
              class="rounded border-gray-300 text-main-600 shadow-sm focus:border-main-300 focus:ring focus:ring-main-200 focus:ring-opacity-50"
            >
          </th>
          <th v-for="header in headers" :key="header.key" 
              class="sticky border-y first:pl-6 last:pr-6 px-2 py-4 text-left text-sm bg-main-100"
              :class="{ 'md:min-w-32': header.key === 'actions', 'cursor-pointer': sortable.includes(header.key) }"
              style="top:-1px"
              @click="sortable.includes(header.key) && toggleSort(header.key)">
            <div class="flex items-center">
              {{ header.label }}
              <template v-if="sortable.includes(header.key)">
                <ChevronUpIcon v-if="sort.key === header.key && sort.order === 'asc'" class="w-4 h-4 ml-1" />
                <ChevronDownIcon v-else-if="sort.key === header.key && sort.order === 'desc'" class="w-4 h-4 ml-1" />
                <ChevronUpDownIcon v-else class="w-4 h-4 ml-1 text-gray-400" />
              </template>
            </div>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in data" 
            :key="item.id" 
            class="bg-white hover:bg-main-50"
        >
          <td v-if="enableBulkActions" class="border-y border-gray-300 pl-6 pr-2 w-8">
            <input
              type="checkbox"
              :checked="selectedItems.has(item.id)"
              @change="toggleSelection(item.id)"
              class="rounded border-gray-300 text-main-600 shadow-sm focus:border-main-300 focus:ring focus:ring-main-200 focus:ring-opacity-50"
            >
          </td>
          <td v-for="header in headers" :key="header.key" 
              class="border-y border-gray-300 first:pl-6 last:pr-6 px-2 py-3 text-sm"
              :class="{ 'md:min-w-32': header.key === 'actions' }">
            <slot :name="header.key" :item="item">
              <div v-html="formatColumnValue(item, header.key)"></div>
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>