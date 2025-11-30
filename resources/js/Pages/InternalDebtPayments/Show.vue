<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref, computed, watch } from 'vue';
import { formatNumber } from '@/utils/numberFormat';
import AppModal from '@/Components/AppModal.vue';
import AppSelect from '@/Components/AppSelect.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

const props = defineProps({
    item: Object,
    filters: Object,
    paymentStatusOptions: Object,
    paymentStatusStyles: Object,
    paymentMethodOptions: Object,
    counterpartyAccounts: Array,
});

const form = useForm({
    counterparty_account_id: props.item.counterparty_account_id || null,
});
const approving = ref(false);
const rejecting = ref(false);
const showCounterpartyModal = ref(false);
const showDeleteConfirmation = ref(false);
const deleteItem = () => {
    form.delete(route('internal-debt-payments.destroy', props.item.id), {
        onSuccess: () => { showDeleteConfirmation.value = false; },
        onError: () => { showDeleteConfirmation.value = false; }
    });
};

const counterpartyAccountOptions = computed(() =>
    (props.counterpartyAccounts || []).filter(a => a.type === 'kas_bank').map(a => ({ value: a.id, label: `${a.code} - ${a.name}` }))
);

function approve() {
    if (props.item.payment_method !== 'transfer' && !props.item.counterparty_account_id) {
        showCounterpartyModal.value = true;
        return;
    }
    approving.value = true;
    form.put(route('internal-debt-payments.approve', props.item.id), {
        onFinish: () => approving.value = false
    });
}

function reject() {
    rejecting.value = true;
    form.put(route('internal-debt-payments.reject', props.item.id), {
        onFinish: () => rejecting.value = false
    });
}

function confirmApproveWithCounterparty() {
    if (!form.counterparty_account_id) return;
    approving.value = true;
    form.put(route('internal-debt-payments.approve', props.item.id), {
        onFinish: () => {
            approving.value = false;
            showCounterpartyModal.value = false;
        }
    });
}
</script>

<template>
    <Head title="Detail Pembayaran Internal" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pembayaran Internal</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('internal-debt-payments.index', filters)" text="Kembali ke Daftar Pembayaran Internal" />
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">Dokumen #{{ item.number }}</h3>
                        <div class="flex items-center">
                            <Link :href="route('internal-debt-payments.edit', item.id)" class="mr-1">
                                <AppEditButton title="Edit" />
                            </Link>
                            <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" :disabled="item.status === 'approved'" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-x-8 gap-y-2 *:py-1 text-sm mb-6">
                        <div>
                            <p class="font-semibold">Perusahaan:</p>
                            <p>{{ item.branch?.branch_group?.company?.name }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Cabang (Peminjam):</p>
                            <p>{{ item.branch?.name }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Tanggal:</p>
                            <p>{{ new Date(item.payment_date).toLocaleDateString('id-ID') }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Status:</p>
                            <p class="capitalize">
                                <span :class="paymentStatusStyles[item.status]?.class" class="px-2 py-1 text-xs font-medium rounded-full">
                                    {{ paymentStatusOptions[item.status] }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="font-semibold">Mata Uang:</p>
                            <p>{{ item.currency?.code }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Metode Pembayaran:</p>
                            <p>{{ paymentMethodOptions[item.payment_method] || '-' }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Referensi:</p>
                            <p>{{ item.reference_number || '-' }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="font-semibold">Catatan:</p>
                            <p>{{ item.notes || '-' }}</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-md font-semibold mb-2">Alokasi ke Hutang/Piutang</h4>
                        <table class="w-full border-collapse border border-gray-300 text-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border border-gray-300 px-1.5 py-1.5 text-left"># Hutang/Piutang</th>
                                    <th class="border border-gray-300 px-1.5 py-1.5 text-left">Tgl Terbit</th>
                                    <th class="border border-gray-300 px-1.5 py-1.5 text-left">Jatuh Tempo</th>
                                    <th class="border border-gray-300 px-1.5 py-1.5 text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="detail in item.details" :key="detail.id">
                                    <td class="border border-gray-300 px-1.5 py-1.5">
                                        <a :href="route('internal-debts.show', detail.internal_debt_id)" class="text-main-600 hover:text-main-800 hover:underline">{{ detail.internal_debt?.number }}</a>
                                    </td>
                                    <td class="border border-gray-300 px-1.5 py-1.5">{{ new Date(detail.internal_debt?.issue_date).toLocaleDateString('id-ID') }}</td>
                                    <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.internal_debt?.due_date ? new Date(detail.internal_debt?.due_date).toLocaleDateString('id-ID') : '-' }}</td>
                                    <td class="border border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.amount) }}</td>
                                </tr>
                                <tr v-if="!item.details || item.details.length === 0">
                                    <td colspan="4" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">Tidak ada alokasi.</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50">
                                    <td colspan="3" class="border border-gray-300 px-1.5 py-1.5 text-right font-semibold">Total</td>
                                    <td class="border border-gray-300 px-1.5 py-1.5 text-right font-semibold">{{ formatNumber(item.amount) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-6 flex items-center gap-2" v-if="item.status === 'pending'">
                        <button @click="approve" :disabled="approving" class="px-3 py-1.5 rounded bg-green-600 text-white text-sm hover:bg-green-700">Setujui</button>
                        <button @click="reject" :disabled="rejecting" class="px-3 py-1.5 rounded bg-red-600 text-white text-sm hover:bg-red-700">Tolak</button>
                    </div>
                </div>
            </div>
        </div>

        <AppModal :show="showCounterpartyModal" @close="showCounterpartyModal = false" maxWidth="md">
            <template #title>Pilih Rekening Penerima (Pemberi)</template>
            <template #content>
                <div class="space-y-3">
                    <p class="text-sm text-gray-600">Metode pembayaran bukan transfer. Pilih rekening kas/bank milik pihak pemberi untuk menerima dana ini.</p>
                    <AppSelect
                        v-model="form.counterparty_account_id"
                        :options="counterpartyAccountOptions"
                        :inModal="true"
                        label="Rekening Pihak Pemberi:"
                        placeholder="Pilih Rekening"
                    />
                </div>
            </template>
            <template #footer>
                <button @click="showCounterpartyModal = false" class="px-3 py-1.5 mr-2 rounded border text-sm">Batal</button>
                <button @click="confirmApproveWithCounterparty" :disabled="!form.counterparty_account_id" class="px-3 py-1.5 rounded bg-green-600 text-white text-sm hover:bg-green-700">Setujui</button>
            </template>
        </AppModal>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Pembayaran Internal"
            message="Apakah Anda yakin ingin menghapus pembayaran internal ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteItem"
        />
    </AuthenticatedLayout>
</template>


