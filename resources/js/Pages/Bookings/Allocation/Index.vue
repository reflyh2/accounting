<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppModal from '@/Components/AppModal.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    runs: Object,
    pools: Array,
    filters: Object,
});

const showCreate = ref(false);
const form = useForm({
    company_id: null,
    cost_pool_id: null,
    period_start: '',
    period_end: '',
    notes: '',
});

function selectPool(poolId) {
    const pool = props.pools.find((p) => p.id === Number(poolId));
    form.cost_pool_id = poolId;
    if (pool) form.company_id = pool.company_id;
}

function submit() {
    form.post(route('booking-allocations.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

function reverseRun(id) {
    if (!confirm('Reverse allocation run ini? Cost akan dilepas dari SI lines.')) return;
    router.post(route('booking-allocations.reverse', id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Booking Allocation Runs" />

    <AuthenticatedLayout>
        <template #header><h2>Booking Allocation Runs</h2></template>

        <div class="mx-auto bg-white shadow-sm sm:rounded border border-gray-200 p-6">
            <div class="flex justify-between mb-4">
                <h3 class="text-lg font-semibold">Allocation Runs</h3>
                <AppPrimaryButton @click="showCreate = true">+ Allocation Run Baru</AppPrimaryButton>
            </div>

            <table class="min-w-full text-sm border border-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 border">ID</th>
                        <th class="px-3 py-2 border">Pool / Aset</th>
                        <th class="px-3 py-2 border">Periode</th>
                        <th class="px-3 py-2 border">Basis</th>
                        <th class="px-3 py-2 border">Pool Amount</th>
                        <th class="px-3 py-2 border">Status</th>
                        <th class="px-3 py-2 border"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="run in runs.data" :key="run.id" class="border-b">
                        <td class="px-3 py-2">{{ run.id }}</td>
                        <td class="px-3 py-2">{{ run.cost_pool?.name }} <span v-if="run.asset" class="text-xs text-gray-500">/ {{ run.asset.name }}</span></td>
                        <td class="px-3 py-2">{{ run.period_start }} → {{ run.period_end }}</td>
                        <td class="px-3 py-2">{{ run.allocation_basis }}</td>
                        <td class="px-3 py-2 text-right">{{ formatNumber(run.pool_amount) }}</td>
                        <td class="px-3 py-2">
                            <span :class="{
                                'text-yellow-700': run.status === 'draft',
                                'text-green-700': run.status === 'posted',
                                'text-gray-500': run.status === 'reversed',
                            }">{{ run.status }}</span>
                        </td>
                        <td class="px-3 py-2 space-x-2">
                            <Link :href="route('booking-allocations.show', run.id)" class="text-main-500 underline">Detail</Link>
                            <button v-if="run.status === 'posted'" @click="reverseRun(run.id)" class="text-red-600 underline">Reverse</button>
                        </td>
                    </tr>
                    <tr v-if="!runs.data.length">
                        <td colspan="7" class="px-3 py-4 text-center text-gray-500">Belum ada allocation run.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal :show="showCreate" @close="showCreate = false" title="Allocation Run Baru">
            <form @submit.prevent="submit" class="space-y-3">
                <AppSelect
                    :modelValue="form.cost_pool_id"
                    :options="pools.map((p) => ({ value: p.id, label: p.name }))"
                    label="Cost Pool:"
                    placeholder="Pilih Pool"
                    required
                    :error="form.errors.cost_pool_id"
                    @update:modelValue="selectPool"
                />
                <div class="grid grid-cols-2 gap-3">
                    <AppInput v-model="form.period_start" type="date" label="Periode Mulai:" required :error="form.errors.period_start" />
                    <AppInput v-model="form.period_end" type="date" label="Periode Selesai:" required :error="form.errors.period_end" />
                </div>
                <div class="flex gap-2 justify-end">
                    <AppSecondaryButton type="button" @click="showCreate = false">Batal</AppSecondaryButton>
                    <AppPrimaryButton type="submit" :disabled="form.processing">Jalankan</AppPrimaryButton>
                </div>
            </form>
        </AppModal>
    </AuthenticatedLayout>
</template>
