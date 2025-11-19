<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref, computed } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    item: Object,
    filters: Object,
    statusStyles: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteItem = () => {
    form.delete(route('external-payables.destroy', props.item.id), { onSuccess: () => { showDeleteConfirmation.value = false; } });
};

const formattedStatus = computed(() => {
    if (!props.item.status) return '';
    return props.item.status.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
});

</script>

<template>
    <Head title="Detail Hutang Eksternal" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Hutang Eksternal</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('external-payables.index', filters)" text="Kembali ke Daftar Hutang Eksternal" />
                    </div>

                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">Nomor #{{ item.number }}</h3>
                        <div class="flex items-center">
                            <a :href="route('external-payables.edit', item.id)" class="mr-1">
                                <AppEditButton title="Edit" />
                            </a>
                            <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-x-8 gap-y-2 *:py-1 text-sm mb-6">
                        <div>
                            <p class="font-semibold">Perusahaan:</p>
                            <p>{{ item.branch?.branch_group?.company?.name }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Partner:</p>
                            <p>{{ item.external_debt?.partner?.name }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Cabang:</p>
                            <p>{{ item.branch?.name }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Status:</p>
                            <p>
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs font-medium rounded-full',
                                        (statusStyles && statusStyles[item.status]?.class) || 'bg-gray-100 text-gray-800'
                                    ]"
                                >
                                    {{ (statusStyles && statusStyles[item.status]?.label) || formattedStatus }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="font-semibold">Tanggal:</p>
                            <p>{{ new Date(item.issue_date).toLocaleDateString('id-ID') }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Jatuh Tempo:</p>
                            <p>{{ item.due_date ? new Date(item.due_date).toLocaleDateString('id-ID') : '-' }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Mata Uang:</p>
                            <p>{{ item.currency?.code }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Jumlah:</p>
                            <p>{{ item.currency?.symbol }} {{ formatNumber(item.amount) }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="font-semibold">Catatan:</p>
                            <p>{{ item.notes || '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Hutang Eksternal"
            message="Apakah Anda yakin ingin menghapus data ini?"
            @close="showDeleteConfirmation = false"
            @confirm="deleteItem"
        />
    </AuthenticatedLayout>
</template>


