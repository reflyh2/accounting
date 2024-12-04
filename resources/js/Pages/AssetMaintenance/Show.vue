<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    asset: Object,
    maintenance: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteMaintenance = () => {
    form.delete(route('asset-maintenance.destroy', [props.asset.id, props.maintenance.id]), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

const completeMaintenance = () => {
    form.post(route('asset-maintenance.complete', [props.asset.id, props.maintenance.id]), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Detail Pemeliharaan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pemeliharaan Aset</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink 
                                :href="route('asset-maintenance.index', [asset.id, filters])" 
                                :text="`Kembali ke Daftar Pemeliharaan: ${asset.name}`" 
                            />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ maintenance.maintenance_type }}</h3>
                            <div class="flex items-center">
                                <a :href="route('asset-maintenance.print', [asset.id, maintenance.id])" target="_blank">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link :href="route('asset-maintenance.edit', [asset.id, maintenance.id])">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg mb-6">
                            <h4 class="text-md font-semibold mb-2">Informasi Aset</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="font-semibold">Nama Aset:</p>
                                    <p>{{ asset.name }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Kategori:</p>
                                    <p>{{ asset.category.name }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Perusahaan:</p>
                                    <p>{{ asset.branch.branch_group.company.name }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Cabang:</p>
                                    <p>{{ asset.branch.name }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal Pemeliharaan:</p>
                                <p>{{ new Date(maintenance.maintenance_date).toLocaleDateString() }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>
                                    <span :class="maintenance.completed_at ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        {{ maintenance.completed_at ? 'Selesai' : 'Dalam Proses' }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">Biaya:</p>
                                <p>{{ formatNumber(maintenance.cost) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Dilakukan Oleh:</p>
                                <p>{{ maintenance.performed_by || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Jadwal Pemeliharaan Berikutnya:</p>
                                <p>{{ maintenance.next_maintenance_date ? new Date(maintenance.next_maintenance_date).toLocaleDateString() : '-' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Deskripsi:</p>
                                <p>{{ maintenance.description }}</p>
                            </div>
                        </div>

                        <div class="mt-6" v-if="!maintenance.completed_at">
                            <button
                                @click="completeMaintenance"
                                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
                            >
                                Selesaikan Pemeliharaan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Catatan Pemeliharaan"
            @close="showDeleteConfirmation = false"
            @confirm="deleteMaintenance"
        />
    </AuthenticatedLayout>
</template> 