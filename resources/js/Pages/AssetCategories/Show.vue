<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    assetCategory: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAssetCategory = () => {
    form.delete(route('asset-categories.destroy', props.assetCategory.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Kategori Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Kategori Aset</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-categories.index', filters)" text="Kembali ke Daftar Kategori Aset" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-bold">{{ assetCategory.name }}</h3>
                            <div class="flex items-center">                              
                              <Link :href="route('asset-categories.edit', assetCategory.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-6 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Kode:</p>
                                <p>{{ assetCategory.code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Dibuat:</p>
                                <p>{{ new Date(assetCategory.created_at).toLocaleDateString() }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Deskripsi:</p>
                                <p>{{ assetCategory.description || '-' }}</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-4">Pengaturan Perusahaan</h4>
                            
                            <div v-for="company in assetCategory.companies" :key="company.id" 
                                class="mb-6 p-4 border border-gray-200 rounded-lg">
                                <h5 class="font-semibold text-lg mb-3">{{ company.name }}</h5>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-8 text-sm">
                                    <div>
                                        <p class="font-medium">Akun Aset:</p>
                                        <p v-if="company.pivot.asset_account_id">
                                            {{ company.asset_account?.code }} - {{ company.asset_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>
                                    
                                    <div>
                                        <p class="font-medium">Akun Penyusutan Aset:</p>
                                        <p v-if="company.pivot.asset_depreciation_account_id">
                                            {{ company.asset_depreciation_account?.code }} - {{ company.asset_depreciation_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>
                                    
                                    <div>
                                        <p class="font-medium">Akun Akumulasi Penyusutan:</p>
                                        <p v-if="company.pivot.asset_accumulated_depreciation_account_id">
                                            {{ company.asset_accumulated_depreciation_account?.code }} - {{ company.asset_accumulated_depreciation_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>
                                    
                                    <div>
                                        <p class="font-medium">Akun Amortisasi Aset:</p>
                                        <p v-if="company.pivot.asset_amortization_account_id">
                                            {{ company.asset_amortization_account?.code }} - {{ company.asset_amortization_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>
                                    
                                    <div>
                                        <p class="font-medium">Akun Amortisasi Dibayar Dimuka:</p>
                                        <p v-if="company.pivot.asset_prepaid_amortization_account_id">
                                            {{ company.asset_prepaid_amortization_account?.code }} - {{ company.asset_prepaid_amortization_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>
                                    
                                    <div>
                                        <p class="font-medium">Akun Biaya Sewa Aset:</p>
                                        <p v-if="company.pivot.asset_rental_cost_account_id">
                                            {{ company.asset_rental_cost_account?.code }} - {{ company.asset_rental_cost_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>

                                    <div>
                                        <p class="font-medium">Akun Hutang Pembelian Aset:</p>
                                        <p v-if="company.pivot.asset_acquisition_payable_account_id">
                                            {{ company.asset_acquisition_payable_account?.code }} - {{ company.asset_acquisition_payable_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>

                                    <div>
                                        <p class="font-medium">Akun Piutang Penjualan Aset:</p>
                                        <p v-if="company.pivot.asset_sale_receivable_account_id">
                                            {{ company.asset_sale_receivable_account?.code }} - {{ company.asset_sale_receivable_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>

                                    <div>
                                        <p class="font-medium">Akun Laba Penjualan Aset:</p>
                                        <p v-if="company.pivot.asset_sale_profit_account_id">
                                            {{ company.asset_sale_profit_account?.code }} - {{ company.asset_sale_profit_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>

                                    <div>
                                        <p class="font-medium">Akun Rugi Penjualan Aset:</p>
                                        <p v-if="company.pivot.asset_sale_loss_account_id">
                                            {{ company.asset_sale_loss_account?.code }} - {{ company.asset_sale_loss_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>

                                    <div>
                                        <p class="font-medium">Akun Hutang Leasing Aset:</p>
                                        <p v-if="company.pivot.asset_financing_payable_account_id">
                                            {{ company.asset_financing_payable_account?.code }} - {{ company.asset_financing_payable_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>

                                    <div>
                                        <p class="font-medium">Akun Bunga Leasing Aset:</p>
                                        <p v-if="company.pivot.leasing_interest_cost_account_id">
                                            {{ company.leasing_interest_cost_account?.code }} - {{ company.leasing_interest_cost_account?.name }}
                                        </p>
                                        <p v-else class="text-gray-500">Tidak diatur</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Kategori Aset"
            text="Apakah Anda yakin ingin menghapus kategori aset ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteAssetCategory"
        />
    </AuthenticatedLayout>
</template> 