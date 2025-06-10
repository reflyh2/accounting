<script setup>
import { computed, ref, useAttrs, watch } from 'vue';

defineOptions({
   inheritAttrs: false
});

const props = defineProps({
   modelValue: String,
   label: String,
   error: String,
   required: Boolean,
   rows: {
      type: Number,
      default: 3
   },
   submitted: Boolean,
   disabled: {
      type: Boolean,
      default: false
   },
   margins: {
      type: Object,
      default: () => ({
         top: 0,
         right: 0,
         bottom: 4,
         left: 0
      })
   }
});

const emit = defineEmits(['update:modelValue']);

const attrs = useAttrs();

const inputAttrs = computed(() => {
   const { class: _, ...rest } = attrs;
   return rest;
});

const focused = ref(false);
const initialValue = ref(props.modelValue);
const hasChanged = ref(false);

const textareaClass = computed(() => [
   'w-full px-1.5 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-main-500',
   !focused.value && props.error && !hasChanged.value ? 'border-red-500' : '',
   props.disabled ? 'bg-gray-50 cursor-not-allowed' : ''
]);

watch(() => props.modelValue, (newValue) => {
   hasChanged.value = newValue !== initialValue.value;
});

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
</script>

<template>
   <div :class="`mt-${margins.top} mr-${margins.right} mb-${margins.bottom} ml-${margins.left} relative`">
      <label v-if="label" class="block mb-1 text-sm">
         {{ label }}
         <span v-if="required" class="text-red-500 ml-1">*</span>
      </label>
      <textarea
         :value="modelValue"
         @input="$emit('update:modelValue', $event.target.value)"
         :class="textareaClass"
         :rows="rows"
         v-bind="inputAttrs"
         @focus="onFocus"
         @blur="onBlur"
      ></textarea>
      <div v-if="props.error && !focused && !hasChanged" class="text-red-500 mt-2 text-sm">{{ props.error }}</div>
   </div>
</template>