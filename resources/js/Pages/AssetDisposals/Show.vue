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
    disposal: Object,
});

const confirmingDisposalDeletion = ref(false);

function deleteDisposal() {
    router.delete(route('asset-disposals.destroy', [props.asset.id, props.disposal.id]), {
        preserveScroll: true,
    });
}

function approveDisposal() {
    router.post(route('asset-disposals.approve', [props.asset.id, props.disposal.id]), {
        preserveScroll: true,
    });
}

function cancelDisposal() {
    router.post(route('asset-disposals.cancel', [props.asset.id, props.disposal.id]), {
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
    <Head title="Detail Pelepasan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pelepasan</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6 flex justify-between items-center">
                        <AppBackLink :href="route('asset-disposals.index', asset.id)" text="Back to Disposal History" />
                        <div class="flex space-x-2" v-if="disposal.status === 'pending'">
                            <AppPrimaryButton @click="approveDisposal">
                                Approve Disposal
                            </AppPrimaryButton>
                            <AppSecondaryButton @click="cancelDisposal">
                                Cancel Disposal
                            </AppSecondaryButton>
                            <AppSecondaryButton
                                @click="$inertia.visit(route('asset-disposals.edit', [asset.id, disposal.id]))"
                            >
                                Ubah Pelepasan
                            </AppSecondaryButton>
                            <AppDangerButton @click="confirmingDisposalDeletion = true">
                                Hapus Pelepasan
                            </AppDangerButton>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Disposal Details</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">Status</dt>
                                    <dd>
                                        <span :class="[
                                            'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                            getStatusClass(disposal.status)
                                        ]">
                                            {{ disposal.status }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Disposal Date</dt>
                                    <dd>{{ new Date(disposal.disposal_date).toLocaleDateString() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Disposal Method</dt>
                                    <dd class="capitalize">{{ disposal.disposal_method }}</dd>
                                </div>
                                <div v-if="disposal.disposal_method === 'sale'">
                                    <dt class="font-medium">Disposal Amount</dt>
                                    <dd>{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(disposal.disposal_amount) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold mb-4">Financial Information</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">Book Value at Disposal</dt>
                                    <dd>{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(disposal.book_value_at_disposal) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Gain/Loss Amount</dt>
                                    <dd :class="disposal.gain_loss_amount >= 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(disposal.gain_loss_amount) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Requested By</dt>
                                    <dd>{{ disposal.requested_by }}</dd>
                                </div>
                                <div v-if="disposal.approved_by">
                                    <dt class="font-medium">Approved By</dt>
                                    <dd>{{ disposal.approved_by }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="md:col-span-2">
                            <h3 class="text-lg font-semibold mb-2">Disposal Reason</h3>
                            <p class="whitespace-pre-wrap">{{ disposal.reason }}</p>
                        </div>

                        <div class="md:col-span-2" v-if="disposal.notes">
                            <h3 class="text-lg font-semibold mb-2">Additional Notes</h3>
                            <p class="whitespace-pre-wrap">{{ disposal.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Disposal Confirmation Modal -->
        <AppModal :show="confirmingDisposalDeletion" @close="confirmingDisposalDeletion = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this disposal request?
                </h2>

                <div class="mt-6 flex justify-end">
                    <AppSecondaryButton @click="confirmingDisposalDeletion = false">
                        Cancel
                    </AppSecondaryButton>

                    <AppDangerButton
                        class="ml-3"
                        @click="deleteDisposal"
                    >
                        Delete Disposal
                    </AppDangerButton>
                </div>
            </div>
        </AppModal>
    </AuthenticatedLayout>
</template> 