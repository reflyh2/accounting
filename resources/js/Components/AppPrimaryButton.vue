<script setup>
defineProps({
   type: {
      type: String,
      default: 'button'
   }
});
</script>

<template>
   <button
      :type="type"
      class="px-3 py-2 border border-main-600 bg-main-500 text-white text-sm rounded hover:bg-main-600 focus:outline-none focus:ring-2 focus:ring-main-500 focus:ring-opacity-50"
   >
      <span class="flex items-center">
         <slot></slot>
      </span>
   </button>
</template>