<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    account: Object,
    companies: Array,
    currencies: Array,
    parentAccounts: Array,
    filters: Object,
    allAccounts: Array,
});

const form = useForm({
    name: props.account?.name || '',
    code: props.account?.code || '',
    type: props.account?.type || '',
    parent_id: props.account?.parent_id || null,
    company_ids: props.account?.companies.map(c => c.id) || props.companies.map(c => c.id),
    create_another: false,
    currency_ids: props.account?.currencies?.map(c => c.id) || props.currencies?.filter(c => c.is_primary).map(c => c.id),
});

const submitted = ref(false);

const accountTypes = [
   { value: 'kas_bank', label: 'Kas & Bank' },
   { value: 'piutang_usaha', label: 'Piutang Usaha' },
   { value: 'persediaan', label: 'Persediaan' },
   { value: 'aset_lancar_lainnya', label: 'Aset Lancar Lainnya' },
   { value: 'aset_tetap', label: 'Aset Tetap' },
   { value: 'akumulasi_penyusutan', label: 'Akumulasi Penyusutan' },
   { value: 'aset_lainnya', label: 'Aset Lainnya' },
   { value: 'utang_usaha', label: 'Utang Usaha' },
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

watch(() => form.parent_id, (newParentId) => {
    if (newParentId) {
        form.code = generateAccountCode(newParentId);
        form.type = props.allAccounts.find(account => account.id === newParentId).type;
    }
});

function generateAccountCode(parentId) {
    const parentAccount = props.allAccounts.find(account => account.id === parentId);
    if (!parentAccount) return '';

    const childAccounts = props.allAccounts.filter(account => account.parent_id === parentId);
    let lastChildNumber = 0;

    if (childAccounts.length > 0) {
        const childCodes = childAccounts.map(child => parseInt(child.code.slice(-3)));
        lastChildNumber = Math.max(...childCodes);
    }

    const newChildNumber = (lastChildNumber + 1).toString().padStart(3, '0');
    return `${parentAccount.code}${newChildNumber}`;
}

function submitForm() {
    submitted.value = true;
    if (props.account) {
        form.put(route('accounts.update', props.account.id), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
            },
            onError: () => {
                submitted.value = false;
            }
        });
    } else {
        form.post(route('accounts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (form.create_another) {
                    form.reset();
                    form.clearErrors();
                    form.create_another = false;
                }
            },
            onError: () => {
                submitted.value = false;
            }
        });
    }
}

function submitAndCreateAnother() {
    form.create_another = true;
    submitForm();
}
</script>

<template>
    <div class="flex justify-between">
        <form @submit.prevent="submitForm" class="w-2/3 max-w-2xl mr-8">         
            <AppSelect
                v-model="form.parent_id"
                :options="parentAccounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                label="Akun Induk:"
                placeholder="Pilih akun induk (opsional)"
                :error="form.errors.parent_id"
                @update:modelValue="generateAccountCode($event)"
            />
            <AppInput
                v-model="form.code"
                label="Kode Akun:"
                placeholder="Masukkan kode akun"
                :error="form.errors.code"
                autofocus
                required
            />
            <AppInput
                v-model="form.name"
                label="Nama Akun:"
                placeholder="Masukkan nama akun"
                :error="form.errors.name"
                required
            />
            <AppSelect
                v-model="form.type"
                :options="accountTypes"
                label="Tipe Akun:"
                placeholder="Pilih tipe akun"
                :error="form.errors.type"
                required
            />
            <AppSelect
                v-model="form.currency_ids"
                :options="currencies.map(currency => ({ value: currency.id, label: currency.name }))"
                label="Mata Uang:"
                placeholder="Pilih mata uang"
                :error="form.errors.currency_ids"
                multiple
                required
            />
            <AppSelect
                v-model="form.company_ids"
                :options="companies.map(company => ({ value: company.id, label: company.name }))"
                label="Perusahaan:"
                placeholder="Pilih perusahaan"
                :error="form.errors.company_ids"
                multiple
                required
            />
            <div class="mt-4 flex items-center">
                <AppPrimaryButton type="submit" class="mr-2">
                    {{ props.account ? 'Ubah' : 'Tambah' }} Akun
                </AppPrimaryButton>
                <AppUtilityButton v-if="!props.account" type="button" @click="submitAndCreateAnother" class="mr-2">
                    Tambah & Buat Lagi
                </AppUtilityButton>
                <AppSecondaryButton @click="$inertia.visit(route('accounts.index', filters))">
                    Batal
                </AppSecondaryButton>
            </div>
        </form>
        
        <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Akun</h3>
            <p class="mb-2">Akun adalah komponen dasar dalam sistem akuntansi. Pastikan informasi yang dimasukkan akurat.</p>
            <ul class="list-disc list-inside">
                <li>Kode akun harus unik</li>
                <li>Pilih tipe akun yang sesuai dari daftar yang tersedia</li>
                <li>Akun induk opsional, gunakan untuk struktur hierarki</li>
                <li>Pilih satu atau lebih mata uang yang akan digunakan untuk akun ini</li>
                <li>Pilih satu atau lebih perusahaan terkait</li>
            </ul>
        </div>
    </div>
</template>