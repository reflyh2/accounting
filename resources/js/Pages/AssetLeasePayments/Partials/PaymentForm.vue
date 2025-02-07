<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    asset: Object,
    payment: Object,
});

const emit = defineEmits(['close']);

const form = useForm({
    payment_date: new Date().toISOString().split('T')[0],
    amount: props.payment?.amount || '',
    notes: props.payment?.notes || '',
});

function submitForm() {
    if (props.payment) {
        form.put(route('asset-lease-payments.update', [props.asset.id, props.payment.id]), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    } else {
        form.post(route('asset-lease-payments.store', props.asset.id), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm">
        <h2 class="text-lg font-medium text-gray-900 mb-4">
            {{ payment ? 'Edit Payment' : 'Record Payment' }}
        </h2>

        <div class="space-y-4">
            <AppInput
                v-model="form.payment_date"
                type="date"
                label="Payment Date"
                :error="form.errors.payment_date"
                required
            />

            <AppInput
                v-model="form.amount"
                label="Amount"
                :number-format="true"
                :error="form.errors.amount"
                required
            />

            <AppTextarea
                v-model="form.notes"
                label="Notes"
                :error="form.errors.notes"
            />
        </div>

        <div class="mt-6 flex justify-end">
            <AppSecondaryButton @click="$emit('close')" class="mr-2">
                Cancel
            </AppSecondaryButton>
            <AppPrimaryButton type="submit" :disabled="form.processing">
                {{ payment ? 'Update' : 'Record' }} Payment
            </AppPrimaryButton>
        </div>
    </form>
</template> 