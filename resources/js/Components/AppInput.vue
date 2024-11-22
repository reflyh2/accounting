<script setup>
import { computed, ref, useAttrs, watch, nextTick } from 'vue';
import { formatNumber, unformatNumber } from '@/utils/numberFormat';

defineOptions({
   inheritAttrs: false
});

const props = defineProps({
   modelValue: String,
   label: String,
   error: String,
   required: Boolean,
   submitted: Boolean,
   numberFormat: Boolean,
   prefix: [String, Object],
   suffix: [String, Object],
   disabled: Boolean,
});

const emit = defineEmits(['update:modelValue']);

const attrs = useAttrs();

const { type, ...restAttrs } = attrs;
const inputAttrs = computed(() => {
   const { class: _, ...rest } = restAttrs;
   return rest;
});

const inputType = computed(() => type || 'text');

const focused = ref(false);
const initialValue = ref(props.modelValue);
const hasChanged = ref(false);

const inputClass = computed(() => [
   'w-full px-2 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-main-500 focus:border-gray-300',
   !focused.value && props.error && !hasChanged.value ? 'border-red-500' : '',
   props.prefix ? 'rounded-l-none' : '',
   props.suffix ? 'rounded-r-none' : '',
   props.disabled ? 'bg-gray-50 cursor-not-allowed' : '',
]);

const displayValue = ref(props.modelValue);
const internalValue = ref(props.modelValue);

watch(() => props.modelValue, (newValue) => {
   hasChanged.value = newValue !== initialValue.value;
   internalValue.value = newValue;
   if (props.numberFormat) {
      displayValue.value = formatNumber(newValue);
   } else {
      displayValue.value = newValue;
   }
}, { immediate: true });

// Add this watch effect
watch(() => props.submitted, (newValue) => {
   if (newValue) {
      hasChanged.value = false;
   }
});

function onBlur() {
   focused.value = false;
}

function onFocus() {
   focused.value = true;
}

function onInput(event) {
   let value = event.target.value;
   if (props.numberFormat) {
      // Get cursor position and value length before formatting
      const cursorPosition = event.target.selectionStart;
      const lengthBefore = value.length;

      // Handle comma input
      if (value.endsWith(',') && !internalValue.value.includes('.')) {
         internalValue.value += '.';
      } else {
         internalValue.value = unformatNumber(value);
      }
      
      // Format the display value
      displayValue.value = formatNumber(internalValue.value, true);
      
      // Calculate the number of separators before the cursor
      const separatorsBefore = (value.slice(0, cursorPosition).match(/\./g) || []).length;
      const separatorsAfter = (displayValue.value.slice(0, cursorPosition).match(/\./g) || []).length;
      const separatorsDiff = separatorsAfter - separatorsBefore;
      
      // Adjust cursor position
      nextTick(() => {
         const newCursorPosition = cursorPosition + separatorsDiff;
         event.target.setSelectionRange(newCursorPosition, newCursorPosition);
      });
   } else {
      displayValue.value = value;
      internalValue.value = value;
   }
   emit('update:modelValue', internalValue.value);
}

function onKeyDown(event) {
   if (props.numberFormat) {
      const allowedKeys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Backspace', 'Delete', 'Tab', ',', '.'];
      if (!allowedKeys.includes(event.key) && !/^[0-9]$/.test(event.key) && !event.ctrlKey) {
         event.preventDefault();
      }
      
      // Special handling for Backspace and Delete
      if (event.key === 'Backspace' || event.key === 'Delete') {
         const cursorPosition = event.target.selectionStart;
         const value = event.target.value;
         
         if (value[cursorPosition - 1] === '.' || value[cursorPosition] === '.') {
            event.preventDefault();
            const newValue = value.slice(0, cursorPosition - (event.key === 'Backspace' ? 1 : 0)) + 
                             value.slice(cursorPosition + (event.key === 'Delete' ? 1 : 0));
            displayValue.value = newValue;
            internalValue.value = unformatNumber(newValue);
            emit('update:modelValue', internalValue.value);
            
            nextTick(() => {
               event.target.setSelectionRange(cursorPosition, cursorPosition);
            });
         }
      }
   }
}
</script>

<template>
   <div class="mb-4 relative">
      <label v-if="label" class="block mb-1 text-sm">
         {{ label }}
         <span v-if="required" class="text-red-500 ml-1">*</span>
      </label>
      <div class="flex">
         <div v-if="prefix" class="flex items-center px-2 bg-gray-100 border border-r-0 border-gray-300 rounded-l text-sm">
            <span v-if="typeof prefix === 'string'" v-html="prefix"></span>
            <component v-else :is="prefix"></component>
         </div>
         <input
            :value="displayValue"
            @input="onInput"
            @keydown="onKeyDown"
            :class="inputClass"
            :type="inputType"
            v-bind="inputAttrs"
            @focus="onFocus"
            @blur="onBlur"
            :disabled="disabled"
         >
         <div v-if="suffix" class="flex items-center px-2 bg-gray-100 border border-l-0 border-gray-300 rounded-r text-sm">
            <span v-if="typeof suffix === 'string'" v-html="suffix"></span>
            <component v-else :is="suffix"></component>
         </div>
      </div>
      <div v-if="props.error && !focused && !hasChanged" class="text-red-500 text-sm mt-2">{{ props.error }}</div>
      <slot name="help"></slot>
   </div>
</template>
