<script setup>
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router, usePage } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppModal from '@/Components/AppModal.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppInput from '@/Components/AppInput.vue';
import { DocumentStatusKind, getDocumentStatusMeta } from '@/constants/documentStatuses';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    booking: Object,
    filters: Object,
    allowedTransitions: Array,
    paymentMethods: Array,
    companyBankAccounts: Array,
});

const page = usePage();

const isSuperAdmin = computed(() => {
    return page.props.auth.tenantUser?.roles?.some(role => role.id === 1) || false;
});

const form = useForm({});
const showDeleteConfirmation = ref(false);
const showCancelModal = ref(false);
const cancelReason = ref('');
const editingLineId = ref(null);
const editForm = useForm({
    supplier_cost: 0,
});

const showAddDepositForm = ref(false);
const depositForm = useForm({
    amount: 0,
    payment_method: null,
    company_bank_account_id: null,
    received_at: new Date().toISOString().split('T')[0],
    notes: '',
});

const canAddOrDeleteDeposit = computed(() => {
    return !props.booking.has_invoice;
});

const totalDepositSum = computed(() => {
    return (props.booking.deposits || []).reduce((sum, deposit) => sum + parseFloat(deposit.amount || 0), 0);
});

const totalAmountSum = computed(() => {
    return (props.booking.lines || []).reduce((sum, line) => sum + parseFloat(line.amount || 0), 0);
});

const totalSupplierCostSum = computed(() => {
    return (props.booking.lines || []).reduce((sum, line) => sum + parseFloat(line.supplier_cost || 0), 0);
});

const depositMethodNeedsBank = computed(() => {
    if (!depositForm.payment_method) return false;
    return depositForm.payment_method !== 'cash';
});

const depositBankAccountOptions = computed(() => {
    return (props.companyBankAccounts || [])
        .map((b) => ({ value: b.id, label: b.label }));
});

watch(() => depositForm.payment_method, (newMethod) => {
    if (newMethod === 'cash') {
        depositForm.company_bank_account_id = null;
    }
});

function submitDeposit() {
    depositForm.post(route('booking-deposits.store', props.booking.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddDepositForm.value = false;
            depositForm.reset();
        },
    });
}

function deleteDeposit(depositId) {
    if (confirm('Apakah Anda yakin ingin menghapus deposit ini?')) {
        router.delete(route('booking-deposits.destroy', [props.booking.id, depositId]), {
            preserveScroll: true,
        });
    }
}

function startEdit(line) {
    editingLineId.value = line.id;
    editForm.supplier_cost = parseFloat(line.supplier_cost || 0);
}

function cancelEdit() {
    editingLineId.value = null;
    editForm.clearErrors();
}

function saveSupplierCost(line) {
    editForm.patch(route('bookings.update-supplier-cost', line.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingLineId.value = null;
        },
    });
}

const statusMeta = getDocumentStatusMeta(DocumentStatusKind.BOOKING, props.booking.status);

function deleteBooking() {
    form.delete(route('bookings.destroy', props.booking.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
}

function confirmBooking() {
    router.post(route('bookings.confirm', props.booking.id), {}, { preserveScroll: true });
}

function checkInBooking() {
    router.post(route('bookings.check-in', props.booking.id), {}, { preserveScroll: true });
}

function checkOutBooking() {
    router.post(route('bookings.check-out', props.booking.id), {}, { preserveScroll: true });
}

function cancelBooking() {
    router.post(route('bookings.cancel', props.booking.id), { reason: cancelReason.value }, {
        preserveScroll: true,
        onSuccess: () => {
            showCancelModal.value = false;
            cancelReason.value = '';
        },
    });
}

function convertBooking() {
    router.post(route('bookings.convert', props.booking.id), {}, { preserveScroll: true });
}

const convertibleStatuses = ['confirmed', 'checked_in', 'checked_out', 'completed'];

function formatDateTime(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
function formatDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
    });
}
</script>

<template>
    <Head title="Detail Booking" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Booking</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('bookings.index', filters)" text="Kembali ke Daftar Booking" />
                        </div>

                        <div class="flex justify-between items-center mb-4">
                            <div class="flex items-center gap-3">
                                <h3 class="text-lg font-bold">{{ booking.booking_number }}</h3>
                                <span :class="['px-2 py-0.5 rounded text-sm', statusMeta.classes]">
                                    {{ statusMeta.label }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <Link v-if="booking.status === 'hold'" :href="route('bookings.edit', booking.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton 
                                    v-if="booking.status === 'hold' || booking.status === 'canceled'" 
                                    @click="showDeleteConfirmation = true" 
                                    title="Delete" 
                                />
                            </div>
                        </div>

                        <!-- Lifecycle Actions -->
                        <div v-if="allowedTransitions.length || convertibleStatuses.includes(booking.status)" class="mb-6 flex gap-2 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <AppPrimaryButton v-if="allowedTransitions.includes('confirm')" @click="confirmBooking">
                                Konfirmasi
                            </AppPrimaryButton>
                            <AppPrimaryButton v-if="allowedTransitions.includes('check_in')" @click="checkInBooking">
                                Check-in
                            </AppPrimaryButton>
                            <AppPrimaryButton v-if="allowedTransitions.includes('check_out')" @click="checkOutBooking">
                                Check-out
                            </AppPrimaryButton>
                            <AppPrimaryButton
                                v-if="convertibleStatuses.includes(booking.status) && !booking.converted_sales_order_id"
                                @click="convertBooking"
                            >
                                Konversi ke Sales Order
                            </AppPrimaryButton>
                            <Link
                                v-if="booking.converted_sales_order_id"
                                :href="route('sales-orders.show', booking.converted_sales_order_id)"
                                class="text-main-500 underline self-center"
                            >
                                Lihat SO terkait →
                            </Link>
                            <AppDangerButton v-if="allowedTransitions.includes('cancel')" @click="showCancelModal = true">
                                Batalkan
                            </AppDangerButton>
                        </div>

                        <!-- Booking Info -->
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Pelanggan:</p>
                                <p>{{ booking.partner?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tipe Booking:</p>
                                <p>{{ booking.booking_type === 'accommodation' ? 'Akomodasi' : 'Rental' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Booking:</p>
                                <p>{{ formatDate(booking.booked_at) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Mata Uang:</p>
                                <p>{{ booking.currency?.code || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Hold Sampai:</p>
                                <p>{{ booking.held_until ? formatDateTime(booking.held_until) : '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Deposit:</p>
                                <p>{{ formatNumber(totalDepositSum) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Channel:</p>
                                <p>{{ booking.source_channel || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Dibuat Oleh:</p>
                                <p>{{ booking.creator?.name || '-' }}</p>
                            </div>
                        </div>

                        <div v-if="booking.notes" class="mt-4">
                            <p class="font-semibold text-sm">Catatan:</p>
                            <p class="text-sm">{{ booking.notes }}</p>
                        </div>

                        <!-- Booking Lines -->
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Baris Booking</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Produk</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Pool</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Instance</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Mulai</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Selesai</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-right">Qty</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-right">Harga</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-right">Amount</th>
                                        <th v-if="booking.fulfillment_mode === 'reseller' && isSuperAdmin" class="bg-gray-100 border border-gray-300 px-4 py-2 text-right">Harga Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in booking.lines" :key="line.id" class="group">
                                        <td class="border border-gray-300 px-4 py-2">{{ line.product?.name || '-' }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.pool?.name || '-' }}</td>
                                        <td class="border border-gray-300 px-4 py-2">
                                            <span v-if="line.assigned_instance">{{ line.assigned_instance.code }}</span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2">{{ formatDateTime(line.start_datetime) }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ formatDateTime(line.end_datetime) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ line.qty }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.unit_price) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.amount) }}</td>
                                        <td v-if="booking.fulfillment_mode === 'reseller' && isSuperAdmin" class="border border-gray-300 px-4 py-2 text-right">
                                            <div class="flex flex-col items-end gap-1">
                                                <div class="flex items-center gap-2">
                                                    <span v-if="editingLineId !== line.id">{{ formatNumber(line.supplier_cost) }}</span>
                                                    <AppInput
                                                        v-else
                                                        v-model="editForm.supplier_cost"
                                                        :numberFormat="true"
                                                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                                        class="w-28 text-sm"
                                                    />
                                                    
                                                    <span v-if="line.settled_by_type" class="px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded-full" title="Sudah dibuatkan faktur pembelian">Settled</span>
                                                    
                                                    <template v-else-if="booking.status !== 'hold'">
                                                         <AppEditButton title="Edit"  v-if="editingLineId !== line.id" 
                                                            @click="startEdit(line)" />
                                                        <div v-else class="flex gap-2">
                                                            <button 
                                                                @click="saveSupplierCost(line)" 
                                                                class="text-green-600 hover:text-green-800 text-xs font-semibold"
                                                                :disabled="editForm.processing"
                                                            >
                                                                Simpan
                                                            </button>
                                                            <button 
                                                                @click="cancelEdit" 
                                                                class="text-gray-500 hover:text-gray-700 text-xs font-semibold"
                                                            >
                                                                Batal
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                                <div v-if="editingLineId === line.id && editForm.errors.supplier_cost" class="text-red-500 text-xs">
                                                    {{ editForm.errors.supplier_cost }}
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="7" class="border border-gray-300 px-4 py-2 font-semibold text-right">Total</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(totalAmountSum) }}</td>
                                        <td v-if="booking.fulfillment_mode === 'reseller' && isSuperAdmin" class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(totalSupplierCostSum) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Booking Deposits -->
                        <div class="mt-8">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="text-lg font-semibold">Deposit Diterima</h4>
                                <AppPrimaryButton v-if="canAddOrDeleteDeposit && !showAddDepositForm" @click="showAddDepositForm = true">
                                    + Tambah Deposit
                                </AppPrimaryButton>
                            </div>

                            <!-- Form inline tambah deposit -->
                            <div v-if="showAddDepositForm" class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <h5 class="font-semibold text-sm mb-3">Tambah Catatan Deposit</h5>
                                <form @submit.prevent="submitDeposit">
                                    <div class="grid grid-cols-2 gap-4">
                                        <AppInput
                                            v-model="depositForm.amount"
                                            :numberFormat="true"
                                            label="Jumlah Deposit:"
                                            required
                                            :error="depositForm.errors.amount"
                                        />

                                        <AppInput
                                            v-model="depositForm.received_at"
                                            type="date"
                                            label="Tanggal Terima:"
                                            required
                                            :error="depositForm.errors.received_at"
                                        />

                                        <AppSelect
                                            v-model="depositForm.payment_method"
                                            :options="paymentMethods"
                                            placeholder="Pilih Metode"
                                            label="Metode Pembayaran:"
                                            :error="depositForm.errors.payment_method"
                                        />

                                        <AppSelect
                                            v-if="depositMethodNeedsBank"
                                            v-model="depositForm.company_bank_account_id"
                                            :options="depositBankAccountOptions"
                                            placeholder="Pilih Rekening Penerima"
                                            label="Rekening Bank:"
                                            :error="depositForm.errors.company_bank_account_id"
                                        />
                                    </div>

                                    <AppTextarea
                                        v-model="depositForm.notes"
                                        label="Catatan:"
                                        rows="2"
                                        class="mt-3"
                                        :error="depositForm.errors.notes"
                                    />

                                    <div class="mt-4 flex gap-2">
                                        <AppPrimaryButton type="submit" :disabled="depositForm.processing">
                                            Simpan Deposit
                                        </AppPrimaryButton>
                                        <AppSecondaryButton type="button" @click="showAddDepositForm = false">
                                            Batal
                                        </AppSecondaryButton>
                                    </div>
                                </form>
                            </div>

                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-left">No</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-left">Tanggal Terima</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-left">Metode</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-left">Rekening Bank</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-left">Catatan</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-right">Jumlah</th>
                                        <th v-if="canAddOrDeleteDeposit" class="bg-gray-100 border border-gray-300 px-4 py-2 text-center w-20">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!booking.deposits || booking.deposits.length === 0">
                                        <td :colspan="canAddOrDeleteDeposit ? 7 : 6" class="border border-gray-300 px-4 py-4 text-center text-gray-400">
                                            Belum ada data deposit yang diterima.
                                        </td>
                                    </tr>
                                    <tr v-else v-for="(deposit, idx) in booking.deposits" :key="deposit.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ idx + 1 }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ formatDate(deposit.received_at) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 capitalize">{{ deposit.payment_method || '-' }}</td>
                                        <td class="border border-gray-300 px-4 py-2">
                                            {{ deposit.company_bank_account ? deposit.company_bank_account.bank_name + ' - ' + deposit.company_bank_account.account_number : '-' }}
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2">{{ deposit.notes || '-' }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(deposit.amount) }}</td>
                                        <td v-if="canAddOrDeleteDeposit" class="border border-gray-300 px-4 py-2 text-center">
                                            <AppDeleteButton 
                                                @click="deleteDeposit(deposit.id)"
                                                title="Delete" 
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td :colspan="canAddOrDeleteDeposit ? 5 : 5" class="border border-gray-300 px-4 py-2 font-semibold text-right">Total Deposit Diterima</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">
                                            {{ formatNumber(totalDepositSum) }}
                                        </td>
                                        <td v-if="canAddOrDeleteDeposit" class="border border-gray-300 px-4 py-2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Booking"
            @close="showDeleteConfirmation = false"
            @confirm="deleteBooking"
        />

        <AppModal :show="showCancelModal" @close="showCancelModal = false" maxWidth="md">
            <template #title>
                Batalkan Booking
            </template>

            <template #content>
                <AppTextarea
                    v-model="cancelReason"
                    label="Alasan Pembatalan:"
                    rows="3"
                    required
                />
            </template>

            <template #footer>
                <button
                    type="button"
                    @click="showCancelModal = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 mr-2"
                >
                    Batal
                </button>

                <AppDangerButton
                    @click="cancelBooking"
                    :disabled="!cancelReason"
                >
                    Konfirmasi Pembatalan
                </AppDangerButton>
            </template>
        </AppModal>
    </AuthenticatedLayout>
</template>
