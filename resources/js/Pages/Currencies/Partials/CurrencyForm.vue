<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    currency: Object,
    companies: Array,
    filters: Object,
});

const form = useForm({
    code: props.currency?.code || '',
    name: props.currency?.name || '',
    symbol: props.currency?.symbol || '',
    is_primary: props.currency?.is_primary || false,
    exchange_rates: props.currency?.company_rates.map(companyRate => ({
        company_id: companyRate.company_id,
        rate: companyRate.exchange_rate,
    })) || props.companies.map(company => ({
        company_id: company.id,
        rate: 1,
    })),
    use_same_rate: true,
    create_another: false,
});

const submitted = ref(false);

const sameRateForAll = computed({
    get: () => form.use_same_rate,
    set: (value) => {
        form.use_same_rate = value;
        if (value) {
            const firstRate = form.exchange_rates[0].rate;
            form.exchange_rates.forEach(rate => rate.rate = firstRate);
        }
    }
});

function updateAllRates(newRate) {
    if (form.use_same_rate) {
        form.exchange_rates.forEach(rate => rate.rate = newRate);
    }
}

function submitForm() {
    submitted.value = true;
    if (props.currency) {
        form.put(route('currencies.update', props.currency.id), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
            },
            onError: () => {
                submitted.value = false;
            }
        });
    } else {
        form.post(route('currencies.store'), {
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
         <AppInput
               v-model="form.code"
               label="Kode Mata Uang:"
               :error="form.errors.code"
               placeholder="Kode mata uang (contoh: IDR)"
               required
         />
         <AppInput
               v-model="form.name"
               label="Nama Mata Uang:"
               :error="form.errors.name"
               required
         />
         <AppInput
               v-model="form.symbol"
               label="Simbol Mata Uang:"
               :error="form.errors.symbol"
               required
         />
         <AppCheckbox
               v-model="form.is_primary"
               label="Mata Uang Utama"
               :error="form.errors.is_primary"
         />

         <div class="mt-6">
            <h3 class="text-lg font-semibold mb-2">Exchange Rates</h3>
            <AppCheckbox
                  v-model="sameRateForAll"
                  label="Gunakan nilai tukar yang sama untuk semua perusahaan"
                  class="mb-4"
            />
            <div v-for="(rate, index) in form.exchange_rates" :key="index" class="mb-4">
                  <div class="flex items-center">
                     <AppSelect
                        v-model="rate.company_id"
                        :options="companies.map(company => ({ value: company.id, label: company.name }))"
                        label="Perusahaan:"
                        class="w-1/2 mr-4"
                        disabled
                     />
                     <AppInput
                        v-model="rate.rate"
                        label="Nilai Tukar:"
                        :error="form.errors[`exchange_rates.${index}.rate`]"
                        class="w-1/2"
                        @input="updateAllRates(rate.rate)"
                        :numberFormat="true"
                     />
                  </div>
            </div>
         </div>

         <div class="mt-6 flex items-center">
            <AppPrimaryButton type="submit" :disabled="submitted" class="mr-2">
               {{ props.currency ? 'Ubah' : 'Tambah' }} Mata Uang
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.currency" type="button" @click="submitAndCreateAnother" class="mr-2">
               Tambahkan dan buat yang lain
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('currencies.index', filters))">
               Batal
            </AppSecondaryButton>
         </div>
      </form>

      <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
         <h3 class="text-lg font-semibold mb-2">Informasi Mata Uang</h3>
         <p class="mb-2">Mata uang adalah komponen dasar dalam sistem akuntansi. Pastikan informasi yang dimasukkan akurat.</p>
         <ul class="list-disc list-inside">
            <li>Kode mata uang harus unik</li>
            <li>Masukkan simbol mata uang yang sesuai</li>
            <li>Mata uang utama harus diatur dengan benar</li>
         </ul>
      </div>
   </div>
</template>