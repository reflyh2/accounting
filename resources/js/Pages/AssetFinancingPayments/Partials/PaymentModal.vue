<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AppModal from '@/Components/AppModal.vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    show: Boolean,
    payment: {
        type: Object,
        default: null
    },
    asset: {
        type: Object,
        required: true
    },
    accounts: {
        type: Array,
        required: true
    }
});

const emit = defineEmits(['close']);

const form = useForm({
    payment_date: new Date().toISOString().split('T')[0],
    principal_portion: 0,
    interest_portion: 0,
    amount: 0,
    credited_account_id: null,
});

// Watch for payment prop changes to update form values
watch(() => props.payment, (newPayment) => {
    if (newPayment) {
        form.principal_portion = newPayment.principal_portion;
        form.interest_portion = newPayment.interest_portion;
        form.amount = newPayment.amount;
    } else {
        form.reset();
    }
}, { immediate: true });

// Watch for changes in principal and interest portions to update total amount
watch([() => form.principal_portion, () => form.interest_portion], ([newPrincipal, newInterest]) => {
    form.amount = Number(newPrincipal || 0) + Number(newInterest || 0);
});

function submit() {
    form.post(route('asset-financing-payments.complete', props.payment.id), {
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
                {{ props.payment ? 'Edit Pembayaran' : 'Tambah Pembayaran' }}
            </h2>

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
                        label: account.code + ' - ' + account.name
                    }))"
                    label="Bayar dari Akun"
                    :error="form.errors.credited_account_id"
                    required
                />

                <AppInput
                    v-model="form.principal_portion"
                    label="Porsi Pokok"
                    :number-format="true"
                    :error="form.errors.principal_portion"
                    required
                />

                <AppInput
                    v-model="form.interest_portion"
                    label="Porsi Bunga"
                    :number-format="true"
                    :error="form.errors.interest_portion"
                    required
                />

                <AppInput
                    v-model="form.amount"
                    label="Total"
                    :number-format="true"
                    :error="form.errors.amount"
                    disabled
                    required
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