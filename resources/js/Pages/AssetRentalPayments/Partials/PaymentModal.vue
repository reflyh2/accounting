<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppModal from '@/Components/AppModal.vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppTextarea from '@/Components/AppTextarea.vue';

const props = defineProps({
    show: Boolean,
    payment: Object,
    asset: Object,
    accounts: {
        type: Array,
        required: true
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    payment_date: new Date().toISOString().split('T')[0],
    notes: props.payment?.notes || '',
    credited_account_id: '',
    amount: props.payment?.amount || 0,
});

// Watch for payment prop changes to update form values
watch(() => props.payment, (newPayment) => {
    if (newPayment) {
        form.amount = newPayment.amount;
        form.notes = newPayment.notes;
    } else {
        form.reset();
    }
}, { immediate: true });

function submit() {
    form.post(route('asset-rental-payments.complete', props.payment?.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            form.reset();
        },
    });
}
</script>

<template>
    <AppModal :show="show" @close="$emit('close')" max-width="md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                Bayar Sewa
            </h2>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <p class="text-sm text-gray-600">Nama Aset</p>
                    <p class="font-medium">{{ asset?.name }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Periode</p>
                    <p class="font-medium">{{ payment?.period_start ? new Date(payment.period_start).toLocaleDateString('id-ID') : '-' }} - {{ payment?.period_end ? new Date(payment.period_end).toLocaleDateString('id-ID') : '-' }}</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <AppInput
                    v-model="form.payment_date"
                    type="date"
                    label="Tanggal Pembayaran"
                    :error="form.errors.payment_date"
                    required
                />

                <AppSelect
                    v-model="form.credited_account_id"
                    :options="accounts.map(account => ({
                        value: account.id,
                        label: account.name
                    }))"
                    label="Akun Kas/Bank"
                    :error="form.errors.credited_account_id"
                    required
                />

                <AppInput
                    v-model="form.amount"
                    label="Jumlah"
                    :number-format="true"
                    :error="form.errors.amount"
                    required
                />

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan"
                    :error="form.errors.notes"
                />

                <div class="mt-6 flex justify-end space-x-3">
                    <AppSecondaryButton @click="$emit('close')">
                        Batal
                    </AppSecondaryButton>
                    <AppPrimaryButton type="submit" :disabled="form.processing">
                        Bayar
                    </AppPrimaryButton>
                </div>
            </form>
        </div>
    </AppModal>
</template> 