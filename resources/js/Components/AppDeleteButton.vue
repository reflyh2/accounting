<script setup>
import { TrashIcon } from '@heroicons/vue/24/solid';

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
      class="inline-flex items-center justify-center align-middle w-4 h-4 ml-3 text-red-500 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50"
   >
      <TrashIcon class="h-4 w-4" />
   </button>
</template>