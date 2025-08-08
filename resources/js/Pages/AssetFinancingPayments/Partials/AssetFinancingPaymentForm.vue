<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import { ref, watch, computed } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { formatNumber } from '@/utils/numberFormat';
import axios from 'axios';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';

const props = defineProps({
    payment: Object,
    companies: Array,
    branches: Array,
    currencies: Array,
    creditors: Array,
    agreements: Array,
    sourceAccounts: Array,
    paymentMethods: Array,
});

const form = useForm({
    company_id: props.payment?.branch?.branch_group?.company_id || null,
    branch_id: props.payment?.branch_id || null,
    payment_date: props.payment?.payment_date || new Date().toISOString().split('T')[0],
    creditor_id: props.payment?.creditor_id || null,
    source_account_id: props.payment?.source_account_id || null,
    destination_bank_account_id: props.payment?.destination_bank_account_id || null,
    reference: props.payment?.reference || '',
    currency_id: props.payment?.currency_id || null,
    exchange_rate: props.payment?.exchange_rate || 1,
    total_paid_amount: props.payment?.total_paid_amount || 0,
    principal_amount: props.payment?.principal_amount || 0,
    interest_amount: props.payment?.interest_amount || 0,
    payment_method: props.payment?.payment_method || 'cash',
    notes: props.payment?.notes || '',
    allocations: (props.payment?.allocations && props.payment.allocations.length > 0)
        ? props.payment.allocations
        : [{
            asset_financing_agreement_id: null,
            asset_financing_schedule_id: null,
            allocated_amount: 0,
            principal_amount: 0,
            interest_amount: 0,
        }],
});

const selectedCompany = ref(props.payment?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0].id));

// Computed currency options
const currencyOptions = computed(() => {
    return props.currencies.map(currency => ({
        value: currency.id,
        label: `${currency.code} - ${currency.name}`
    }));
});

// Computed current currency symbol
const currentCurrencySymbol = computed(() => {
    const currency = props.currencies.find(c => c.id == form.currency_id);
    return currency?.symbol || page.props.primaryCurrency?.symbol || '';
});

const paymentMethodOptions = computed(() => {
    return Object.entries(props.paymentMethods).map(([value, label]) => ({
        value,
        label
    }));
});

watch(selectedCompany, (newCompanyId) => {
    if (!props.payment) {
        router.reload({ only: ['branches', 'currencies', 'sourceAccounts'], data: { company_id: newCompanyId } });
    }
});

// Watch currency selection to update exchange rate
watch(() => form.currency_id, () => {
    updateExchangeRate();
});

watch([() => form.creditor_id, () => form.branch_id, () => form.currency_id], ([newCreditorId, newBranchId, newCurrencyId]) => {
    if (!props.payment) {
        router.reload({ only: ['agreements'], data: { creditor_id: newCreditorId, branch_id: newBranchId, currency_id: newCurrencyId } });
    }
});

watch(() => form.payment_date, (newDate, oldDate) => {
    if (newDate !== oldDate) {
        form.allocations.forEach((allocation, index) => {
            if (allocation.asset_financing_agreement_id) {
                updateSchedule(index);
            }
        });
    }
});

async function updateSchedule(index) {
    const allocation = form.allocations[index];
    if (allocation.asset_financing_agreement_id) {
        try {
            const response = await axios.get(route('api.financing-schedule', {
                agreement_id: allocation.asset_financing_agreement_id,
                payment_date: form.payment_date,
            }));
            const schedule = response.data;
            
            if (schedule && Object.keys(schedule).length > 0) {
                allocation.principal_amount = schedule.principal_amount > 0 ? schedule.principal_amount : 0;
                allocation.interest_amount = schedule.interest_amount > 0 ? schedule.interest_amount : 0;
                allocation.asset_financing_schedule_id = schedule.id;
            } else {
                allocation.principal_amount = 0;
                allocation.interest_amount = 0;
                allocation.asset_financing_schedule_id = null;
            }
        } catch (error) {
            console.error('Error fetching schedule:', error);
        }
    }
    else {
        allocation.principal_amount = 0;
        allocation.interest_amount = 0;
        allocation.asset_financing_schedule_id = null;
    }
}

const partnerUrl = computed(() => {
    return route('api.partners', { company_id: selectedCompany.value, roles: ['creditor'] });
});

const partnerTableHeaders = [
    { key: 'code', label: 'Code' },
    { key: 'name', label: 'Name' },
    { key: 'actions', label: '' }
];

const partnerName = ref(props.payment?.partner?.name || '');

watch(() => form.allocations, (newAllocations) => {
    form.principal_amount = newAllocations.reduce((sum, allocation) => sum + Number(allocation.principal_amount || 0), 0);
    form.interest_amount = newAllocations.reduce((sum, allocation) => sum + Number(allocation.interest_amount || 0), 0);
    form.total_paid_amount = form.principal_amount + form.interest_amount;
    
    newAllocations.forEach(allocation => {
        allocation.allocated_amount = Number(allocation.principal_amount || 0) + Number(allocation.interest_amount || 0);
    });
}, { deep: true });


function addAllocation() {
    form.allocations.push({
        asset_financing_agreement_id: null,
        asset_financing_schedule_id: null,
        allocated_amount: 0,
        principal_amount: 0,
        interest_amount: 0,
    });
}

function removeAllocation(index) {
    form.allocations.splice(index, 1);
}

function updateExchangeRate() {
    if (!form.currency_id || !selectedCompany.value) {
        form.exchange_rate = 1;
        return;
    }
    
    const currency = props.currencies.find(c => c.id == form.currency_id);
    if (currency && currency.company_rates) {
        const companyRate = currency.company_rates.find(rate => rate.company_id == selectedCompany.value);
        if (companyRate) {
            form.exchange_rate = companyRate.exchange_rate;
        }
    }
}

function submitForm() {
    if (props.payment) {
        form.put(route('asset-financing-payments.update', props.payment.id));
    } else {
        form.post(route('asset-financing-payments.store'));
    }
}
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCompany"
                        :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                        label="Perusahaan:"
                        :error="form.errors.company_id"
                        :disabled="!!props.payment"
                        required
                    />
                    <AppSelect
                        v-model="form.branch_id"
                        :options="props.branches.map(branch => ({ value: branch.id, label: branch.name }))"
                        label="Cabang:"
                        :error="form.errors.branch_id"
                        :disabled="!!props.payment"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.payment_date"
                        type="date"
                        label="Tanggal Pembayaran:"
                        :error="form.errors.payment_date"
                        required
                    />
                    <AppSelect
                        v-model="form.payment_method"
                        :options="paymentMethodOptions"
                        label="Metode Pembayaran:"
                        :error="form.errors.payment_method"
                        required
                    />
                </div>                
                <div class="grid grid-cols-1 gap-4">
                    <AppPopoverSearch
                        v-model="form.creditor_id"
                        label="Kreditor:"
                        placeholder="Pilih Kreditor"
                        :url="partnerUrl"
                        valueKey="id"
                        :displayKeys="['name']"
                        :tableHeaders="partnerTableHeaders"
                        :initialDisplayValue="partnerName"
                        :error="form.errors.creditor_id"
                        :modalTitle="'Pilih Kreditor'"
                        :disabled="!selectedCompany"
                        required
                    />
                </div>
                <div class="grid grid-cols-1 gap-4" v-if="form.payment_method === 'bank_transfer'">
                    <AppSelect
                        v-model="form.destination_bank_account_id"
                        :options="creditors.find(c => c.id === form.creditor_id)?.active_bank_accounts.map(account => ({ value: account.id, label: account.display_name }))"
                        label="Rekening Bank Tujuan:"
                        :error="form.errors.destination_bank_account_id"
                        :disabled="!form.creditor_id"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.currency_id"
                        :options="currencyOptions"
                        label="Mata Uang:"
                        placeholder="Pilih Mata Uang"
                        :error="form.errors.currency_id"
                        required
                    />
                    
                    <AppInput
                        v-model="form.exchange_rate"
                        :numberFormat="true"
                        label="Nilai Tukar:"
                        :error="form.errors.exchange_rate"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.source_account_id"
                        :options="sourceAccounts.map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Sumber:"
                        :error="form.errors.source_account_id"
                        required
                    />
                    <AppInput
                        v-model="form.reference"
                        label="Referensi:"
                        :error="form.errors.reference"
                    />
                </div>
                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                />
            </div>
            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Pembayaran Pembiayaan</h3>
                <p class="mb-2">Formulir ini digunakan untuk mencatat pembayaran angsuran untuk perjanjian pembiayaan aset. Pastikan semua informasi yang dimasukkan akurat.</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Pilih perusahaan dan cabang yang melakukan pembayaran.</li>
                    <li>Pilih kreditor yang menerima pembayaran.</li>
                    <li>Masukkan tanggal pembayaran dan nomor referensi jika ada.</li>
                    <li>Alokasikan pembayaran ke satu atau lebih perjanjian pembiayaan.</li>
                    <li>Masukkan jumlah pokok dan bunga untuk setiap alokasi.</li>
                    <li>Total pembayaran akan dihitung secara otomatis.</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto mt-6">
            <h4 class="text-lg font-semibold mb-2">Alokasi Pembayaran</h4>
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-72 px-1.5 py-1.5">Perjanjian Pembiayaan</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Pokok</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Bunga</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Jumlah Dialokasikan</th>
                        <th class="border border-gray-300 px-1.5 py-1.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(allocation, index) in form.allocations" :key="index">
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppSelect
                                v-model="allocation.asset_financing_agreement_id"
                                :options="agreements.map(a => ({ value: a.id, label: a.number + ' - ' + (a.asset_invoice.assets[0] ? a.asset_invoice.assets[0].name : '') }))"
                                :error="form.errors[`allocations.${index}.asset_financing_agreement_id`]"
                                @update:modelValue="updateSchedule(index)"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="allocation.principal_amount"
                                :numberFormat="true"
                                :error="form.errors[`allocations.${index}.principal_amount`]"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="allocation.interest_amount"
                                :numberFormat="true"
                                :error="form.errors[`allocations.${index}.interest_amount`]"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="allocation.allocated_amount"
                                :numberFormat="true"
                                :error="form.errors[`allocations.${index}.allocated_amount`]"
                                required
                                disabled
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                            <button type="button" @click="removeAllocation(index)" class="text-red-500 hover:text-red-700 mb-4">
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th class="text-right px-4 py-2">Total</th>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(form.principal_amount) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(form.interest_amount) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(form.total_paid_amount) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <div class="flex mt-2 mb-4">
                <button type="button" @click="addAllocation" class="flex items-center text-main-500 hover:text-main-700">
                    <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Alokasi
                </button>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit">
                {{ props.payment ? 'Ubah' : 'Tambah' }} Pembayaran
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('asset-financing-payments.index'))" class="ml-2">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 