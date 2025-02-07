<script setup>
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import AppHint from '@/Components/AppHint.vue';

defineOptions({
   inheritAttrs: false
});

const props = defineProps({
   modelValue: {
      type: [String, Number, Array],
      default: ''
   },
   label: {
      type: String,
      default: ''
   },
   options: {
      type: Array,
      required: true
   },
   error: {
      type: String,
      default: ''
   },
   multiple: {
      type: Boolean,
      default: false
   },
   placeholder: {
      type: String,
      default: 'Pilih opsi'
   },
   required: {
      type: Boolean,
      default: false
   },
   submitted: Boolean,
   maxRows: {
      type: Number,
      default: 4
   },
   disabled: {
      type: Boolean,
      default: false
   },
   hint: {
      type: String,
      default: ''
   }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const isFocused = ref(false);
const selectRef = ref(null);
const dropdownRef = ref(null);
const isOpenUpwards = ref(false);
const initialValue = ref(props.modelValue);
const hasChanged = ref(false);
const searchTerm = ref('');
const searchInput = ref(null);

const selectValue = computed({
   get: () => props.modelValue,
   set: (value) => emit('update:modelValue', value)
});

const selectedOptions = computed(() => {
   if (!props.multiple) {
      const option = props.options?.find(option => option.value == selectValue.value);
      return option ? option : null;
   }
   return props.options?.filter(option => 
      Array.isArray(selectValue.value) && selectValue.value.some(value => value == option.value)
   );
});

const filteredOptions = computed(() => {
   if (!searchTerm.value) return props.options || [];
   return props.options?.filter(option => 
     option.label.toLowerCase().includes(searchTerm.value.toLowerCase())
   );
});

watch(() => props.modelValue, (newValue) => {
   hasChanged.value = JSON.stringify(newValue) !== JSON.stringify(initialValue.value);
});

watch(() => props.submitted, (newValue) => {
   if (newValue) {
      hasChanged.value = false;
   }
});

function toggleOption(option) {
   if (!props.multiple) {
      emit('update:modelValue', option.value);
      isOpen.value = false;
      return;
   }
   
   const newValue = selectValue.value.includes(option.value)
      ? selectValue.value.filter(v => v !== option.value)
      : [...selectValue.value, option.value];
   emit('update:modelValue', newValue);
}

function removeOption(value) {
   if (props.disabled) return;
   selectValue.value = selectValue.value.filter(v => v != value);
}

function clearSelection() {
  if (props.disabled) return;
   emit('update:modelValue', props.multiple ? [] : null);
}

function closeDropdown(event) {
   if (selectRef.value && !selectRef.value.contains(event.target)) {
      isOpen.value = false;
      isFocused.value = false;
   }
}

function handleFocus() {
   if (props.disabled) return;
   isFocused.value = true;
}

function handleBlur(event) {
   // Delay the blur to allow for option selection
   setTimeout(() => {
      if (!selectRef.value.contains(document.activeElement)) {
         isFocused.value = false;
      }
   }, 0);
}

function toggleDropdown() {
   if (props.disabled) return;
   isOpen.value = !isOpen.value;
   if (isOpen.value) {
      nextTick(() => {
         positionDropdown();
         focusSearchInput();
      });
   } else {
      searchTerm.value = ''; // Clear search when closing
   }
}

function focusSearchInput() {
   nextTick(() => {
      if (searchInput.value && typeof searchInput.value.focus === 'function') {
         searchInput.value.focus();
      }
   });
}

function handleSearchInput(event) {
   searchTerm.value = event.target.value;
}

function positionDropdown() {
  if (!selectRef.value || !dropdownRef.value) return;

  const selectRect = selectRef.value.getBoundingClientRect();
  const viewportHeight = window.innerHeight;

  const spaceBelow = viewportHeight - selectRect.bottom;
  const spaceAbove = selectRect.top;

  isOpenUpwards.value = spaceBelow < 300 && spaceAbove > spaceBelow;
}

// Add this computed property
const dropdownContent = computed(() => {
  return isOpenUpwards.value ? ['optionsList', 'searchBox'] : ['searchBox', 'optionsList'];
});

const dropdownStyle = computed(() => {
  if (!isOpen.value || !selectRef.value) return {};
  
  const rect = selectRef.value.getBoundingClientRect();
  const style = {
    width: `${rect.width}px`,
    left: `${rect.left}px`,
  };

  if (isOpenUpwards.value) {
    style.bottom = `${window.innerHeight - rect.top}px`;
  } else {
    style.top = `${rect.bottom}px`;
  }

  return style;
});

onMounted(() => {
   document.addEventListener('click', closeDropdown);
   window.addEventListener('scroll', positionDropdown);
   window.addEventListener('resize', positionDropdown);
});

onUnmounted(() => {
   document.removeEventListener('click', closeDropdown);
   window.removeEventListener('scroll', positionDropdown);
   window.removeEventListener('resize', positionDropdown);
});
</script>

<template>
  <div class="mb-4">
    <label v-if="label" class="block mb-1 text-sm">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-1">*</span>
      <AppHint v-if="hint" :text="hint" />
    </label>
    <div class="relative" ref="selectRef">
      <div 
        @click="toggleDropdown"
        @focus="handleFocus"
        @blur="handleBlur"
        tabindex="0"
        :class="[
          'w-full px-2 py-2 border text-sm border-gray-300 rounded flex items-center justify-between',
          multiple && selectedOptions.length ? 'py-1.5' : 'py-2',
          isFocused ? 'outline-none ring-1 ring-main-500' : '',
          !isFocused && props.error && !hasChanged ? 'border-red-500' : '',
          props.disabled ? 'bg-gray-50 cursor-not-allowed' : 'bg-white cursor-pointer'
        ]"
      >
        <div 
          class="flex flex-wrap items-center flex-grow mr-2"
          :style="{
            maxHeight: props.maxRows ? `${props.maxRows * 1.5}rem` : 'none',
            overflowY: props.maxRows ? 'auto' : 'visible'
          }"
        >
          <template v-if="multiple && selectedOptions.length">
            <span 
              v-for="option in selectedOptions" 
              :key="option.value"
              class="bg-main-100 text-main-800 text-xs font-medium px-2 py-0.5 rounded mr-1 my-0.5 flex items-center"
            >
              {{ option.label }}
              <button @click.stop="removeOption(option.value)" class="ml-1 text-main-600 hover:text-main-800">&times;</button>
            </span>
          </template>
          <span v-else-if="!multiple && selectedOptions">{{ selectedOptions.label }}</span>
          <span v-else class="text-gray-500 text-sm">{{ placeholder }}</span>
        </div>
        <div class="flex items-center">
          <button 
            v-if="(multiple && selectedOptions.length) || (!multiple && selectedOptions)"
            @click.stop="clearSelection"
            :disabled="props.disabled"
            class="text-gray-400 hover:text-gray-600 text-sm mr-1"
            :class="{ 'cursor-not-allowed opacity-60': props.disabled }"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </div>
      </div>
    </div>
    <Teleport to="body">
      <div 
        v-if="isOpen" 
        ref="dropdownRef"
        :class="[
          'fixed z-50 bg-white shadow-lg rounded-md text-base ring-1 ring-black ring-opacity-5 overflow-hidden focus:outline-none text-sm',
          isOpenUpwards ? 'bottom-0' : 'top-0'
        ]"
        :style="dropdownStyle"
      >
        <template v-for="(content, index) in dropdownContent" :key="index">
          <div v-if="content === 'searchBox'" class="border-gray-200">
            <input
              ref="searchInput"
              v-model="searchTerm"
              @input="handleSearchInput"
              @click.stop
              placeholder="Cari..."
              :class="[
               'w-full border-x-0 border-gray-200 py-2 focus:outline-none focus:ring-0 text-sm focus:border-gray-200',
               isOpenUpwards ? 'rounded-b-md border-b-0 border-t' : 'rounded-t-md border-t-0 border-b'
              ]"
            />
          </div>
          <div v-else-if="content === 'optionsList'" class="max-h-60 overflow-auto">
            <div 
              v-for="option in filteredOptions" 
              :key="option.value"
              @click="toggleOption(option)"
              :class="[
                'px-3 py-2 cursor-pointer hover:bg-gray-100',
                (multiple && selectValue.includes(option.value)) || (!multiple && selectValue == option.value) ? 'bg-main-50 text-main-900' : ''
              ]"
            >
              {{ option.label }}
            </div>
          </div>
        </template>
      </div>
    </Teleport>
    <div v-if="props.error && !isFocused && !hasChanged" class="text-red-500 mt-2 text-sm">{{ props.error }}</div>
    <slot name="help"></slot>
  </div>
</template>
