<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';
import { formatNumber } from '@/utils/numberFormat';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    finishedGoodsReceipt: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteReceipt = () => {
    form.delete(route('finished-goods-receipts.destroy', props.finishedGoodsReceipt.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

const postReceipt = () => {
    if (!confirm('Apakah Anda yakin ingin memposting Finished Goods Receipt ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    router.post(route('finished-goods-receipts.post', props.finishedGoodsReceipt.id), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Detail Finished Goods Receipt" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Finished Goods Receipt</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('finished-goods-receipts.index', filters)" text="Kembali ke Daftar Finished Goods Receipts" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ finishedGoodsReceipt.receipt_number }}</h3>
                            <div class="flex items-center gap-2">
                                <AppPrimaryButton v-if="finishedGoodsReceipt.status === 'draft'" @click="postReceipt">
                                    Post
                                </AppPrimaryButton>
                                <Link :href="route('finished-goods-receipts.edit', finishedGoodsReceipt.id)" v-if="finishedGoodsReceipt.status === 'draft'">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" v-if="finishedGoodsReceipt.status === 'draft'" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal:</p>
                                <p>{{ finishedGoodsReceipt.receipt_date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Work Order:</p>
                                <p>{{ finishedGoodsReceipt.work_order?.wo_number }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ finishedGoodsReceipt.status === 'draft' ? 'Draft' : 'Posted' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ finishedGoodsReceipt.branch?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Finished Product:</p>
                                <p>{{ finishedGoodsReceipt.finished_product_variant?.name || finishedGoodsReceipt.work_order?.bom?.finished_product?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Location To:</p>
                                <p>{{ finishedGoodsReceipt.location_to?.code }} - {{ finishedGoodsReceipt.location_to?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Quantity Good:</p>
                                <p>{{ formatNumber(finishedGoodsReceipt.quantity_good) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Quantity Scrap:</p>
                                <p>{{ formatNumber(finishedGoodsReceipt.quantity_scrap) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">UOM:</p>
                                <p>{{ finishedGoodsReceipt.uom?.name }}</p>
                            </div>
                            <div v-if="finishedGoodsReceipt.lot">
                                <p class="font-semibold">Lot:</p>
                                <p>{{ finishedGoodsReceipt.lot.lot_code }}</p>
                            </div>
                            <div v-if="finishedGoodsReceipt.serial">
                                <p class="font-semibold">Serial:</p>
                                <p>{{ finishedGoodsReceipt.serial.serial_no }}</p>
                            </div>
                            <div v-if="finishedGoodsReceipt.posted_at">
                                <p class="font-semibold">Posted At:</p>
                                <p>{{ new Date(finishedGoodsReceipt.posted_at).toLocaleString() }}</p>
                            </div>
                        </div>
                        <div class="mt-6 grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Total Material Cost:</p>
                                <p>{{ formatNumber(finishedGoodsReceipt.total_material_cost || 0) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Labor Cost:</p>
                                <p>{{ formatNumber(finishedGoodsReceipt.labor_cost || 0) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Overhead Cost:</p>
                                <p>{{ formatNumber(finishedGoodsReceipt.overhead_cost || 0) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Cost:</p>
                                <p>{{ formatNumber(finishedGoodsReceipt.total_cost || 0) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Unit Cost:</p>
                                <p>{{ formatNumber(finishedGoodsReceipt.unit_cost || 0) }}</p>
                            </div>
                        </div>
                        <div v-if="finishedGoodsReceipt.notes" class="mt-4">
                            <p class="font-semibold">Catatan:</p>
                            <p>{{ finishedGoodsReceipt.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Finished Goods Receipt"
            @close="showDeleteConfirmation = false"
            @confirm="deleteReceipt"
        />
    </AuthenticatedLayout>
</template>

