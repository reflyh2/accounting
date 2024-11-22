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
      class="px-3 py-2 bg-white border border-red-500 text-red-700 text-sm rounded hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-50 focus:ring-opacity-50"
   >
      <span class="flex items-center">
         <slot></slot>
      </span>
   </button>
</template>