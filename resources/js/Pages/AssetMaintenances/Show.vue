<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { formatNumber } from '@/utils/numberFormat';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    maintenance: Object,
    filters: Object,
    maintenanceTypes: Object,
    statusOptions: Object,
});

const showDeleteConfirmation = ref(false);
const processing = ref(false);

const isDraft = computed(() => props.maintenance.status === 'draft');
const isCompleted = computed(() => props.maintenance.status === 'completed');
const isCancelled = computed(() => props.maintenance.status === 'cancelled');
const canComplete = computed(() => isDraft.value);
const canCancel = computed(() => isDraft.value || isCompleted.value);
const canReopen = computed(() => isCompleted.value || isCancelled.value);
const canEdit = computed(() => isDraft.value);

const formatDate = (date) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
};

const getStatusClass = (status) => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'draft':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'cancelled':
            return 'bg-red-100 text-red-800 border-red-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

function markCompleted() {
    processing.value = true;
    router.post(route('asset-maintenances.complete', props.maintenance.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}

function markCancelled() {
    processing.value = true;
    router.post(route('asset-maintenances.cancel', props.maintenance.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}

function reopen() {
    processing.value = true;
    router.post(route('asset-maintenances.reopen', props.maintenance.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}

function deleteMaintenance() {
    router.delete(route('asset-maintenances.destroy', props.maintenance.id), {
        onFinish: () => {
            showDeleteConfirmation.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Pemeliharaan Aset ${maintenance.code}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pemeliharaan Aset</p>
                    <h2 class="text-2xl font-semibold">
                        {{ maintenance.code }}
                    </h2>
                </div>
                <span :class="[getStatusClass(maintenance.status), 'px-3 py-1 rounded-full text-sm font-medium border']">
                    {{ statusOptions[maintenance.status] }}
                </span>
            </div>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <AppBackLink :href="route('asset-maintenances.index', filters)" text="Kembali ke Daftar Pemeliharaan" />
                            <div class="flex flex-wrap items-center gap-2">
                                <a :href="route('asset-maintenances.print', maintenance.id)" target="_blank">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link v-if="canEdit" :href="route('asset-maintenances.edit', maintenance.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppPrimaryButton v-if="canComplete" @click="markCompleted" :disabled="processing">
                                    Tandai Selesai
                                </AppPrimaryButton>
                                <AppSecondaryButton v-if="canReopen" @click="reopen" :disabled="processing">
                                    Buka Kembali
                                </AppSecondaryButton>
                                <AppDangerButton v-if="canCancel" @click="markCancelled" :disabled="processing">
                                    Batalkan
                                </AppDangerButton>
                                <AppDeleteButton v-if="isDraft" @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <div v-if="isCompleted && maintenance.total_cost > 0 && !maintenance.cost_entry_id" class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded">
                            <p class="text-sm">
                                ⚠️ Pemeliharaan ini memiliki biaya tetapi belum tercatat ke modul costing. Silakan buka kembali dan selesaikan ulang untuk membuat entri biaya.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Informasi Pemeliharaan</h3>
                                <p><span class="text-gray-500 text-sm">Tanggal:</span> {{ formatDate(maintenance.maintenance_date) }}</p>
                                <p><span class="text-gray-500 text-sm">Jenis:</span> {{ maintenanceTypes[maintenance.maintenance_type] }}</p>
                                <p><span class="text-gray-500 text-sm">Status:</span> {{ statusOptions[maintenance.status] }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Aset & Lokasi</h3>
                                <p><span class="text-gray-500 text-sm">Aset:</span> {{ maintenance.asset?.name }} ({{ maintenance.asset?.code }})</p>
                                <p><span class="text-gray-500 text-sm">Cabang:</span> {{ maintenance.branch?.name }}</p>
                                <p><span class="text-gray-500 text-sm">Perusahaan:</span> {{ maintenance.company?.name }}</p>
                                <p><span class="text-gray-500 text-sm">Vendor:</span> {{ maintenance.vendor?.name || '—' }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Ringkasan Biaya</h3>
                                <p class="flex justify-between text-sm">
                                    <span>Biaya Tenaga Kerja</span>
                                    <span>{{ formatNumber(maintenance.labor_cost) }}</span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Biaya Suku Cadang</span>
                                    <span>{{ formatNumber(maintenance.parts_cost) }}</span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Biaya Eksternal</span>
                                    <span>{{ formatNumber(maintenance.external_cost) }}</span>
                                </p>
                                <p class="flex justify-between text-base font-semibold border-t pt-2">
                                    <span>Total Biaya</span>
                                    <span>{{ formatNumber(maintenance.total_cost) }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded p-4">
                            <h3 class="text-sm font-semibold text-gray-600 mb-2">Deskripsi Pekerjaan</h3>
                            <p class="text-gray-700 whitespace-pre-line">{{ maintenance.description || '—' }}</p>
                        </div>

                        <div v-if="maintenance.notes" class="bg-white border border-gray-200 rounded p-4">
                            <h3 class="text-sm font-semibold text-gray-600 mb-2">Catatan</h3>
                            <p class="text-gray-700 whitespace-pre-line">{{ maintenance.notes }}</p>
                        </div>

                        <div v-if="maintenance.cost_entry_id" class="bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 rounded">
                            <p class="text-sm">
                                ✅ Biaya pemeliharaan ini telah dicatat ke modul costing.
                                <Link :href="route('costing.cost-entries.show', maintenance.cost_entry_id)" class="underline font-medium">
                                    Lihat Entri Biaya →
                                </Link>
                            </p>
                        </div>

                        <div class="text-sm text-gray-500 space-y-1">
                            <p v-if="maintenance.created_by_user">
                                Dibuat oleh: {{ maintenance.created_by_user.name }}
                            </p>
                            <p v-if="maintenance.updated_by_user">
                                Diubah oleh: {{ maintenance.updated_by_user.name }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Pemeliharaan Aset"
            message="Apakah Anda yakin ingin menghapus pemeliharaan aset ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteMaintenance"
        />
    </AuthenticatedLayout>
</template>
