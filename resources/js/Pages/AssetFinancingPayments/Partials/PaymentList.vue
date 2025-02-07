<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppModal from '@/Components/AppModal.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    payments: {
        type: Object,
        required: true
    },
    asset: {
        type: Object,
        required: true
    }
});

const confirmingPaymentDeletion = ref(false);
const selectedPayment = ref(null);
const confirmingPaymentUpdate = ref(false);
const paymentDate = ref('');
const paymentNotes = ref('');

function confirmPaymentDeletion(payment) {
    selectedPayment.value = payment;
    confirmingPaymentDeletion.value = true;
}

function deletePayment() {
    router.delete(route('asset-financing-payments.destroy', [selectedPayment.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingPaymentDeletion.value = false;
            selectedPayment.value = null;
        }
    });
}

function confirmPaymentUpdate(payment) {
    selectedPayment.value = payment;
    paymentDate.value = new Date().toISOString().split('T')[0];
    paymentNotes.value = payment.notes || '';
    confirmingPaymentUpdate.value = true;
}

function updatePayment() {
    router.put(route('asset-financing-payments.update', [props.asset.id, selectedPayment.value.id]), {
        payment_date: paymentDate.value,
        notes: paymentNotes.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            confirmingPaymentUpdate.value = false;
            selectedPayment.value = null;
        }
    });
}

const getStatusClass = (status) => {
    switch (status) {
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'overdue':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'paid':
            return 'Lunas';
        case 'overdue':
            return 'Terlambat';
        case 'pending':
            return 'Pending';
        default:
            return status;
    }
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead>
                <tr class="bg-gray-100">
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Jatuh Tempo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Bayar</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catatan</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="payment in payments.data" :key="payment.id">
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ new Date(payment.due_date).toLocaleDateString('id-ID') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ payment.payment_date ? new Date(payment.payment_date).toLocaleDateString('id-ID') : '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ formatNumber(payment.amount) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full', getStatusClass(payment.status)]">
                            {{ getStatusLabel(payment.status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ payment.notes || '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                        <button 
                            v-if="payment.status === 'pending'"
                            @click="confirmPaymentUpdate(payment)" 
                            class="text-indigo-600 hover:text-indigo-900"
                        >
                            Bayar
                        </button>
                        <button 
                            @click="confirmPaymentDeletion(payment)" 
                            class="text-red-600 hover:text-red-900"
                        >
                            Hapus
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Delete Payment Modal -->
        <AppModal :show="confirmingPaymentDeletion" @close="confirmingPaymentDeletion = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Konfirmasi Hapus Pembayaran
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Apakah Anda yakin ingin menghapus pembayaran ini?
                </p>

                <div class="mt-6 flex justify-end">
                    <AppSecondaryButton @click="confirmingPaymentDeletion = false" class="mr-3">
                        Batal
                    </AppSecondaryButton>
                    <AppDangerButton @click="deletePayment">
                        Hapus Pembayaran
                    </AppDangerButton>
                </div>
            </div>
        </AppModal>

        <!-- Update Payment Modal -->
        <AppModal :show="confirmingPaymentUpdate" @close="confirmingPaymentUpdate = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    Catat Pembayaran
                </h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tanggal Bayar</label>
                        <input 
                            type="date" 
                            v-model="paymentDate"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Catatan</label>
                        <textarea 
                            v-model="paymentNotes"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <AppSecondaryButton @click="confirmingPaymentUpdate = false" class="mr-3">
                        Batal
                    </AppSecondaryButton>
                    <AppPrimaryButton @click="updatePayment">
                        Simpan Pembayaran
                    </AppPrimaryButton>
                </div>
            </div>
        </AppModal>
    </div>
</template> 