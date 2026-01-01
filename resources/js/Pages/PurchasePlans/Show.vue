<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppStatusPill from '@/Components/AppStatusPill.vue';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppModal from '@/Components/AppModal.vue';
import AppTextarea from '@/Components/AppTextarea.vue';

const props = defineProps({
    purchasePlan: Object,
    filters: Object,
    allowedTransitions: Array,
});

const showCancelModal = ref(false);
const cancelReason = ref('');
const processing = ref(false);

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID');
}

function handleTransition(action) {
    if (action === 'cancel') {
        showCancelModal.value = true;
        return;
    }

    processing.value = true;
    router.post(route(`purchase-plans.${action}`, props.purchasePlan.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
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
</script>

<template>
    <Head :title="'Rencana Pembelian: ' + purchasePlan.plan_number" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2>Rencana Pembelian: {{ purchasePlan.plan_number }}</h2>
                <div class="flex gap-2">
                    <Link :href="route('purchase-plans.index', filters)" class="text-gray-500 hover:text-gray-700">
                        &larr; Kembali
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto space-y-6">
            <!-- Header Info Card -->
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ purchasePlan.plan_number }}</h3>
                            <AppStatusPill :kind="DocumentStatusKind.PURCHASE_PLAN" :status="purchasePlan.status" />
                        </div>
                        <div class="flex gap-2">
                            <template v-if="purchasePlan.status === 'draft'">
                                <Link :href="route('purchase-plans.edit', purchasePlan.id)">
                                    <AppSecondaryButton>Edit</AppSecondaryButton>
                                </Link>
                            </template>
                            <template v-for="transition in allowedTransitions" :key="transition.action">
                                <AppPrimaryButton
                                    v-if="transition.action === 'confirm'"
                                    @click="handleTransition(transition.action)"
                                    :disabled="processing"
                                >
                                    {{ transition.label }}
                                </AppPrimaryButton>
                                <AppSecondaryButton
                                    v-else-if="transition.action === 'close'"
                                    @click="handleTransition(transition.action)"
                                    :disabled="processing"
                                >
                                    {{ transition.label }}
                                </AppSecondaryButton>
                                <AppDangerButton
                                    v-else-if="transition.action === 'cancel'"
                                    @click="handleTransition(transition.action)"
                                    :disabled="processing"
                                >
                                    {{ transition.label }}
                                </AppDangerButton>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Perusahaan</span>
                            <p class="font-medium">{{ purchasePlan.branch?.branch_group?.company?.name || '-' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Cabang</span>
                            <p class="font-medium">{{ purchasePlan.branch?.name || '-' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Tanggal Rencana</span>
                            <p class="font-medium">{{ formatDate(purchasePlan.plan_date) }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Tanggal Dibutuhkan</span>
                            <p class="font-medium">{{ formatDate(purchasePlan.required_date) }}</p>
                        </div>
                    </div>

                    <div v-if="purchasePlan.notes" class="mt-4">
                        <span class="text-gray-500 text-sm">Catatan</span>
                        <p class="text-sm whitespace-pre-wrap">{{ purchasePlan.notes }}</p>
                    </div>
                </div>
            </div>

            <!-- Lines Table -->
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6">
                    <h4 class="text-lg font-medium mb-4">Item Rencana</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Varian</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Rencana</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Dipesan</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Sisa</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UOM</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Dibutuhkan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(line, index) in purchasePlan.lines" :key="line.id">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ line.product?.name || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ line.variant?.sku || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ line.planned_qty }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 text-right">{{ line.ordered_qty || 0 }}</td>
                                    <td class="px-4 py-3 text-sm text-right" :class="(line.planned_qty - (line.ordered_qty || 0)) > 0 ? 'text-orange-600 font-medium' : 'text-green-600'">
                                        {{ Math.max(0, line.planned_qty - (line.ordered_qty || 0)) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ line.uom?.code || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(line.required_date) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Modal -->
        <AppModal :show="showCancelModal" @close="showCancelModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Batalkan Rencana Pembelian</h3>
                <AppTextarea
                    v-model="cancelReason"
                    label="Alasan Pembatalan (opsional)"
                    placeholder="Masukkan alasan pembatalan..."
                    :rows="3"
                />
                <div class="mt-6 flex justify-end gap-3">
                    <AppSecondaryButton @click="showCancelModal = false">Batal</AppSecondaryButton>
                    <AppDangerButton @click="confirmCancel" :disabled="processing">Konfirmasi Pembatalan</AppDangerButton>
                </div>
            </div>
        </AppModal>
    </AuthenticatedLayout>
</template>
