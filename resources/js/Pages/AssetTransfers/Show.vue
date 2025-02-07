<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppModal from '@/Components/AppModal.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';

const props = defineProps({
    asset: Object,
    transfer: Object,
});

const confirmingTransferDeletion = ref(false);

function deleteTransfer() {
    router.delete(route('asset-transfers.destroy', [props.asset.id, props.transfer.id]), {
        preserveScroll: true,
    });
}

function approveTransfer() {
    router.post(route('asset-transfers.approve', [props.asset.id, props.transfer.id]), {
        preserveScroll: true,
    });
}

function cancelTransfer() {
    router.post(route('asset-transfers.cancel', [props.asset.id, props.transfer.id]), {
        preserveScroll: true,
    });
}

const getStatusClass = (status) => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head title="Detail Transfer Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Transfer</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6 flex justify-between items-center">
                        <AppBackLink :href="route('asset-transfers.index', asset.id)" text="Back to Transfer History" />
                        <div class="flex space-x-2" v-if="transfer.status === 'pending'">
                            <AppPrimaryButton @click="approveTransfer">
                                Approve Transfer
                            </AppPrimaryButton>
                            <AppSecondaryButton @click="cancelTransfer">
                                Cancel Transfer
                            </AppSecondaryButton>
                            <AppSecondaryButton
                                @click="$inertia.visit(route('asset-transfers.edit', [asset.id, transfer.id]))"
                            >
                                Ubah Transfer
                            </AppSecondaryButton>
                            <AppDangerButton @click="confirmingTransferDeletion = true">
                                Hapus Transfer
                            </AppDangerButton>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Transfer Details</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">Status</dt>
                                    <dd>
                                        <span :class="[
                                            'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                            getStatusClass(transfer.status)
                                        ]">
                                            {{ transfer.status }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Transfer Date</dt>
                                    <dd>{{ new Date(transfer.transfer_date).toLocaleDateString() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">From Department</dt>
                                    <dd>{{ transfer.from_department }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">To Department</dt>
                                    <dd>{{ transfer.to_department }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold mb-4">Location Information</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">From Location</dt>
                                    <dd>{{ transfer.from_location }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">To Location</dt>
                                    <dd>{{ transfer.to_location }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Requested By</dt>
                                    <dd>{{ transfer.requested_by }}</dd>
                                </div>
                                <div v-if="transfer.approved_by">
                                    <dt class="font-medium">Approved By</dt>
                                    <dd>{{ transfer.approved_by }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="md:col-span-2">
                            <h3 class="text-lg font-semibold mb-2">Transfer Reason</h3>
                            <p class="whitespace-pre-wrap">{{ transfer.reason }}</p>
                        </div>

                        <div class="md:col-span-2" v-if="transfer.notes">
                            <h3 class="text-lg font-semibold mb-2">Additional Notes</h3>
                            <p class="whitespace-pre-wrap">{{ transfer.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Transfer Confirmation Modal -->
        <AppModal :show="confirmingTransferDeletion" @close="confirmingTransferDeletion = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this transfer request?
                </h2>

                <div class="mt-6 flex justify-end">
                    <AppSecondaryButton @click="confirmingTransferDeletion = false">
                        Cancel
                    </AppSecondaryButton>

                    <AppDangerButton
                        class="ml-3"
                        @click="deleteTransfer"
                    >
                        Delete Transfer
                    </AppDangerButton>
                </div>
            </div>
        </AppModal>
    </AuthenticatedLayout>
</template> 