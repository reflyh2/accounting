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
      class="px-3 py-2 border border-green-600 bg-green-500 text-white text-sm rounded hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50"
   >
      <span class="flex items-center">
         <slot></slot>
      </span>
   </button>
</template>