<script setup>
import { computed, ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DocumentStatusPill from '@/Components/DocumentStatusPill.vue';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppModal from '@/Components/AppModal.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    purchasePlan: Object,
    filters: Object,
    allowedTransitions: Array,
});

const showCancelModal = ref(false);
const showDeleteConfirmation = ref(false);
const cancelReason = ref('');
const processing = ref(false);

const canConfirm = computed(() => props.allowedTransitions?.some(t => t.action === 'confirm'));
const canClose = computed(() => props.allowedTransitions?.some(t => t.action === 'close'));
const canCancel = computed(() => props.allowedTransitions?.some(t => t.action === 'cancel'));
const isDraft = computed(() => props.purchasePlan.status === 'draft');

function formatDate(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('id-ID');
}

function confirmPlan() {
    processing.value = true;
    router.post(route('purchase-plans.confirm', props.purchasePlan.id), {}, {
        preserveScroll: true,
        onFinish: () => processing.value = false,
    });
}

function close() {
    processing.value = true;
    router.post(route('purchase-plans.close', props.purchasePlan.id), {}, {
        preserveScroll: true,
        onFinish: () => processing.value = false,
    });
}

function openCancelModal() {
    showCancelModal.value = true;
}

function confirmCancel() {
    processing.value = true;
    router.post(route('purchase-plans.cancel', props.purchasePlan.id), {
        reason: cancelReason.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            showCancelModal.value = false;
            cancelReason.value = '';
        },
    });
}

function deletePlan() {
    router.delete(route('purchase-plans.destroy', props.purchasePlan.id), {
        onFinish: () => {
            showDeleteConfirmation.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Rencana Pembelian ${purchasePlan.plan_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm text-gray-500">Rencana Pembelian</p>
                    <h2 class="text-2xl font-semibold">
                        {{ purchasePlan.plan_number }}
                    </h2>
                </div>
                <DocumentStatusPill
                    :documentKind="DocumentStatusKind.PURCHASE_PLAN"
                    :status="purchasePlan.status"
                />
            </div>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <AppBackLink :href="route('purchase-plans.index', filters)" text="Kembali ke Daftar Rencana Pembelian" />
                            <div class="flex flex-wrap items-center">
                                <Link v-if="isDraft" :href="route('purchase-plans.edit', purchasePlan.id)" class="ml-3">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppPrimaryButton v-if="canConfirm" type="button" @click="confirmPlan" :disabled="processing" class="ml-3">
                                    Konfirmasi
                                </AppPrimaryButton>
                                <AppSecondaryButton v-if="canClose" type="button" @click="close" :disabled="processing" class="ml-3">
                                    Tutup
                                </AppSecondaryButton>
                                <AppDangerButton v-if="canCancel" type="button" @click="openCancelModal" :disabled="processing" class="ml-3">
                                    Batalkan
                                </AppDangerButton>
                                <AppDeleteButton v-if="isDraft" @click="showDeleteConfirmation = true" title="Delete" class="ml-3" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Informasi Dokumen</h3>
                                <p><span class="text-gray-500 text-sm">Tanggal Rencana:</span> {{ formatDate(purchasePlan.plan_date) }}</p>
                                <p><span class="text-gray-500 text-sm">Tanggal Dibutuhkan:</span> {{ formatDate(purchasePlan.required_date) }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Lokasi</h3>
                                <p><span class="text-gray-500 text-sm">Cabang:</span> {{ purchasePlan.branch?.name || '—' }}</p>
                                <p><span class="text-gray-500 text-sm">Perusahaan:</span> {{ purchasePlan.branch?.branch_group?.company?.name || '—' }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Ringkasan</h3>
                                <p class="flex justify-between text-sm">
                                    <span>Total Item</span>
                                    <span class="font-medium">{{ purchasePlan.lines?.length || 0 }}</span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Item Belum Dipesan</span>
                                    <span class="font-medium text-orange-600">
                                        {{ purchasePlan.lines?.filter(l => (l.planned_qty - (l.ordered_qty || 0)) > 0).length || 0 }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">#</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Produk</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Varian</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Qty Rencana</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Qty Dipesan</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Sisa</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">UOM</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Tgl Dibutuhkan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(line, index) in purchasePlan.lines" :key="line.id">
                                        <td class="px-4 py-3 text-gray-500">{{ index + 1 }}</td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ line.product?.name || '—' }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">{{ line.variant?.sku || '—' }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(line.planned_qty) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(line.ordered_qty || 0) }}</td>
                                        <td class="px-4 py-3 text-right" :class="(line.planned_qty - (line.ordered_qty || 0)) > 0 ? 'text-orange-600 font-medium' : 'text-green-600'">
                                            {{ formatNumber(Math.max(0, line.planned_qty - (line.ordered_qty || 0))) }}
                                        </td>
                                        <td class="px-4 py-3">{{ line.uom?.code || '—' }}</td>
                                        <td class="px-4 py-3">{{ formatDate(line.required_date) }}</td>
                                    </tr>
                                    <tr v-if="!purchasePlan.lines?.length">
                                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                            Tidak ada item dalam rencana ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="purchasePlan.notes" class="bg-white border border-gray-200 rounded p-4">
                            <h3 class="text-sm font-semibold text-gray-600 mb-2">Catatan</h3>
                            <p class="text-gray-700 whitespace-pre-line">{{ purchasePlan.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Modal -->
        <AppModal :show="showCancelModal" @close="showCancelModal = false">
            <template #title>
                Batalkan Rencana Pembelian
            </template>

            <template #content>
                <div class="mt-2">
                    <p class="text-sm text-gray-500 mb-4">
                        Apakah Anda yakin ingin membatalkan rencana pembelian ini? Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <AppTextarea
                        v-model="cancelReason"
                        label="Alasan Pembatalan (opsional)"
                        placeholder="Masukkan alasan pembatalan..."
                        :rows="3"
                    />
                </div>
            </template>

            <template #footer>
                <AppSecondaryButton @click="showCancelModal = false">Tidak</AppSecondaryButton>
                <AppDangerButton class="ml-3" @click="confirmCancel" :disabled="processing">Ya, Batalkan</AppDangerButton>
            </template>
        </AppModal>

        <!-- Delete Confirmation Modal -->
        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Rencana Pembelian"
            message="Apakah Anda yakin ingin menghapus rencana pembelian ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deletePlan"
        />
    </AuthenticatedLayout>
</template>
