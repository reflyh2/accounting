<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
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

// Calculate initial period start date
function calculateInitialPeriodStart() {
    if (props.payment) {
        const date = new Date(props.payment.period_start);
        return date.toISOString().split('T')[0];
    }
    
    const existingPayments = props.asset.rental_payments || [];
    const sortedPayments = [...existingPayments]
        .filter(p => p.period_end)
        .sort((a, b) => new Date(b.period_end) - new Date(a.period_end));

    if (sortedPayments.length > 0) {
        // Get the day after the last period end
        const lastPeriodEnd = new Date(sortedPayments[0].period_end);
        const nextDay = new Date(lastPeriodEnd);
        nextDay.setDate(nextDay.getDate() + 1);
        return nextDay.toISOString().split('T')[0];
    }

    // If no payments yet, use today's date
    return new Date().toISOString().split('T')[0];
}

// Calculate initial period end date
function calculateInitialPeriodEnd(startDate) {
    if (props.payment) {
        const date = new Date(props.payment.period_end);
        return date.toISOString().split('T')[0];
    }
    
    const start = new Date(startDate);
    const end = new Date(start);
    end.setMonth(end.getMonth() + 1);
    end.setDate(end.getDate() - 1);
    return end.toISOString().split('T')[0];
}

const initialPeriodStart = calculateInitialPeriodStart();
const initialPeriodEnd = calculateInitialPeriodEnd(initialPeriodStart);

const form = useForm({
    period_start: initialPeriodStart,
    period_end: initialPeriodEnd,
    payment_date: props.payment?.payment_date ? new Date(props.payment.payment_date).toISOString().split('T')[0] : '',
    amount: props.payment?.amount || props.asset.rental_amount || 0,
    notes: props.payment?.notes || '',
    credited_account_id: props.payment?.credited_account_id || '',
    create_another: false,
});

// Watch for period_start changes to update period_end
watch(() => form.period_start, (newStart) => {
    if (!props.payment && newStart) {
        const start = new Date(newStart);
        const end = new Date(start);
        end.setMonth(end.getMonth() + 1);
        end.setDate(end.getDate() - 1);
        form.period_end = end.toISOString().split('T')[0];
    }
});

// Watch for payment_date to require credited_account_id
watch(() => form.payment_date, (newDate) => {
    if (newDate && !form.credited_account_id) {
        form.errors.credited_account_id = 'Akun kas/bank harus dipilih saat mengisi tanggal pembayaran';
    }
});

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    
    if (props.payment) {
        form.put(route('asset-rental-payments.update', [props.payment.id]), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                    // Reset dates for new payment
                    const newStart = calculateInitialPeriodStart();
                    form.period_start = newStart;
                    form.period_end = calculateInitialPeriodEnd(newStart);
                }
            }
        });
    } else {
        form.post(route('asset-rental-payments.store', props.asset.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                    // Reset dates for new payment
                    const newStart = calculateInitialPeriodStart();
                    form.period_start = newStart;
                    form.period_end = calculateInitialPeriodEnd(newStart);
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
                v-model="form.period_start"
                type="date"
                label="Periode Mulai"
                :error="form.errors.period_start"
                required
            />

            <AppInput
                v-model="form.period_end"
                type="date"
                label="Periode Selesai"
                :error="form.errors.period_end"
                required
            />

            <AppInput
                v-model="form.payment_date"
                type="date"
                label="Tanggal Pembayaran"
                hint="Mengisi tanggal pembayaran akan membuat pembayaran sebagai pembayaran yang sudah dibayar"
                :error="form.errors.payment_date"
            />

            <AppSelect
                v-if="form.payment_date"
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
        </div>

        <div class="bg-gray-50 p-4 rounded-lg mb-4">
            <h3 class="font-medium mb-2">Informasi Pembayaran</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p>Biaya Sewa: {{ formatNumber(asset.rental_amount) }}</p>
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
                @click="$inertia.visit(route('asset-rental-payments.index', asset.id))"
            >
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 