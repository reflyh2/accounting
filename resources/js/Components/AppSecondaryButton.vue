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
      class="px-3 py-2 border border-gray-400 bg-white text-gray-900 text-sm rounded hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-opacity-50"
   >
      <span class="flex items-center">
         <slot></slot>
      </span>
   </button>
</template>