<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppModal from '@/Components/AppModal.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import PaymentForm from './Partials/PaymentForm.vue';

const props = defineProps({
    asset: Object,
    lease: Object,
});

const showingPaymentModal = ref(false);
const editingPayment = ref(null);
const confirmingPaymentDeletion = ref(false);
const paymentToDelete = ref(null);

function openPaymentModal(payment = null) {
    editingPayment.value = payment;
    showingPaymentModal.value = true;
}

function closePaymentModal() {
    editingPayment.value = null;
    showingPaymentModal.value = false;
}

function confirmPaymentDeletion(payment) {
    paymentToDelete.value = payment;
    confirmingPaymentDeletion.value = true;
}

function deletePayment() {
    router.delete(route('asset-lease-payments.destroy', [props.asset.id, paymentToDelete.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingPaymentDeletion.value = false;
            paymentToDelete.value = null;
        },
    });
}

const getStatusClass = (status) => {
    switch (status) {
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'overdue':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head title="Lease Payments" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Lease Payments</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6 flex justify-between items-center">
                        <AppBackLink 
                            :href="route('asset-leases.show', asset.id)" 
                            :text="`Back to Lease Details: ${asset.name}`" 
                        />
                        <AppPrimaryButton @click="openPaymentModal()">
                            Record Payment
                        </AppPrimaryButton>
                    </div>

                    <!-- Payments Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Due Date
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Payment Date
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th v-if="lease.lease_type === 'finance'" class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Interest
                                    </th>
                                    <th v-if="lease.lease_type === 'finance'" class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Principal
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="payment in lease.payments" :key="payment.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ new Date(payment.due_date).toLocaleDateString() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ payment.payment_date ? new Date(payment.payment_date).toLocaleDateString() : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(payment.amount) }}
                                    </td>
                                    <td v-if="lease.lease_type === 'finance'" class="px-6 py-4 whitespace-nowrap">
                                        {{ payment.interest_portion ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(payment.interest_portion) : '-' }}
                                    </td>
                                    <td v-if="lease.lease_type === 'finance'" class="px-6 py-4 whitespace-nowrap">
                                        {{ payment.principal_portion ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(payment.principal_portion) : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="[
                                            'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                            getStatusClass(payment.status)
                                        ]">
                                            {{ payment.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button
                                            v-if="payment.status === 'pending'"
                                            @click="openPaymentModal(payment)"
                                            class="text-indigo-600 hover:text-indigo-900 mr-2"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="confirmPaymentDeletion(payment)"
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <AppModal :show="showingPaymentModal" @close="closePaymentModal">
            <div class="p-6">
                <PaymentForm
                    :asset="asset"
                    :payment="editingPayment"
                    @close="closePaymentModal"
                />
            </div>
        </AppModal>

        <!-- Delete Payment Confirmation Modal -->
        <AppModal :show="confirmingPaymentDeletion" @close="confirmingPaymentDeletion = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this payment?
                </h2>

                <div class="mt-6 flex justify-end">
                    <AppSecondaryButton @click="confirmingPaymentDeletion = false">
                        Cancel
                    </AppSecondaryButton>

                    <AppDangerButton
                        class="ml-3"
                        @click="deletePayment"
                    >
                        Delete Payment
                    </AppDangerButton>
                </div>
            </div>
        </AppModal>
    </AuthenticatedLayout>
</template> 