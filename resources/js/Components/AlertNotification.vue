<script setup>
import { onMounted, ref } from 'vue';
import { XMarkIcon } from '@heroicons/vue/16/solid';

const props = defineProps({
  type: {
    type: String,
    default: 'success',
    validator: (value) => ['success', 'warning', 'error'].includes(value),
  },
  message: {
    type: String,
    required: true,
  },
  duration: {
    type: Number,
    default: 5000, // 5 seconds
  },
});

const emit = defineEmits(['close']);

const isVisible = ref(true);

const close = () => {
  isVisible.value = false;
  emit('close');
};

onMounted(() => {
  if (props.duration > 0) {
    setTimeout(close, props.duration);
  }
});
</script>

<template>
  <Transition name="fade">
    <div v-if="isVisible" class="fixed top-4 left-1/2 transform p-4 z-50 rounded-md shadow-md flex items-center" :class="{
      'bg-green-100 text-green-800': type === 'success',
      'bg-yellow-100 text-yellow-800': type === 'warning',
      'bg-red-100 text-red-800': type === 'error'
    }">
      <p class="mr-2 text-sm">{{ message }}</p>
      <button @click="close" class="text-gray-500 text-sm hover:text-gray-700">
        <XMarkIcon class="h-4 w-4" />
      </button>
    </div>
  </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>