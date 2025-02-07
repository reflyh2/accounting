<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    category: Object,
    assets: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);
const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });

const deleteCategory = () => {
    form.delete(route('asset-categories.destroy', props.category.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

const tableHeaders = [
    { key: 'name', label: 'Nama Aset' },
    { key: 'branch.branch_group.company.name', label: 'Perusahaan' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const sortableColumns = ['name', 'branch.name', 'status'];
const defaultSort = { key: 'name', order: 'asc' };

const columnFormatters = {
    status: (value) => ({
        'active': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>',
        'inactive': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Tidak Aktif</span>',
        'maintenance': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pemeliharaan</span>',
        'disposed': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Dilepas</span>'
    })[value] || value
};

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('asset-categories.show', props.category.id), {
        ...route().params,
        sort: newSort.key,
        order: newSort.order,
        per_page: props.perPage,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Detail Kategori Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Kategori Aset</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-categories.index')" text="Kembali ke Daftar Kategori" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ category.name }}</h3>
                            <div class="flex items-center">
                                <Link :href="route('asset-categories.edit', category.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div class="col-span-2">
                                <p class="font-semibold">Deskripsi:</p>
                                <p>{{ category.description || '-' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Perusahaan:</p>
                                <div class="flex flex-wrap gap-2 mt-1">
                                    <span 
                                        v-for="company in category.companies" 
                                        :key="company.id"
                                        class="px-2 py-1 bg-main-100 text-main-800 rounded-full text-xs"
                                    >
                                        {{ company.name }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-12">
                            <div class="flex justify-between items-center">
                                <h4 class="text-lg font-semibold">Daftar Aset {{ category.name }}</h4>
                            </div>
                        </div>
                    </div>                    
                            
                    <AppDataTable
                        :data="assets"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :indexRoute="{ name: 'asset-categories.show' }"
                        :viewRoute="{ name: 'assets.show' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        :enableBulkActions="false"
                        :showPerPage="false"
                        :showDownload="false"
                        :enableFilters="false"
                        :customFilters="[]"
                        :filters="{}"
                        @sort="handleSort"
                    />
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Kategori"
            @close="showDeleteConfirmation = false"
            @confirm="deleteCategory"
        >
            <template #message>
                Apakah Anda yakin ingin menghapus kategori ini? Kategori yang memiliki aset tidak dapat dihapus.
            </template>
        </DeleteConfirmationModal>
    </AuthenticatedLayout>
</template> 