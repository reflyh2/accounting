<script setup>
defineProps({
  sticky: {
    type: Boolean,
    default: false
  }
});
</script>

<template>
  <th 
    :class="[
      'border-l last:border-r border-y border-gray-300 px-4 py-2 bg-gray-100',
      { 'sticky top-0 z-10': sticky }
    ]"
  >
    <slot></slot>
  </th>
</template>
