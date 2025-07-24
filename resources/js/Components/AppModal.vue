<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { Dialog, DialogPanel } from '@headlessui/vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

watch(() => props.show, () => {
    if (props.show) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = null;
    }
});

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = null;
});

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
</script>

<template>
    <Teleport to="body">
        <Dialog :open="show" @close="close" class="relative z-[200]">
            <div class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0">
                <div class="fixed inset-0 transform transition-all" @click="close">
                    <div class="absolute inset-0 bg-gray-500/75"></div>
                </div>

                <transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <DialogPanel
                        class="mb-6 bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:mx-auto"
                        :class="maxWidthClass"
                    >
                        <div v-if="$slots.title" class="px-6 py-4">
                            <h3 class="text-lg font-medium text-gray-900">
                                <slot name="title" />
                            </h3>
                        </div>

                        <div class="px-6 py-4">
                            <slot name="content" />
                        </div>

                        <div v-if="$slots.footer" class="px-6 py-4 bg-gray-50 text-right">
                            <slot name="footer" />
                        </div>
                    </DialogPanel>
                </transition>
            </div>
        </Dialog>
    </Teleport> 
</template> 