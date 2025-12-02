<script setup>
import { computed } from 'vue';
import { getDocumentStatusMeta } from '@/constants/documentStatuses';

const props = defineProps({
  documentKind: {
    type: String,
    required: true,
  },
  status: {
    type: String,
    required: true,
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md'].includes(value),
  },
  uppercase: {
    type: Boolean,
    default: false,
  },
  showDot: {
    type: Boolean,
    default: true,
  },
});

const meta = computed(() => getDocumentStatusMeta(props.documentKind, props.status));

const label = computed(() => (props.uppercase ? meta.value.label.toUpperCase() : meta.value.label));

const sizeClasses = computed(() => (props.size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-sm px-3 py-1'));
</script>

<template>
  <span
    class="inline-flex items-center gap-2 rounded-full font-semibold tracking-tight"
    :class="[meta.classes, sizeClasses]"
  >
    <span v-if="showDot" class="h-2 w-2 rounded-full" :class="meta.dotClass" />
    <span class="whitespace-nowrap">
      {{ label }}
    </span>
  </span>
</template>

