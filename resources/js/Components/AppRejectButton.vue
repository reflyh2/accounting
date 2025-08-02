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
      class="px-3 py-2 border border-red-600 bg-red-500 text-white text-sm rounded hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50"
   >
      <span class="flex items-center">
         <slot></slot>
      </span>
   </button>
</template>