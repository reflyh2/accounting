<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage, router } from '@inertiajs/vue3';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref, computed } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const props = defineProps({
    workOrder: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);
const showCloseoutConfirmation = ref(false);

const deleteWorkOrder = () => {
    form.delete(route('work-orders.destroy', props.workOrder.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

const closeoutWorkOrder = () => {
    form.post(route('work-orders.closeout', props.workOrder.id), {
        onSuccess: () => {
            showCloseoutConfirmation.value = false;
        },
    });
};

const statusLabels = {
    'draft': 'Draft',
    'released': 'Released',
    'in_progress': 'In Progress',
    'completed': 'Completed',
    'cancelled': 'Cancelled'
};

const canTransition = (toStatus) => {
    const allowedTransitions = {
        'draft': ['released', 'cancelled'],
        'released': ['in_progress', 'cancelled'],
        'in_progress': ['completed', 'cancelled'],
        'completed': [],
        'cancelled': []
    };
    return allowedTransitions[props.workOrder.status]?.includes(toStatus) || false;
};

const transitionTo = (status) => {
    router.put(route('work-orders.transition', props.workOrder.id), {
        status: status
    });
};
</script>

<template>
    <Head title="Detail Work Order" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Work Order</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('work-orders.index', filters)" text="Kembali ke Daftar Work Order" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ workOrder.wo_number }}</h3>
                            <div class="flex items-center">
                                <Link :href="route('work-orders.edit', workOrder.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <!-- Status and Actions -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-700 mr-2">Status:</span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full"
                                          :class="{
                                              'bg-gray-100 text-gray-800': workOrder.status === 'draft',
                                              'bg-blue-100 text-blue-800': workOrder.status === 'released',
                                              'bg-yellow-100 text-yellow-800': workOrder.status === 'in_progress',
                                              'bg-green-100 text-green-800': workOrder.status === 'completed',
                                              'bg-red-100 text-red-800': workOrder.status === 'cancelled'
                                          }">
                                        {{ statusLabels[workOrder.status] }}
                                    </span>
                                </div>
                                <div class="flex space-x-2">
                                    <button v-if="canTransition('released')"
                                            @click="transitionTo('released')"
                                            class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600">
                                        Release
                                    </button>
                                    <button v-if="canTransition('in_progress')"
                                            @click="transitionTo('in_progress')"
                                            class="px-3 py-1 text-sm bg-yellow-500 text-white rounded hover:bg-yellow-600">
                                        Start
                                    </button>
                                    <button v-if="canTransition('completed')"
                                            @click="transitionTo('completed')"
                                            class="px-3 py-1 text-sm bg-green-500 text-white rounded hover:bg-green-600">
                                        Complete
                                    </button>
                                    <button v-if="workOrder.status === 'completed'"
                                            @click="showCloseoutConfirmation = true"
                                            class="px-3 py-1 text-sm bg-purple-500 text-white rounded hover:bg-purple-600">
                                        Close Work Order
                                    </button>
                                    <button v-if="canTransition('cancelled')"
                                            @click="transitionTo('cancelled')"
                                            class="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                                <span>Progress</span>
                                <span>{{ formatNumber(workOrder.progress_percentage) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full"
                                     :style="{ width: workOrder.progress_percentage + '%' }"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Bill of Material:</p>
                                <p>{{ workOrder.bom.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Produk Jadi:</p>
                                <p>{{ workOrder.bom.finished_product.name }}</p>
                                <p v-if="workOrder.finished_product_variant" class="text-sm text-gray-600 mt-1">
                                    Varian: {{ workOrder.finished_product_variant.name }} ({{ workOrder.finished_product_variant.sku }})
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">Quantity Direncanakan:</p>
                                <p>{{ formatNumber(workOrder.quantity_planned) }} {{ workOrder.bom.finished_uom.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Quantity Diterima:</p>
                                <p>{{ formatNumber(workOrder.total_received_quantity) }} {{ workOrder.bom.finished_uom.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Quantity Scrap:</p>
                                <p>{{ formatNumber(workOrder.quantity_scrap) }} {{ workOrder.bom.finished_uom.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ workOrder.branch.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Mulai Direncanakan:</p>
                                <p>{{ workOrder.scheduled_start_date ? new Date(workOrder.scheduled_start_date).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Mulai Aktual:</p>
                                <p>{{ workOrder.actual_start_date ? new Date(workOrder.actual_start_date).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Selesai Direncanakan:</p>
                                <p>{{ workOrder.scheduled_end_date ? new Date(workOrder.scheduled_end_date).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Selesai Aktual:</p>
                                <p>{{ workOrder.actual_end_date ? new Date(workOrder.actual_end_date).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div v-if="workOrder.wip_location">
                                <p class="font-semibold">Lokasi WIP:</p>
                                <p>{{ workOrder.wip_location.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ workOrder.notes || '-' }}</p>
                            </div>
                        </div>

                        <!-- BOM Components -->
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Komponen Bill of Material</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">No.</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Komponen</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Quantity per Unit</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Total Quantity</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Satuan</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Backflush</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Operasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in workOrder.bom.bom_lines" :key="line.id" class="group">
                                        <td class="border border-gray-300 px-4 py-2">{{ line.line_number }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.component_product.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.quantity_per) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.quantity_per * workOrder.quantity_planned) }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.uom.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-center">
                                            <span v-if="line.backflush" class="text-green-600">✓</span>
                                            <span v-else class="text-gray-400">✗</span>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.operation || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Work Order"
            @close="showDeleteConfirmation = false"
            @confirm="deleteWorkOrder"
        />

        <DeleteConfirmationModal
            :show="showCloseoutConfirmation"
            title="Tutup Work Order"
            message="Apakah Anda yakin ingin menutup Work Order ini? Variance akan dihitung dan diposting ke jurnal."
            @close="showCloseoutConfirmation = false"
            @confirm="closeoutWorkOrder"
        />
    </AuthenticatedLayout>
</template>
