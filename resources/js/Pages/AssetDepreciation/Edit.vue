<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import DepreciationForm from './Partials/DepreciationForm.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    asset: Object,
    entry: Object,
    accounts: Array,
});
</script>

<template>
    <Head title="Edit Entri Penyusutan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Entri Penyusutan</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 mb-6">
                <div class="p-6">
                    <div class="mb-6">
                        <AppBackLink :href="route('asset-depreciation.index', asset.id)" text="Kembali ke Daftar Penyusutan" />
                    </div>

                    <h3 class="text-lg font-semibold mb-4">Informasi Aset</h3>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-sm text-gray-600">Nama Aset</p>
                            <p class="font-medium">{{ asset?.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Nilai Perolehan</p>
                            <p class="font-medium">{{ formatNumber(asset.purchase_cost) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Perusahaan</p>
                            <p class="font-medium">{{ asset.branch.branch_group.company.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Cabang</p>
                            <p class="font-medium">{{ asset.branch.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Metode Penyusutan</p>
                            <p class="font-medium">{{ asset.depreciation_method }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Masa Manfaat</p>
                            <p class="font-medium">{{ asset.useful_life_months }} bulan</p>
                        </div>
                    </div>

                    <DepreciationForm
                        :asset="asset"
                        :entry="entry"
                        :accounts="accounts"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 