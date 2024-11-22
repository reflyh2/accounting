<script setup>
import { defineProps, defineEmits } from 'vue';

defineProps({
    show: {
        type: Boolean,
        required: true
    },
    title: {
        type: String,
        default: 'Konfirmasi Hapus'
    },
    message: {
        type: String,
        default: 'Apakah Anda yakin ingin menghapus data ini secara permanen?'
    }
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <div v-if="show" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ title }}</h3>
            <p class="mb-4 text-gray-600 text-sm">{{ message }}</p>
            <div class="flex justify-end space-x-2">
                <button @click="$emit('close')" class="px-4 py-2 text-sm bg-gray-300 text-gray-800 rounded hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Batal
                </button>
                <button @click="$emit('confirm')" class="px-4 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                    Hapus
                </button>
            </div>
        </div>
    </div>
</template>