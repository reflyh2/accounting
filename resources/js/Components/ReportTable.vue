<script setup>
defineProps({
  maxHeight: {
    type: String,
    default: 'none'
  }
});
</script>

<template>
  <div :style="{ maxHeight, overflowY: maxHeight !== 'none' ? 'auto' : 'visible' }">
    <table class="relative w-full border-separate border-spacing-0 border-none text-sm">
      <slot></slot>
    </table>
  </div>
</template>
