<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref, computed } from 'vue';

const page = usePage();

const props = defineProps({
    debt: Object,
    filters: Object,
    statusOptions: Object,
    statusStyles: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteDebt = () => {
    form.delete(route('internal-debts.destroy', props.debt.id), {
        onSuccess: () => { showDeleteConfirmation.value = false; },
        onError: () => { showDeleteConfirmation.value = false; }
    });
};

const approve = () => {
    form.put(route('internal-debts.approve', props.debt.id));
};

const reject = () => {
    form.put(route('internal-debts.reject', props.debt.id));
};

const formattedStatus = computed(() => {
    if (!props.debt.status) return '';
    return props.statusOptions[props.debt.status] || props.debt.status;
});

const statusColor = computed(() => {
    if (!props.debt.status) return '';
    return props.statusStyles[props.debt.status]?.class || 'bg-gray-100 text-gray-800';
});

function formatNumber(n) {
    return Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n || 0));
}
</script>

<template>
    <Head title="Detail Hutang / Piutang Internal" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Hutang / Piutang Internal</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('internal-debts.index', filters)" text="Kembali ke Daftar Hutang Internal" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">Nomor #{{ debt.number }}</h3>
                            <div class="flex items-center">
                                <Link :href="route('internal-debts.edit', debt.id)" class="mr-1">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Peminjam (Perusahaan):</p>
                                <p>{{ debt.branch?.branch_group?.company?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Pemberi (Perusahaan):</p>
                                <p>{{ debt.counterparty_branch?.branch_group?.company?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Peminjam (Cabang):</p>
                                <p>{{ debt.branch?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Pemberi (Cabang):</p>
                                <p>{{ debt.counterparty_branch?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>
                                    <span :class="statusColor" class="px-2 py-1 text-xs font-medium rounded-full">
                                        {{ formattedStatus }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Terbit:</p>
                                <p>{{ new Date(debt.issue_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Jatuh Tempo:</p>
                                <p>{{ debt.due_date ? new Date(debt.due_date).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Mata Uang:</p>
                                <p>{{ debt.currency?.code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Jumlah:</p>
                                <p>{{ debt.currency?.symbol }} {{ formatNumber(debt.amount) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ debt.notes || '-' }}</p>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button v-if="debt.status === 'pending'" @click="approve" class="px-3 py-1.5 rounded bg-green-600 text-white text-sm hover:bg-green-700">
                                Setujui
                            </button>
                            <button v-if="debt.status === 'pending'" @click="reject" class="px-3 py-1.5 rounded bg-red-600 text-white text-sm hover:bg-red-700">
                                Tolak
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Hutang Internal"
            message="Apakah Anda yakin ingin menghapus hutang internal ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteDebt"
        />
    </AuthenticatedLayout>
</template>


