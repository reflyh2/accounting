<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    bankAccount: Object,
    companies: Array,
    accounts: Array,
    currencies: Array,
    filters: Object,
});

const form = useForm({
    company_id: props.bankAccount?.company_id || null,
    account_id: props.bankAccount?.account_id || null,
    currency_id: props.bankAccount?.currency_id || null,
    bank_name: props.bankAccount?.bank_name || '',
    account_number: props.bankAccount?.account_number || '',
    account_holder_name: props.bankAccount?.account_holder_name || '',
    branch_name: props.bankAccount?.branch_name || '',
    swift_code: props.bankAccount?.swift_code || '',
    iban: props.bankAccount?.iban || '',
    is_primary: props.bankAccount?.is_primary ?? false,
    is_active: props.bankAccount?.is_active ?? true,
    notes: props.bankAccount?.notes || '',
    create_another: false,
});

const submitted = ref(false);

function submitForm() {
    submitted.value = true;
    if (props.bankAccount) {
        form.put(route('company-bank-accounts.update', props.bankAccount.id), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
            },
            onError: () => {
                submitted.value = false;
            }
        });
    } else {
        form.post(route('company-bank-accounts.store'), {
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
               v-model="form.company_id"
               :options="companies"
               label="Perusahaan:"
               :error="form.errors.company_id"
               required
         />

         <div class="mt-4">
            <h3 class="text-lg font-semibold mb-2">Informasi Rekening</h3>
            <div class="grid grid-cols-2 gap-4">
               <AppInput
                     v-model="form.bank_name"
                     label="Nama Bank:"
                     :error="form.errors.bank_name"
                     placeholder="Contoh: BCA, Mandiri, BNI"
                     required
               />
               <AppInput
                     v-model="form.account_number"
                     label="Nomor Rekening:"
                     :error="form.errors.account_number"
                     placeholder="1234567890"
                     required
               />
            </div>
            <div class="grid grid-cols-2 gap-4">
               <AppInput
                     v-model="form.account_holder_name"
                     label="Nama Pemilik Rekening:"
                     :error="form.errors.account_holder_name"
                     placeholder="PT. Example Company"
                     required
               />
               <AppInput
                     v-model="form.branch_name"
                     label="Cabang Bank:"
                     :error="form.errors.branch_name"
                     placeholder="Cabang Pusat (opsional)"
               />
            </div>
         </div>

         <div class="mt-4">
            <h3 class="text-lg font-semibold mb-2">Informasi Internasional</h3>
            <div class="grid grid-cols-2 gap-4">
               <AppInput
                     v-model="form.swift_code"
                     label="Kode SWIFT:"
                     :error="form.errors.swift_code"
                     placeholder="CENAIDJA (opsional)"
               />
               <AppInput
                     v-model="form.iban"
                     label="IBAN:"
                     :error="form.errors.iban"
                     placeholder="ID12 3456 7890 (opsional)"
               />
            </div>
         </div>

         <div class="mt-4">
            <h3 class="text-lg font-semibold mb-2">Integrasi Akuntansi</h3>
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                     v-model="form.account_id"
                     :options="accounts"
                     label="Akun GL Terkait:"
                     :error="form.errors.account_id"
                     placeholder="Pilih Akun (opsional)"
               />
               <AppSelect
                     v-model="form.currency_id"
                     :options="currencies"
                     label="Mata Uang:"
                     :error="form.errors.currency_id"
                     placeholder="Pilih Mata Uang (opsional)"
               />
            </div>
         </div>

         <div class="mt-4 flex gap-6">
            <AppCheckbox
                  v-model="form.is_primary"
                  label="Rekening Utama"
                  :error="form.errors.is_primary"
            />
            <AppCheckbox
                  v-model="form.is_active"
                  label="Aktif"
                  :error="form.errors.is_active"
            />
         </div>

         <AppTextarea
               v-model="form.notes"
               label="Catatan:"
               :error="form.errors.notes"
               class="mt-4"
         />

         <div class="mt-6 flex items-center">
            <AppPrimaryButton type="submit" :disabled="submitted" class="mr-2">
               {{ props.bankAccount ? 'Ubah' : 'Tambah' }} Rekening Bank
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.bankAccount" type="button" @click="submitAndCreateAnother" class="mr-2">
               Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('company-bank-accounts.index', filters))">
               Batal
            </AppSecondaryButton>
         </div>
      </form>

      <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
         <h3 class="text-lg font-semibold mb-2">Informasi Rekening Bank</h3>
         <p class="mb-2">Rekening bank perusahaan digunakan untuk menerima pembayaran dari pelanggan dan transaksi lainnya.</p>
         <ul class="list-disc list-inside">
            <li>Pastikan nomor rekening sudah benar</li>
            <li>Nama pemilik rekening harus sesuai dengan data bank</li>
            <li>Pilih akun GL yang sesuai untuk integrasi jurnal</li>
            <li>Rekening utama akan ditampilkan sebagai default pada dokumen</li>
         </ul>
      </div>
   </div>
</template>
