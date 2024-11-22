<script setup>
import { PrinterIcon } from '@heroicons/vue/24/solid';

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
      class="inline-flex items-center justify-center align-middle w-4 h-4 md:ml-3 text-main-500 hover:text-main-700 focus:outline-none focus:ring-2 focus:ring-main-500 focus:ring-opacity-50"
   >
      <PrinterIcon class="h-4 w-4" />
   </button>
</template>