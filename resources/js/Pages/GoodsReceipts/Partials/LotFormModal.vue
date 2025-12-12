<script setup>
import { ref, computed, watch } from 'vue';
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
    lot_code: '',
    mfg_date: '',
    expiry_date: '',
});

const today = new Date().toISOString().split('T')[0];

watch(() => props.show, (newVal) => {
    if (newVal) {
        form.value = {
            lot_code: '',
            mfg_date: today,
            expiry_date: '',
        };
        error.value = '';
    }
});

async function submit() {
    if (!form.value.lot_code.trim()) {
        error.value = 'Kode lot wajib diisi.';
        return;
    }

    isSubmitting.value = true;
    error.value = '';

    try {
        const response = await axios.post(route('api.lots.store'), {
            product_variant_id: props.productVariantId,
            lot_code: form.value.lot_code,
            mfg_date: form.value.mfg_date || null,
            expiry_date: form.value.expiry_date || null,
        });
        emit('created', response.data);
        emit('close');
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal membuat lot.';
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
                    <h3 class="text-lg font-semibold">Tambah Lot Baru</h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
                <form @submit.prevent="submit" class="p-4 space-y-4">
                    <div class="text-sm text-gray-600 mb-2">
                        Produk: <strong>{{ productName }}</strong>
                    </div>

                    <AppInput
                        v-model="form.lot_code"
                        label="Kode Lot:"
                        placeholder="Masukkan kode lot"
                        required
                    />

                    <div class="grid grid-cols-2 gap-4">
                        <AppInput
                            v-model="form.mfg_date"
                            type="date"
                            label="Tanggal Produksi:"
                        />
                        <AppInput
                            v-model="form.expiry_date"
                            type="date"
                            label="Tanggal Kadaluarsa:"
                        />
                    </div>

                    <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>

                    <div class="flex justify-end space-x-2 pt-4">
                        <AppSecondaryButton type="button" @click="$emit('close')">
                            Batal
                        </AppSecondaryButton>
                        <AppPrimaryButton type="submit" :disabled="isSubmitting">
                            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Lot' }}
                        </AppPrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
