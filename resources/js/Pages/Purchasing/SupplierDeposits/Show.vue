<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppModal from '@/Components/AppModal.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    deposit: Object,
    partnerId: Number,
    accounts: Array,
});

const showRefund = ref(false);
const refundAmount = ref(null);

const showConsume = ref(false);
const consumeForm = useForm({
    amount: null,
    account_id: '',
    consumed_at: new Date().toISOString().substr(0, 10),
    notes: '',
});

const canRefund = computed(() => Number(props.deposit.balance) > 0);

const hasInsufficientBalance = computed(() => {
    return consumeForm.amount && Number(consumeForm.amount) > Number(props.deposit.balance);
});

function statusLabel(status) {
    return { open: 'Saldo Tersedia', exhausted: 'Habis Dipakai', refunded: 'Direfund' }[status] || status;
}

function statusClass(status) {
    return {
        open: 'bg-green-100 text-green-800',
        exhausted: 'bg-gray-200 text-gray-700',
        refunded: 'bg-amber-100 text-amber-800',
    }[status] || 'bg-gray-100';
}

function formatDate(v) {
    if (!v) return '-';
    return new Date(v).toLocaleDateString('id-ID', { dateStyle: 'medium' });
}

function formatDateTime(v) {
    if (!v) return '-';
    return new Date(v).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

function submitRefund() {
    router.post(route('supplier-deposits.refund', props.deposit.id),
        refundAmount.value ? { amount: Number(refundAmount.value) } : {},
        {
            preserveScroll: true,
            onSuccess: () => { showRefund.value = false; refundAmount.value = null; },
        }
    );
}

function submitConsume() {
    consumeForm.post(route('supplier-deposits.consume-custom', props.deposit.id), {
        preserveScroll: true,
        onSuccess: () => {
            showConsume.value = false;
            consumeForm.reset();
        },
    });
}
</script>

<template>
    <Head :title="`Deposit ${deposit.deposit_number}`" />

    <AuthenticatedLayout>
        <template #header><h2>Detail Deposit Pemasok</h2></template>

        <div class="mx-auto bg-white shadow-sm sm:rounded border border-gray-200 p-6 space-y-4">
            <AppBackLink :href="route('supplier-deposits.supplier-detail', props.partnerId)" text="Kembali ke Detail Pemasok" />

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h3 class="text-lg font-bold">{{ deposit.deposit_number }}</h3>
                    <span :class="statusClass(deposit.status)" class="px-2 py-0.5 rounded text-sm">
                        {{ statusLabel(deposit.status) }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <AppPrimaryButton v-if="canRefund" @click="showConsume = true">Gunakan Deposit</AppPrimaryButton>
                    <AppDangerButton v-if="canRefund" @click="showRefund = true">Refund</AppDangerButton>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
                <div><strong>Pemasok:</strong> {{ deposit.partner?.code }} — {{ deposit.partner?.name }}</div>
                <div><strong>Perusahaan:</strong> {{ deposit.company?.name }}</div>
                <div><strong>Cabang:</strong> {{ deposit.branch?.name || '—' }}</div>
                <div><strong>Tanggal:</strong> {{ formatDate(deposit.deposit_date) }}</div>
                <div><strong>Jumlah:</strong> {{ formatNumber(deposit.amount) }} {{ deposit.currency?.code }}</div>
                <div><strong>Saldo Sisa:</strong> {{ formatNumber(deposit.balance) }} {{ deposit.currency?.code }}</div>
                <div><strong>Metode Pembayaran:</strong> {{ deposit.payment_method || '—' }}</div>
                <div><strong>Bank:</strong> {{ deposit.company_bank_account?.bank_name || '—' }}</div>
                <div><strong>Akun Uang Muka:</strong> {{ deposit.advance_account?.code }} - {{ deposit.advance_account?.name }}</div>
                <div><strong>Akun Pembayaran:</strong> {{ deposit.payment_account?.code }} - {{ deposit.payment_account?.name }}</div>
                <div v-if="deposit.refunded_at"><strong>Direfund:</strong> {{ formatNumber(deposit.refunded_amount) }} pada {{ formatDateTime(deposit.refunded_at) }}</div>
            </div>

            <div v-if="deposit.notes" class="text-sm bg-gray-50 p-3 rounded">{{ deposit.notes }}</div>

            <h4 class="text-lg font-semibold mt-4">Riwayat Konsumsi</h4>
            <table class="min-w-full text-sm border border-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 border text-left">Tanggal</th>
                        <th class="px-3 py-2 border text-left">Dikonsumsi Oleh</th>
                        <th class="px-3 py-2 border text-right">Jumlah</th>
                        <th class="px-3 py-2 border text-left">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in deposit.consumptions" :key="c.id" class="border-b">
                        <td class="px-3 py-2 border">{{ formatDateTime(c.consumed_at) }}</td>
                        <td class="px-3 py-2 border text-xs">
                            <template v-if="c.consumed_by_type === 'App\\Models\\Journal'">
                                <Link :href="route('journals.show', c.consumed_by_id)" class="text-blue-600 hover:underline">
                                    Jurnal #{{ c.consumed_by_id }}
                                </Link>
                            </template>
                            <template v-else-if="c.consumed_by_type === 'App\\Models\\SalesInvoice'">
                                <Link :href="route('sales-invoices.show', c.consumed_by_id)" class="text-blue-600 hover:underline">
                                    Invoice #{{ c.consumed_by_id }}
                                </Link>
                            </template>
                            <template v-else>
                                {{ c.consumed_by_type.split('\\').pop() }}#{{ c.consumed_by_id }}
                            </template>
                        </td>
                        <td class="px-3 py-2 border text-right">{{ formatNumber(c.amount) }}</td>
                        <td class="px-3 py-2 border">{{ c.notes || '—' }}</td>
                    </tr>
                    <tr v-if="!deposit.consumptions?.length">
                        <td colspan="4" class="px-3 py-3 text-center text-gray-500">Belum ada konsumsi.</td>
                    </tr>
                </tbody>
            </table>
        </div>

      <AppModal :show="showRefund" @close="showRefund = false">
    <template #title>
        Refund Deposit
    </template>

    <template #content>
        <div class="mb-4">
            <p class="text-sm text-gray-500">
                Sisa Saldo
            </p>

            <p class="text-3xl font-bold text-green-600">
                {{ formatNumber(deposit.balance) }}
            </p>
        </div>

        <AppInput
            v-model="refundAmount"
            :numberFormat="true"
            label="Jumlah Refund (opsional):"
        />

        <p class="text-sm text-red-500 italic mt-1">
            * Kosongkan jumlah jika ingin melakukan refund seluruh saldo.
        </p>
    </template>

    <template #footer>
        <div class="flex justify-end gap-2">
            <AppSecondaryButton @click="showRefund = false">
                Batal
            </AppSecondaryButton>

            <AppPrimaryButton @click="submitRefund">
                Refund
            </AppPrimaryButton>
        </div>
    </template>
</AppModal>

<AppModal :show="showConsume" @close="showConsume = false">
    <template #title>
        Gunakan Deposit
    </template>

    <template #content>
        <div class="mb-4">
            <p class="text-sm text-gray-500">
                Sisa Saldo
            </p>

            <p class="text-3xl font-bold text-green-600">
                {{ formatNumber(deposit.balance) }}
            </p>
        </div>

        <div class="space-y-4">
            <AppInput
                v-model="consumeForm.consumed_at"
                type="date"
                label="Tanggal Penggunaan:"
                :error="consumeForm.errors.consumed_at"
                required
            />

            <AppSelect
                v-model="consumeForm.account_id"
                :options="accounts.map(acc => ({ value: acc.id, label: `${acc.code} - ${acc.name}` }))"
                label="Akun Tujuan (Debit):"
                :error="consumeForm.errors.account_id"
                :inModal="true"
                required
            />

            <AppInput
                v-model="consumeForm.amount"
                :numberFormat="true"
                label="Jumlah Penggunaan:"
                :error="consumeForm.errors.amount"
                required
            />
            <p v-if="hasInsufficientBalance" class="text-xs text-red-600 -mt-0 mb-2 italic font-light">
                Saldo tidak mencukupi
            </p>

            <AppInput
                v-model="consumeForm.notes"
                label="Catatan / Keterangan:"
                :error="consumeForm.errors.notes"
            />
        </div>
    </template>

    <template #footer>
        <div class="flex justify-end gap-2">
            <AppSecondaryButton @click="showConsume = false">
                Batal
            </AppSecondaryButton>

            <AppPrimaryButton @click="submitConsume" :disabled="consumeForm.processing || hasInsufficientBalance">
                Gunakan
            </AppPrimaryButton>
        </div>
    </template>
</AppModal>
    </AuthenticatedLayout>
</template>
