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
    componentIssue: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteComponentIssue = () => {
    form.delete(route('component-issues.destroy', props.componentIssue.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

const postComponentIssue = () => {
    if (!confirm('Apakah Anda yakin ingin memposting Component Issue ini? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }

    router.post(route('component-issues.post', props.componentIssue.id), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Detail Component Issue" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Component Issue</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('component-issues.index', filters)" text="Kembali ke Daftar Component Issues" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ componentIssue.issue_number }}</h3>
                            <div class="flex items-center gap-2">
                                <AppPrimaryButton v-if="componentIssue.status === 'draft'" @click="postComponentIssue">
                                    Post
                                </AppPrimaryButton>
                                <Link :href="route('component-issues.edit', componentIssue.id)" v-if="componentIssue.status === 'draft'">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" v-if="componentIssue.status === 'draft'" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal:</p>
                                <p>{{ componentIssue.issue_date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Work Order:</p>
                                <p>{{ componentIssue.work_order?.wo_number }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ componentIssue.status === 'draft' ? 'Draft' : 'Posted' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ componentIssue.branch?.name }}</p>
                            </div>
                            <div v-if="componentIssue.location_from">
                                <p class="font-semibold">Location From:</p>
                                <p>{{ componentIssue.location_from?.code }} - {{ componentIssue.location_from?.name }}</p>
                            </div>
                            <div v-if="componentIssue.posted_at">
                                <p class="font-semibold">Posted At:</p>
                                <p>{{ new Date(componentIssue.posted_at).toLocaleString() }}</p>
                            </div>
                            <div v-if="componentIssue.total_material_cost">
                                <p class="font-semibold">Total Material Cost:</p>
                                <p>{{ formatNumber(componentIssue.total_material_cost) }}</p>
                            </div>
                            <div v-if="componentIssue.notes" class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ componentIssue.notes }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Component Issue Lines</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">No.</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Component</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Quantity</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">UOM</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Lot</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Serial</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in componentIssue.component_issue_lines" :key="line.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ line.line_number }}</td>
                                        <td class="border border-gray-300 px-4 py-2">
                                            {{ line.component_product?.name }}
                                            <span v-if="line.component_product_variant"> - {{ line.component_product_variant?.name }}</span>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.quantity_issued) }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.uom?.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2">
                                            <span v-if="line.lot">{{ line.lot.lot_code }}</span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2">
                                            <span v-if="line.serial">{{ line.serial.serial_no }}</span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
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
            title="Hapus Component Issue"
            @close="showDeleteConfirmation = false"
            @confirm="deleteComponentIssue"
        />
    </AuthenticatedLayout>
</template>

