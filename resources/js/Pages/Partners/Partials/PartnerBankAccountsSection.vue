<script setup>
import { ref, watch, nextTick } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import { TrashIcon, PlusCircleIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    bankAccounts: {
        type: Array,
        default: () => [],
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update:bank-accounts']);

const rows = ref(Array.isArray(props.bankAccounts) ? [...props.bankAccounts] : []);
const syncing = ref(false);

watch(() => props.bankAccounts, (val) => {
    syncing.value = true;
    rows.value = Array.isArray(val) ? [...val] : [];
    nextTick(() => {
        syncing.value = false;
    });
});

watch(
    () => rows.value,
    (val) => {
        if (!syncing.value) {
            emit('update:bank-accounts', val);
        }
    },
    { deep: true }
);

function addRow() {
    rows.value.push({
        id: null,
        bank_name: '',
        account_number: '',
        account_holder_name: '',
        branch_name: '',
        swift_code: '',
        iban: '',
        currency: '',
        is_primary: rows.value.length === 0,
        is_active: true,
        notes: '',
    });
}

function removeRow(index) {
    rows.value.splice(index, 1);
    if (rows.value.length > 0 && !rows.value.some(r => r.is_primary)) {
        rows.value[0] = { ...rows.value[0], is_primary: true };
    }
}

function togglePrimary(index) {
    rows.value = rows.value.map((r, i) => ({ ...r, is_primary: i === index }));
}
</script>

<template>
    <div class="rounded-lg border border-gray-200 p-4">
        <h3 class="text-lg font-medium mb-4">Rekening Bank</h3>
        <div class="overflow-x-auto mb-4">
            <table class="min-w-full bg-white border border-gray-300 text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-2 py-2 text-left">Nama Bank</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">No. Rekening</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">Atas Nama</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">Cabang</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">SWIFT</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">IBAN</th>
                        <th class="border border-gray-300 px-2 py-2 text-left">Mata Uang</th>
                        <th class="border border-gray-300 px-2 py-2 text-center">Utama</th>
                        <th class="border border-gray-300 px-2 py-2 text-center">Aktif</th>
                        <th class="border border-gray-300 px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in rows" :key="row.id ?? index">
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput v-model="row.bank_name" :error="props.errors[`bank_accounts.${index}.bank_name`]" placeholder="Nama Bank" :margins="{ top: 0, right: 0, bottom: 0, left: 0 }" />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput v-model="row.account_number" :error="props.errors[`bank_accounts.${index}.account_number`]" placeholder="No. Rekening" :margins="{ top: 0, right: 0, bottom: 0, left: 0 }" />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput v-model="row.account_holder_name" :error="props.errors[`bank_accounts.${index}.account_holder_name`]" placeholder="Atas Nama" :margins="{ top: 0, right: 0, bottom: 0, left: 0 }" />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput v-model="row.branch_name" :error="props.errors[`bank_accounts.${index}.branch_name`]" placeholder="Cabang" :margins="{ top: 0, right: 0, bottom: 0, left: 0 }" />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput v-model="row.swift_code" :error="props.errors[`bank_accounts.${index}.swift_code`]" placeholder="SWIFT" :margins="{ top: 0, right: 0, bottom: 0, left: 0 }" />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput v-model="row.iban" :error="props.errors[`bank_accounts.${index}.iban`]" placeholder="IBAN" :margins="{ top: 0, right: 0, bottom: 0 }" />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput v-model="row.currency" :error="props.errors[`bank_accounts.${index}.currency`]" placeholder="Mis. IDR" :margins="{ top: 0, right: 0, bottom: 0, left: 0 }" />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-center">
                            <input type="checkbox" :checked="row.is_primary" @change="() => togglePrimary(index)" class="w-4 h-4" />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-center">
                            <input type="checkbox" v-model="rows[index].is_active" class="w-4 h-4" />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <button type="button" class="text-red-600 flex items-left" @click="removeRow(index)">
                                <TrashIcon class="w-4 h-4 mr-1" /> Hapus
                            </button>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="10" class="border border-gray-300 px-2 py-2 text-center text-gray-500">Belum ada rekening bank. Tambahkan baris baru.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex mt-2">
            <button type="button" @click="addRow" class="flex items-center text-white bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
                <PlusCircleIcon class="w-4 h-4 mr-2" /> Tambah Rekening
            </button>
        </div>
    </div>
</template>


