<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppPagination from '@/Components/AppPagination.vue';

const props = defineProps({
    asset: Object,
    transfers: Object,
});

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
    <Head title="Daftar Transfer Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Riwayat Transfer</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6 flex justify-between items-center">
                        <AppBackLink :href="route('assets.show', asset.id)" :text="`Back to Asset: ${asset.name}`" />
                        <AppPrimaryButton
                            @click="$inertia.visit(route('asset-transfers.create', asset.id))"
                        >
                            Tambah Transfer
                        </AppPrimaryButton>
                    </div>

                    <!-- Transfers Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Transfer Date
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        From Department
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        To Department
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Requested By
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="transfer in transfers.data" :key="transfer.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ new Date(transfer.transfer_date).toLocaleDateString() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ transfer.from_department }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ transfer.to_department }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="[
                                            'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                            getStatusClass(transfer.status)
                                        ]">
                                            {{ transfer.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ transfer.requested_by }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <button
                                            @click="$inertia.visit(route('asset-transfers.show', [asset.id, transfer.id]))"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            View
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="transfers.data.length === 0">
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        No transfer requests found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <AppPagination :links="transfers.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 