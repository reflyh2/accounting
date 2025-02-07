<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    asset: {
        type: Object,
        required: true
    },
    payment: {
        type: Object,
        default: null
    },
    accounts: {
        type: Array,
        required: true
    }
});

// Calculate next due date
const nextDueDate = computed(() => {
    if (props.payment) return (new Date(props.payment.due_date).toISOString().split('T')[0]);
    if (!props.asset.first_payment_date) return '';

    try {
        const startDate = new Date(props.asset.first_payment_date);
        if (isNaN(startDate.getTime())) return ''; // Invalid date

        const existingPayments = props.asset.financing_payments || [];
        const sortedPayments = [...existingPayments]
            .filter(p => p.due_date) // Filter out payments without due date
            .sort((a, b) => new Date(b.due_date) - new Date(a.due_date));
        
        let nextDate;
        if (sortedPayments.length > 0) {
            // Get the last due date and add one month
            const lastDueDate = new Date(sortedPayments[0].due_date);
            if (isNaN(lastDueDate.getTime())) return ''; // Invalid date
            
            nextDate = new Date(lastDueDate);
            nextDate.setDate(1); // Set to first of month to avoid invalid dates
            nextDate.setMonth(nextDate.getMonth() + 1);
            // Set the day back to match the original due date day
            nextDate.setDate(lastDueDate.getDate());
        } else {
            // If no payments yet, use the start date plus one month
            nextDate = new Date(startDate);
            nextDate.setDate(1); // Set to first of month to avoid invalid dates
            nextDate.setMonth(nextDate.getMonth() + 1);
            // Set the day back to match the original start date day
            nextDate.setDate(startDate.getDate());
        }

        // Format the date as YYYY-MM-DD
        return nextDate.toISOString().split('T')[0];
    } catch (error) {
        console.error('Error calculating next due date:', error);
        return '';
    }
});

// Calculate monthly interest rate
const monthlyRate = computed(() => (props.asset.interest_rate || 0) / 12 / 100);

// Get remaining obligation
const remainingObligation = computed(() => {
    const paidPayments = props.asset.financing_payments?.filter(p => p.status === 'paid') || [];
    const totalPaidPrincipal = paidPayments.reduce((sum, payment) => {
        return sum + (Number(payment.principal_portion) || 0);
    }, 0);
    return (props.asset.financing_amount || 0) - totalPaidPrincipal;
});

// Calculate initial values for a new payment
const initialPrincipalPortion = computed(() => {
    if (props.payment) return props.payment.principal_portion;
    return remainingObligation.value / (props.asset.financing_term_months || 1);
});

const initialInterestPortion = computed(() => {
    if (props.payment) return props.payment.interest_portion;
    return remainingObligation.value * monthlyRate.value;
});

const form = useForm({
    due_date: props.payment !== null && props.payment.due_date !== null ? new Date(props.payment.due_date).toISOString().split('T')[0] : nextDueDate.value,
    payment_date: props.payment !== null && props.payment.payment_date !== null ? new Date(props.payment.payment_date).toISOString().split('T')[0] : '',
    principal_portion: props.payment?.principal_portion || initialPrincipalPortion.value,
    interest_portion: props.payment?.interest_portion || initialInterestPortion.value,
    amount: props.payment?.amount || (initialPrincipalPortion.value + initialInterestPortion.value),
    notes: props.payment?.notes || '',
    credited_account_id: props.payment?.credited_account_id || '',
    create_another: false,
});

// Watch for changes in principal and interest portions to update total amount
watch([() => form.principal_portion, () => form.interest_portion], ([newPrincipal, newInterest]) => {
    form.amount = Number(newPrincipal || 0) + Number(newInterest || 0);
});

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    
    if (props.payment) {
        form.put(route('asset-financing-payments.update', [props.payment.id]), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                    // Reset due date to next month for new payment
                    form.due_date = nextDueDate.value;
                }
            }
        });
    } else {
        form.post(route('asset-financing-payments.store', props.asset.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                    // Reset due date to next month for new payment
                    form.due_date = nextDueDate.value;
                }
            }
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <AppInput
                v-model="form.due_date"
                type="date"
                label="Tanggal Jatuh Tempo"
                :error="form.errors.due_date"
                required
            />

            <AppInput
                v-model="form.payment_date"
                type="date"
                label="Tanggal Pembayaran"
                hint="Mengisi tanggal pembayaran akan membuat pembayaran sebagai pembayaran yang sudah dibayar"
                :error="form.errors.payment_date"
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

            <AppSelect
                v-if="form.payment_date"
                v-model="form.credited_account_id"
                :options="accounts.map(account => ({
                    value: account.id,
                    label: account.code + ' - ' + account.name
                }))"
                label="Bayar dari Akun"
                :error="form.errors.credited_account_id"
                required
            />
        </div>

        <div class="bg-gray-50 p-4 rounded-lg mb-4">
            <h3 class="font-medium mb-2">Informasi Pembayaran</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p>Sisa Kewajiban: {{ formatNumber(remainingObligation) }}</p>
                    <p>Suku Bunga per Bulan: {{ (monthlyRate * 100).toFixed(2) }}%</p>
                </div>
                <div>
                    <p>Pembayaran per Bulan: {{ formatNumber(form.amount) }}</p>
                    <p>Sisa Tenor: {{ props.asset.financing_term_months || 0 }} bulan</p>
                </div>
            </div>
        </div>

        <AppTextarea
            v-model="form.notes"
            label="Catatan"
            :error="form.errors.notes"
        />

        <div class="flex items-center mt-4">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ payment ? 'Simpan Perubahan' : 'Simpan' }}
            </AppPrimaryButton>
            <AppUtilityButton
                v-if="!payment"
                type="button"
                @click="submitForm(true)"
                class="mr-2"
            >
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton
                @click="$inertia.visit(route('asset-financing-payments.index', asset.id))"
            >
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 