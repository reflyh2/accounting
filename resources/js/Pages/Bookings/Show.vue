<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppModal from '@/Components/AppModal.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import { DocumentStatusKind, getDocumentStatusMeta } from '@/constants/documentStatuses';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    booking: Object,
    filters: Object,
    allowedTransitions: Array,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);
const showCancelModal = ref(false);
const cancelReason = ref('');

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

function formatDateTime(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
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
                        <div v-if="allowedTransitions.length" class="mb-6 flex gap-2 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <AppPrimaryButton v-if="allowedTransitions.includes('confirm')" @click="confirmBooking">
                                Konfirmasi
                            </AppPrimaryButton>
                            <AppPrimaryButton v-if="allowedTransitions.includes('check_in')" @click="checkInBooking">
                                Check-in
                            </AppPrimaryButton>
                            <AppPrimaryButton v-if="allowedTransitions.includes('check_out')" @click="checkOutBooking">
                                Check-out
                            </AppPrimaryButton>
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
                                <p>{{ formatDateTime(booking.booked_at) }}</p>
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
                                <p class="font-semibold">Deposit:</p>
                                <p>{{ formatNumber(booking.deposit_amount) }}</p>
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
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="7" class="border border-gray-300 px-4 py-2 font-semibold">Total</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(booking.total_amount) }}</td>
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
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Batalkan Booking</h3>
                <AppTextarea
                    v-model="cancelReason"
                    label="Alasan Pembatalan:"
                    rows="3"
                    required
                />
                <div class="flex justify-end gap-2 mt-4">
                    <button 
                        type="button" 
                        @click="showCancelModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        Batal
                    </button>
                    <AppDangerButton @click="cancelBooking" :disabled="!cancelReason">
                        Konfirmasi Pembatalan
                    </AppDangerButton>
                </div>
            </div>
        </AppModal>
    </AuthenticatedLayout>
</template>
