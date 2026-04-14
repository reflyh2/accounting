<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppInput from '@/Components/AppInput.vue';

const props = defineProps({
    company: Object,
    accounts: Array,
    assignedAccountIds: Array,
    filters: Object,
});

const search = ref('');
const filterType = ref('');

const form = useForm({
    account_ids: [...props.assignedAccountIds],
});

const submitted = ref(false);

const accountTypes = [
    { value: 'kas_bank', label: 'Kas & Bank' },
    { value: 'piutang_usaha', label: 'Piutang Usaha' },
    { value: 'piutang_lainnya', label: 'Piutang Lainnya' },
    { value: 'persediaan', label: 'Persediaan' },
    { value: 'aset_lancar_lainnya', label: 'Aset Lancar Lainnya' },
    { value: 'aset_tetap', label: 'Aset Tetap' },
    { value: 'akumulasi_penyusutan', label: 'Akumulasi Penyusutan' },
    { value: 'aset_lainnya', label: 'Aset Lainnya' },
    { value: 'hutang_usaha', label: 'Hutang Usaha' },
    { value: 'hutang_usaha_lainnya', label: 'Hutang Usaha Lainnya' },
    { value: 'liabilitas_jangka_pendek', label: 'Liabilitas Jangka Pendek' },
    { value: 'liabilitas_jangka_panjang', label: 'Liabilitas Jangka Panjang' },
    { value: 'modal', label: 'Modal' },
    { value: 'pendapatan', label: 'Pendapatan' },
    { value: 'beban_pokok_penjualan', label: 'Beban Pokok Penjualan' },
    { value: 'beban', label: 'Beban' },
    { value: 'beban_penyusutan', label: 'Beban Penyusutan' },
    { value: 'beban_amortisasi', label: 'Beban Amortisasi' },
    { value: 'beban_lainnya', label: 'Beban Lainnya' },
    { value: 'pendapatan_lainnya', label: 'Pendapatan Lainnya' },
];

function getTypeLabel(type) {
    return accountTypes.find(t => t.value === type)?.label || type;
}

const filteredAccounts = computed(() => {
    return props.accounts.filter(account => {
        if (filterType.value && account.type !== filterType.value) return false;
        if (search.value) {
            const q = search.value.toLowerCase();
            return account.code.toLowerCase().includes(q) || account.name.toLowerCase().includes(q);
        }
        return true;
    });
});

const selectedCount = computed(() => form.account_ids.length);
const totalCount = computed(() => props.accounts.length);

function toggleAccount(accountId) {
    const idx = form.account_ids.indexOf(accountId);
    if (idx >= 0) {
        form.account_ids.splice(idx, 1);
    } else {
        form.account_ids.push(accountId);
    }
}

function isChecked(accountId) {
    return form.account_ids.includes(accountId);
}

function selectAll() {
    const visibleIds = filteredAccounts.value.map(a => a.id);
    for (const id of visibleIds) {
        if (!form.account_ids.includes(id)) {
            form.account_ids.push(id);
        }
    }
}

function deselectAll() {
    const visibleIds = new Set(filteredAccounts.value.map(a => a.id));
    form.account_ids = form.account_ids.filter(id => !visibleIds.has(id));
}

const allVisibleSelected = computed(() => {
    if (filteredAccounts.value.length === 0) return false;
    return filteredAccounts.value.every(a => form.account_ids.includes(a.id));
});

function toggleAll() {
    if (allVisibleSelected.value) {
        deselectAll();
    } else {
        selectAll();
    }
}

function submitForm() {
    submitted.value = true;
    form.put(route('companies.account-assignment.update', props.company.id), {
        preserveScroll: true,
        onSuccess: () => { submitted.value = false; },
        onError: () => { submitted.value = false; },
    });
}
</script>

<template>
    <Head title="Penugasan Akun Perusahaan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Penugasan Akun - {{ company.name }}</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('companies.show', [company.id, filters])" text="Kembali ke Detail Perusahaan" />
                        </div>

                        <div class="flex items-center justify-between mb-4">
                            <p class="text-sm text-gray-600">
                                {{ selectedCount }} dari {{ totalCount }} akun dipilih
                            </p>
                            <AppPrimaryButton @click="submitForm" :disabled="submitted || form.processing">
                                Simpan Perubahan
                            </AppPrimaryButton>
                        </div>

                        <!-- Filters -->
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <AppInput
                                v-model="search"
                                type="text"
                                placeholder="Cari kode atau nama akun..."
                                label="Cari"
                            />
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Akun</label>
                                <select v-model="filterType" class="w-full rounded-md border-gray-300 shadow-sm focus:border-main-300 focus:ring focus:ring-main-200 focus:ring-opacity-50 text-sm">
                                    <option value="">Semua Tipe</option>
                                    <option v-for="t in accountTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left w-12">
                                            <input
                                                type="checkbox"
                                                :checked="allVisibleSelected"
                                                @change="toggleAll"
                                                class="rounded border-gray-300 text-main-600 shadow-sm focus:border-main-300 focus:ring focus:ring-main-200 focus:ring-opacity-50"
                                            />
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Akun</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="account in filteredAccounts" :key="account.id"
                                        :class="[
                                            'hover:bg-gray-50 cursor-pointer',
                                            account.is_parent ? 'bg-gray-50 font-semibold' : '',
                                        ]"
                                        @click="toggleAccount(account.id)"
                                    >
                                        <td class="px-4 py-2">
                                            <input
                                                type="checkbox"
                                                :checked="isChecked(account.id)"
                                                @click.stop
                                                @change="toggleAccount(account.id)"
                                                class="rounded border-gray-300 text-main-600 shadow-sm focus:border-main-300 focus:ring focus:ring-main-200 focus:ring-opacity-50"
                                            />
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">{{ account.code }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ account.name }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500 whitespace-nowrap">{{ getTypeLabel(account.type) }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500">{{ account.parent_name || '—' }}</td>
                                    </tr>
                                    <tr v-if="filteredAccounts.length === 0">
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">Tidak ada akun ditemukan</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex items-center justify-between mt-4">
                            <p class="text-sm text-gray-600">
                                {{ selectedCount }} dari {{ totalCount }} akun dipilih
                            </p>
                            <AppPrimaryButton @click="submitForm" :disabled="submitted || form.processing">
                                Simpan Perubahan
                            </AppPrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
