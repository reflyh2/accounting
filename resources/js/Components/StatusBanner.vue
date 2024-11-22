<script setup>
import { ref } from 'vue';
import { XMarkIcon } from '@heroicons/vue/16/solid';
import { ExclamationCircleIcon, CheckCircleIcon, XCircleIcon, InformationCircleIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
  type: {
    type: String,
    default: 'success',
    validator: (value) => ['success', 'warning', 'error', 'info'].includes(value),
  },
  message: {
    type: String,
    required: true,
  },
  dismissible: {
    type: Boolean,
    default: false,
  }
});

const emit = defineEmits(['close']);

const isVisible = ref(true);

const close = () => {
  isVisible.value = false;
  emit('close');
};
</script>

<template>
  <Transition name="fade">
    <div v-if="isVisible" class="w-full p-4 rounded-md shadow-sm flex items-center align-middle" :class="{
      'bg-green-100 text-green-800': type === 'success',
      'bg-yellow-100 text-yellow-800': type === 'warning',
      'bg-red-100 text-red-800': type === 'error',
      'bg-blue-100 text-blue-800': type === 'info'
    }">
      <component :is="type === 'warning' ? ExclamationCircleIcon : type === 'success' ? CheckCircleIcon : type === 'error' ? XCircleIcon : InformationCircleIcon" class="h-5 w-5 mr-2" />
      <p class="text-sm">
        {{ message }}
      </p>
      <button v-if="dismissible" @click="close" class="text-gray-500 hover:text-gray-700">
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