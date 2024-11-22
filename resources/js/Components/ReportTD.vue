<script setup>
defineProps({
  colspan: {
    type: Number,
    default: 1
  }
});
</script>

<template>
  <td 
    :colspan="colspan"
    :class="[
      'border-l last:border-r border-t group-first:border-t-0 group-last:border-b border-gray-300 px-4 py-2',
    ]"
  >
    <slot></slot>
  </td>
</template>
