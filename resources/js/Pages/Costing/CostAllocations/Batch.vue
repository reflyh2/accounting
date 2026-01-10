<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    costPools: Array,
    allocationRuleOptions: Object,
});

const form = useForm({
    cost_pool_id: null,
    period: new Date().toISOString().slice(0, 7), // YYYY-MM
    allocation_rule: 'revenue_based',
});

const submitted = ref(false);

const selectedPool = computed(() => {
    if (!form.cost_pool_id) return null;
    return props.costPools.find(p => p.id === form.cost_pool_id);
});

const costPoolOptionsList = computed(() =>
    props.costPools.map(pool => ({
        value: pool.id,
        label: `${pool.code} - ${pool.name}`,
        description: `${pool.company_name} | Sisa: ${formatNumber(pool.unallocated)}`,
    }))
);

const allocationRuleOptionsList = computed(() =>
    Object.entries(props.allocationRuleOptions || {}).map(([value, label]) => ({
        value,
        label,
    }))
);

// Set default allocation rule from selected pool
watch(() => form.cost_pool_id, (newId) => {
    const pool = props.costPools.find(p => p.id === newId);
    if (pool?.allocation_rule) {
        form.allocation_rule = pool.allocation_rule;
    }
});

function submitForm() {
    submitted.value = true;
    form.post(route('costing.cost-allocations.run-batch'), {
        preserveScroll: true,
        onSuccess: () => submitted.value = false,
        onError: () => submitted.value = false,
    });
}
</script>

<template>
    <Head title="Batch Alokasi Biaya" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Batch Alokasi Biaya</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-6">
                        <AppBackLink :href="route('costing.cost-allocations.index')" text="Kembali ke Daftar Alokasi" />
                    </div>

                    <form @submit.prevent="submitForm" class="space-y-6">
                        <div class="flex gap-8">
                            <div class="w-2/3 max-w-2xl">
                                <div class="grid grid-cols-1 gap-4">
                                    <AppSelect
                                        v-model="form.cost_pool_id"
                                        :options="costPoolOptionsList"
                                        label="Pilih Pool Biaya:"
                                        placeholder="Pilih Pool"
                                        :error="form.errors.cost_pool_id"
                                        required
                                    />

                                    <AppInput
                                        v-model="form.period"
                                        type="month"
                                        label="Periode (Bulan):"
                                        placeholder="YYYY-MM"
                                        :error="form.errors.period"
                                        required
                                    />

                                    <AppSelect
                                        v-model="form.allocation_rule"
                                        :options="allocationRuleOptionsList"
                                        label="Aturan Alokasi:"
                                        placeholder="Pilih Aturan"
                                        :error="form.errors.allocation_rule"
                                        required
                                    />
                                </div>

                                <!-- Selected Pool Info -->
                                <div v-if="selectedPool" class="mt-6 p-4 bg-gray-50 rounded border border-gray-200">
                                    <h3 class="text-sm font-semibold text-gray-600 mb-3">Informasi Pool Terpilih</h3>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-500">Pool:</span>
                                            <span class="ml-2 font-medium">{{ selectedPool.code }} - {{ selectedPool.name }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Perusahaan:</span>
                                            <span class="ml-2">{{ selectedPool.company_name }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Sisa Belum Dialokasikan:</span>
                                            <span class="ml-2 font-medium" :class="selectedPool.unallocated > 0 ? 'text-orange-600' : 'text-green-600'">
                                                {{ formatNumber(selectedPool.unallocated) }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Aturan Default:</span>
                                            <span class="ml-2">{{ allocationRuleOptions[selectedPool.allocation_rule] || selectedPool.allocation_rule || '—' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6 flex items-center">
                                    <AppPrimaryButton type="submit" class="mr-2" :disabled="submitted || !form.cost_pool_id || selectedPool?.unallocated <= 0">
                                        Jalankan Alokasi
                                    </AppPrimaryButton>
                                    <AppSecondaryButton @click="$inertia.visit(route('costing.cost-allocations.index'))">
                                        Batal
                                    </AppSecondaryButton>
                                </div>
                            </div>

                            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                                <h3 class="text-lg font-semibold mb-2">Cara Kerja Alokasi</h3>
                                <p class="mb-3">
                                    Proses ini akan mengalokasikan biaya dari pool yang dipilih ke semua faktur penjualan pada periode yang ditentukan.
                                </p>
                                <h4 class="font-semibold mt-4 mb-2">Aturan Alokasi:</h4>
                                <ul class="list-disc list-inside space-y-1">
                                    <li><strong>Revenue</strong> - Proporsi berdasarkan total penjualan</li>
                                    <li><strong>Quantity</strong> - Proporsi berdasarkan jumlah unit</li>
                                    <li><strong>Waktu</strong> - Dibagi rata per faktur</li>
                                    <li><strong>Manual</strong> - Dibagi rata per faktur</li>
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
