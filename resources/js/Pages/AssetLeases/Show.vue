<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppModal from '@/Components/AppModal.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    asset: Object,
    lease: Object,
});

const confirmingLeaseDeletion = ref(false);

function deleteLease() {
    router.delete(route('asset-leases.destroy', props.asset.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="View Lease" />

    <AuthenticatedLayout>
        <template #header>
            <h2>View Lease</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6 flex justify-between items-center">
                        <AppBackLink :href="route('assets.show', asset.id)" :text="`Back to Asset: ${asset.name}`" />
                        <div class="flex space-x-2">
                            <AppSecondaryButton
                                @click="$inertia.visit(route('asset-lease-payments.index', asset.id))"
                            >
                                View Payments
                            </AppSecondaryButton>
                            <AppSecondaryButton
                                @click="$inertia.visit(route('asset-leases.edit', asset.id))"
                            >
                                Edit Lease
                            </AppSecondaryButton>
                            <AppDangerButton @click="confirmingLeaseDeletion = true">
                                Delete Lease
                            </AppDangerButton>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Lease Details</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">Lease Type</dt>
                                    <dd>{{ lease.lease_type === 'operating' ? 'Operating Lease' : 'Finance Lease' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Duration</dt>
                                    <dd>{{ new Date(lease.start_date).toLocaleDateString() }} to {{ new Date(lease.end_date).toLocaleDateString() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Total Lease Amount</dt>
                                    <dd>{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(lease.lease_amount) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Payment Frequency</dt>
                                    <dd class="capitalize">{{ lease.payment_frequency }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold mb-4">Payment Information</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">Payment Amount</dt>
                                    <dd>{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(lease.payment_amount) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Prepaid Amount</dt>
                                    <dd>{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(lease.prepaid_amount) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Total Obligation</dt>
                                    <dd>{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(lease.total_obligation) }}</dd>
                                </div>
                                <div v-if="lease.lease_type === 'finance'">
                                    <dt class="font-medium">Interest Rate</dt>
                                    <dd>{{ lease.interest_rate }}%</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="md:col-span-2" v-if="lease.has_escalation_clause">
                            <h3 class="text-lg font-semibold mb-2">Escalation Terms</h3>
                            <p class="whitespace-pre-wrap">{{ lease.escalation_terms }}</p>
                        </div>

                        <div class="md:col-span-2" v-if="lease.lease_terms">
                            <h3 class="text-lg font-semibold mb-2">Lease Terms</h3>
                            <p class="whitespace-pre-wrap">{{ lease.lease_terms }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Lease Confirmation Modal -->
        <AppModal :show="confirmingLeaseDeletion" @close="confirmingLeaseDeletion = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this lease?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Once the lease is deleted, all payment records will also be removed.
                </p>

                <div class="mt-6 flex justify-end">
                    <AppSecondaryButton @click="confirmingLeaseDeletion = false">
                        Cancel
                    </AppSecondaryButton>

                    <AppDangerButton
                        class="ml-3"
                        @click="deleteLease"
                    >
                        Delete Lease
                    </AppDangerButton>
                </div>
            </div>
        </AppModal>
    </AuthenticatedLayout>
</template> 