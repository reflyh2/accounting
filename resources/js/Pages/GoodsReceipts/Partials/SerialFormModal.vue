<script setup>
import { ref, watch } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { XMarkIcon } from '@heroicons/vue/24/solid';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    productVariantId: Number,
    productName: String,
});

const emit = defineEmits(['close', 'created']);

const isSubmitting = ref(false);
const error = ref('');

const form = ref({
    serial_no: '',
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        form.value = {
            serial_no: '',
        };
        error.value = '';
    }
});

async function submit() {
    if (!form.value.serial_no.trim()) {
        error.value = 'Nomor serial wajib diisi.';
        return;
    }

    isSubmitting.value = true;
    error.value = '';

    try {
        const response = await axios.post(route('api.serials.store'), {
            product_variant_id: props.productVariantId,
            serial_no: form.value.serial_no,
        });
        emit('created', response.data);
        emit('close');
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal membuat serial.';
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="$emit('close')"></div>
            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-lg font-semibold">Tambah Serial Baru</h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
                <form @submit.prevent="submit" class="p-4 space-y-4">
                    <div class="text-sm text-gray-600 mb-2">
                        Produk: <strong>{{ productName }}</strong>
                    </div>

                    <AppInput
                        v-model="form.serial_no"
                        label="Nomor Serial:"
                        placeholder="Masukkan nomor serial"
                        required
                    />

                    <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>

                    <div class="flex justify-end space-x-2 pt-4">
                        <AppSecondaryButton type="button" @click="$emit('close')">
                            Batal
                        </AppSecondaryButton>
                        <AppPrimaryButton type="submit" :disabled="isSubmitting">
                            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Serial' }}
                        </AppPrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
