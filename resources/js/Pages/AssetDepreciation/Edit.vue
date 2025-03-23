<script setup>
import { ref, computed } from 'vue';
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

const isAmortization = computed(() => props.asset.acquisition_type === 'fixed_rental');

const depreciationMethods = [
    { value: 'straight-line', label: 'Garis Lurus' },
    { value: 'declining-balance', label: 'Saldo Menurun' },
];
</script>

<template>
    <Head :title="isAmortization ? 'Edit Entri Amortisasi' : 'Edit Entri Penyusutan'" />

    <AuthenticatedLayout>
        <template #header>
            <h2>{{ isAmortization ? 'Edit Entri Amortisasi' : 'Edit Entri Penyusutan' }}</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 mb-6">
                <div class="p-6">
                    <div class="mb-6">
                        <AppBackLink :href="route('asset-depreciation.index', asset.id)" :text="isAmortization ? 'Kembali ke Daftar Amortisasi' : 'Kembali ke Daftar Penyusutan'" />
                    </div>

                    <h3 class="text-lg font-semibold mb-4">
                        {{ isAmortization ? `Edit Amortisasi untuk Aset ${asset?.name}` : `Edit Penyusutan untuk Aset ${asset?.name}` }}
                    </h3>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-sm text-gray-600">Perusahaan</p>
                            <p class="font-medium">{{ asset.branch.branch_group.company.name }}</p>
                        </div>
                        <div v-if="isAmortization">
                            <p class="text-sm text-gray-600">Nilai Sewa</p>
                            <p class="font-medium">{{ formatNumber(asset.rental_amount) }}</p>
                        </div>
                        <div v-else>
                            <p class="text-sm text-gray-600">Nilai Perolehan</p>
                            <p class="font-medium">{{ formatNumber(asset.purchase_cost) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Cabang</p>
                            <p class="font-medium">{{ asset.branch.name }}</p>
                        </div>
                        <div v-if="isAmortization">
                            <p class="text-sm text-gray-600">Periode Sewa</p>
                            <p class="font-medium">
                                {{ asset.rental_start_date ? new Date(asset.rental_start_date).toLocaleDateString('id-ID') : '-' }} s/d
                                {{ asset.rental_end_date ? new Date(asset.rental_end_date).toLocaleDateString('id-ID') : '-' }}
                            </p>
                        </div>
                        <div v-else>
                            <p class="text-sm text-gray-600">Nilai Residu</p>
                            <p class="font-medium">{{ formatNumber(asset.salvage_value) }}</p>
                        </div>
                        <div v-if="isAmortization">
                            <p class="text-sm text-gray-600">Metode Amortisasi</p>
                            <p class="font-medium">Garis Lurus</p>
                        </div>
                        <div v-else>
                            <p class="text-sm text-gray-600">Metode Penyusutan</p>
                            <p class="font-medium">{{ depreciationMethods.find(method => method.value === asset.depreciation_method)?.label || 'Garis Lurus' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ isAmortization ? 'Akumulasi Amortisasi' : 'Akumulasi Penyusutan' }}</p>
                            <p class="font-medium">{{ asset.depreciation_entries_sum_amount ? formatNumber(asset.depreciation_entries_sum_amount) : '-' }}</p>
                        </div>
                        <div v-if="isAmortization">
                            <p class="text-sm text-gray-600">Periode Amortisasi</p>
                            <p class="font-medium">{{ asset.amortization_term_months }} bulan</p>
                        </div>
                        <div v-else>
                            <p class="text-sm text-gray-600">Masa Manfaat</p>
                            <p class="font-medium">{{ asset.useful_life_months }} bulan</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Nilai Sisa</p>
                            <p class="font-medium">
                                {{ isAmortization 
                                   ? formatNumber(asset.rental_amount - (asset.depreciation_entries_sum_amount || 0)) 
                                   : formatNumber(asset.purchase_cost - (asset.depreciation_entries_sum_amount || 0)) 
                                }}
                            </p>
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