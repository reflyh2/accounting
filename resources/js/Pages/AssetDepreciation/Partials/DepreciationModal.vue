<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppModal from '@/Components/AppModal.vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    show: Boolean,
    entry: {
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

const isAmortization = computed(() => props.asset.acquisition_type === 'fixed_rental');

const emit = defineEmits(['close']);

const form = useForm({
    journal_date: props.entry ? new Date(props.entry.entry_date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    debit_account_id: isAmortization ? props.asset?.category?.rent_expense_account_id || '' : props.asset?.category?.depreciation_expense_account_id || '',
    credit_account_id: isAmortization ? props.asset?.category?.prepaid_rent_account_id || '' : props.asset?.category?.accumulated_depreciation_account_id || '',
    notes: props.entry?.notes || '',
});

function submit() {
    form.post(route('asset-depreciation.process', props.entry.id), {
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
                {{ isAmortization ? 'Proses Entri Amortisasi' : 'Proses Entri Penyusutan' }}
            </h2>

            <form @submit.prevent="submit" class="space-y-4">
                <AppInput
                    v-model="form.journal_date"
                    type="date"
                    label="Tanggal Jurnal"
                    :error="form.errors.journal_date"
                    required
                    disabled
                />

                <AppSelect
                    v-model="form.debit_account_id"
                    :options="accounts
                        .filter(a => isAmortization ? a.type === 'beban_amortisasi' : a.type === 'beban_penyusutan')
                        .map(account => ({
                            value: account.id,
                            label: account.code + ' - ' + account.name
                        }))
                    "
                    :label="isAmortization ? 'Akun Beban Amortisasi (Debit)' : 'Akun Beban Penyusutan (Debit)'"
                    :error="form.errors.debit_account_id"
                    required
                />

                <AppSelect
                    v-model="form.credit_account_id"
                    :options="accounts
                        .filter(a => isAmortization ? a.type === 'aset_lancar_lainnya' : a.type === 'akumulasi_penyusutan')
                        .map(account => ({
                            value: account.id,
                            label: account.code + ' - ' + account.name
                        }))
                    "
                    :label="isAmortization ? 'Akun Sewa Dibayar Dimuka (Kredit)' : 'Akun Akumulasi Penyusutan (Kredit)'"
                    :error="form.errors.credit_account_id"
                    required
                />

                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <h3 class="font-medium mb-2">{{ isAmortization ? 'Informasi Amortisasi' : 'Informasi Penyusutan' }}</h3>
                    <div class="grid grid-cols-1 gap-2 text-sm">
                        <p>Aset: {{ asset?.name }}</p>
                        <p>Periode: {{ new Date(entry?.period_start).toLocaleDateString('id-ID') }} - {{ new Date(entry?.period_end).toLocaleDateString('id-ID') }}</p>
                        <p>Jumlah: {{ formatNumber(entry?.amount) }}</p>
                    </div>
                </div>

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
                        {{ isAmortization ? 'Proses Amortisasi' : 'Proses Penyusutan' }}
                    </AppPrimaryButton>
                </div>
            </form>
        </div>
    </AppModal>
</template> 