<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppModal from '@/Components/AppModal.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import { ref, watch, computed, onMounted } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { formatNumber } from '@/utils/numberFormat';
import axios from 'axios';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';

const page = usePage();

const props = defineProps({
    payment: Object,
    partners: Array,
    companies: Array,
    branches: Array,
    sourceAccounts: Array,
    currencies: Array,
    filters: Object,
    paymentMethods: Object,
    paymentTypes: Object,
    assetInvoices: Array,
});

const form = useForm({
    payment_date: props.payment?.payment_date || new Date().toISOString().split('T')[0],
    type: props.payment?.type || 'purchase',
    branch_id: props.payment?.branch_id || null,
    partner_id: props.payment?.partner_id || null,
    currency_id: props.payment?.currency_id || page.props.primaryCurrency?.id || null,
    exchange_rate: props.payment?.exchange_rate || 1,
    source_account_id: props.payment?.source_account_id || null,
    destination_bank_account_id: props.payment?.destination_bank_account_id || null,
    reference: props.payment?.reference || '',
    amount: props.payment?.amount || 0,
    payment_method: props.payment?.payment_method || 'cash',
    notes: props.payment?.notes || '',
    allocations: props.payment?.allocations?.map(allocation => ({
        id: allocation.id,
        asset_invoice_id: allocation.asset_invoice_id,
        allocated_amount: allocation.allocated_amount,
    })) || [
        { asset_invoice_id: null, allocated_amount: 0 },
    ],
    create_another: false,
});

const submitted = ref(false);
const availableInvoices = ref([...props.assetInvoices || []]);
const showBankAccountModal = ref(false);
const partnerBankAccounts = ref([]);
const selectedCompany = ref(props.payment?.branch?.branch_group?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id));

// Bank account form
const bankAccountForm = useForm({
    partner_id: null,
    bank_name: '',
    account_number: '',
    account_holder_name: '',
    branch_name: '',
    swift_code: '',
    iban: '',
    currency: 'IDR',
    is_primary: false,
    notes: '',
});

// Watch company selection to load branches
watch(selectedCompany, (newCompanyId) => {
    if (!props.payment) {
        form.branch_id = null;
        form.partner_id = null;
        form.source_account_id = null;
        form.currency_id = page.props.primaryCurrency?.id || null;
        form.exchange_rate = 1;
        router.reload({ only: ['branches', 'partners', 'sourceAccounts', 'assetInvoices', 'currencies'], data: { company_id: newCompanyId } });
    }
}, { immediate: true });

// Watch branches to auto-select if only one branch
watch(
    () => props.branches,
    (newBranches) => {
        if (!props.payment && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

// Watch partners to auto-select if only one partner
watch(
    () => props.partners,
    (newPartners) => {
        if (!props.payment && newPartners.length === 1) {
            form.partner_id = newPartners[0].id;
        }
    },
    { immediate: true }
);

// Watch source accounts to auto-select if only one account
watch(
    () => props.sourceAccounts,
    (newAccounts) => {
        if (!props.payment && newAccounts.length === 1) {
            form.source_account_id = newAccounts[0].id;
        }
    },
    { immediate: true }
);

onMounted(() => {
    selectedCompany.value = props.payment?.branch?.branch_group?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id);
    if (!props.payment && props.branches.length === 1) {
        form.branch_id = props.branches[0].id;
    }
    if (!props.payment && props.partners.length === 1) {
        form.partner_id = props.partners[0].id;
    }
    if (!props.payment && props.sourceAccounts.length === 1) {
        form.source_account_id = props.sourceAccounts[0].id;
    }
});

// Watch partner selection to load their outstanding invoices and bank accounts
watch(() => form.partner_id, (newPartnerId) => {
    if (newPartnerId) {
        // Load outstanding invoices for the selected partner and company
        if (!props.payment && selectedCompany.value) {
            router.reload({ 
                only: ['assetInvoices'], 
                data: { 
                    partner_id: newPartnerId,
                    company_id: selectedCompany.value,
                    currency_id: form.currency_id,
                    type: form.type
                },
                onSuccess: (page) => {
                    availableInvoices.value = page.props.assetInvoices || [];
                }
            });
        }
        
        // Load partner bank accounts
        loadPartnerBankAccounts(newPartnerId);
    } else {
        partnerBankAccounts.value = [];
        availableInvoices.value = [];
    }
});

// Watch currency selection to reload invoices
watch(() => form.currency_id, (newCurrencyId) => {
    if (newCurrencyId && form.partner_id && selectedCompany.value && !props.payment) {
        router.reload({ 
            only: ['assetInvoices'], 
            data: { 
                partner_id: form.partner_id,
                company_id: selectedCompany.value,
                currency_id: newCurrencyId,
                type: form.type
            },
            onSuccess: (page) => {
                availableInvoices.value = page.props.assetInvoices || [];
                // Only remove allocations with invoices that have different currency
                form.allocations = form.allocations.filter(allocation => {
                    // Keep empty allocations (no invoice selected)
                    if (!allocation.asset_invoice_id) {
                        return true;
                    }
                    
                    // Find the invoice for this allocation
                    const invoice = availableInvoices.value.find(inv => inv.id == allocation.asset_invoice_id);
                    
                    // Keep if invoice exists and has matching currency, otherwise remove
                    return invoice && invoice.currency_id == newCurrencyId;
                });
                
                // Ensure there's always at least one empty row
                if (form.allocations.length === 0) {
                    form.allocations.push({ asset_invoice_id: null, allocated_amount: 0 });
                }
            }
        });
    }
});

// Watch type selection to reload invoices
watch(() => form.type, (newType) => {
    if (newType && form.partner_id && selectedCompany.value && !props.payment) {
        router.reload({ 
            only: ['assetInvoices'], 
            data: { 
                partner_id: form.partner_id,
                company_id: selectedCompany.value,
                currency_id: form.currency_id,
                type: newType
            },
            onSuccess: (page) => {
                availableInvoices.value = page.props.assetInvoices || [];
                // Clear existing allocations when type changes as invoices may be different
                form.allocations = [{ asset_invoice_id: null, allocated_amount: 0 }];
            }
        });
    }
});

// Load bank accounts when component mounts if editing
if (props.payment?.partner_id) {
    loadPartnerBankAccounts(props.payment.partner_id);
}

function loadPartnerBankAccounts(partnerId) {
    if (!partnerId) {
        partnerBankAccounts.value = [];
        return;
    }
    
    const selectedPartner = props.partners.find(p => p.id == partnerId);
    if (selectedPartner?.active_bank_accounts) {
        partnerBankAccounts.value = selectedPartner.active_bank_accounts;
    } else {
        // Fallback: load via API
        axios.get(route('partners.bank-accounts', partnerId))
            .then(response => {
                partnerBankAccounts.value = response.data.data;
            })
            .catch(error => {
                console.error('Error loading bank accounts:', error);
                partnerBankAccounts.value = [];
            });
    }
}

// Computed options for invoice selection
const invoiceOptions = computed(() => {
    return availableInvoices.value.map(invoice => {
        const currencySymbol = invoice.currency?.symbol || '';
        const totalAmount = formatNumber(invoice.total_amount);
        const outstandingAmount = formatNumber(invoice.outstanding_amount);
        const invoiceDate = new Date(invoice.invoice_date).toLocaleDateString('id-ID');
        
        return {
            value: invoice.id,
            label: `${invoice.number} - ${currencySymbol}${totalAmount} (Sisa: ${currencySymbol}${outstandingAmount}) - ${invoiceDate}`,
            outstanding: invoice.outstanding_amount,
            totalAmount: invoice.total_amount,
            currency: invoice.currency,
            invoice: invoice
        };
    });
});

const partnerUrl = computed(() => {
    let roles = [];
    if (form.type === 'sales') {
        roles = ['asset_customer'];
    } else {
        roles = ['asset_supplier'];
    }
    return route('api.partners', { company_id: selectedCompany.value, roles: roles });
});

const partnerTableHeaders = [
    { key: 'code', label: 'Code' },
    { key: 'name', label: 'Name' },
    { key: 'actions', label: '' }
];

const partnerName = ref(props.payment?.partner?.name || '');

// Computed source account options
const sourceAccountOptions = computed(() => {
    return props.sourceAccounts.map(account => ({
        value: account.id,
        label: `${account.code} - ${account.name}`
    }));
});

// Computed bank account options
const bankAccountOptions = computed(() => {
    return partnerBankAccounts.value.map(bankAccount => ({
        value: bankAccount.id,
        label: bankAccount.display_name || `${bankAccount.bank_name} - ${bankAccount.account_number}`
    }));
});

// Computed payment method options
const paymentMethodOptions = computed(() => {
    return Object.entries(props.paymentMethods).map(([value, label]) => ({
        value,
        label
    }));
});

// Computed payment type options (excluding lease)
const paymentTypeOptions = computed(() => {
    return Object.entries(props.paymentTypes)
        .filter(([value, label]) => value !== 'lease') // Remove lease option
        .map(([value, label]) => ({
            value,
            label
        }));
});

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

// Computed primary currency amount
const primaryCurrencyAmount = computed(() => {
    if (form.currency_id == page.props.primaryCurrency?.id) {
        return form.amount;
    }
    return (Number(form.amount) || 0) * (Number(form.exchange_rate) || 1);
});

// Computed total allocated amount
const totalAllocatedAmount = computed(() => {
    return form.allocations.reduce((sum, allocation) => {
        return sum + (Number(allocation.allocated_amount) || 0);
    }, 0);
});

// Computed allocation difference
const allocationDifference = computed(() => {
    return (Number(form.amount) || 0) - totalAllocatedAmount.value;
});

// Check if bank transfer requires destination bank account
const requiresBankAccount = computed(() => {
    return form.payment_method === 'bank_transfer';
});

function addAllocation() {
    form.allocations.push({ 
        asset_invoice_id: null, 
        allocated_amount: Math.max(0, allocationDifference.value)
    });
}

function removeAllocation(index) {
    form.allocations.splice(index, 1);
}

function updateAllocationAmount(index) {
    const selectedInvoiceId = form.allocations[index].asset_invoice_id;
    const selectedInvoice = availableInvoices.value.find(invoice => invoice.id == selectedInvoiceId);
    
    if (selectedInvoice && selectedInvoice.outstanding_amount) {
        const maxAmount = Math.min(selectedInvoice.outstanding_amount, allocationDifference.value + (Number(form.allocations[index].allocated_amount) || 0));
        form.allocations[index].allocated_amount = maxAmount;
    }
}

function autoAllocate() {
    if (!form.amount) return;
    
    let remainingAmount = Number(form.amount);
    form.allocations = [];
    
    for (const invoice of availableInvoices.value) {
        if (remainingAmount <= 0) break;
        
        const allocateAmount = Math.min(remainingAmount, invoice.outstanding_amount);
        if (allocateAmount > 0) {
            form.allocations.push({
                asset_invoice_id: invoice.id,
                allocated_amount: allocateAmount
            });
            remainingAmount -= allocateAmount;
        }
    }
    
    // Add empty allocation if there's remaining amount
    if (remainingAmount > 0) {
        form.allocations.push({
            asset_invoice_id: null,
            allocated_amount: remainingAmount
        });
    }
}

function openBankAccountModal() {
    if (!form.partner_id) {
        alert('Pilih partner terlebih dahulu');
        return;
    }
    
    bankAccountForm.reset();
    bankAccountForm.partner_id = form.partner_id;
    bankAccountForm.account_holder_name = props.partners.find(p => p.id == form.partner_id)?.name || '';
    showBankAccountModal.value = true;
}

function saveBankAccount() {
    bankAccountForm.post(route('partner-bank-accounts.store'), {
        preserveScroll: true,
        onSuccess: (response) => {
            // Refresh bank accounts list
            loadPartnerBankAccounts(form.partner_id);
            showBankAccountModal.value = false;
            bankAccountForm.reset();
        },
        onError: (errors) => {
            console.error('Error saving bank account:', errors);
        }
    });
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

// Watch currency selection to update exchange rate
watch(() => form.currency_id, () => {
    updateExchangeRate();
});

// Watch currencies prop to auto-select if only one currency
watch(
    () => props.currencies,
    (newCurrencies) => {
        if (!props.payment && newCurrencies.length === 1) {
            form.currency_id = newCurrencies[0].id;
        }
    },
    { immediate: true }
);

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    
    if (props.payment) {
        form.put(route('asset-invoice-payments.update', props.payment.id), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
            },
            onError: () => {
                submitted.value = false;
            }
        });
    } else {
        form.post(route('asset-invoice-payments.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            },
            onError: () => {
                submitted.value = false;
            }
        });
    }
}

// Helper function to get invoice details for display
function getInvoiceDetails(invoiceId) {
    const invoice = availableInvoices.value.find(inv => inv.id == invoiceId);
    if (!invoice) return { total: 0, outstanding: 0, currency: null };
    
    return {
        total: invoice.total_amount,
        outstanding: invoice.outstanding_amount,
        currency: invoice.currency
    };
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">                
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCompany"
                        :options="props.companies.map(company => ({
                            value: company.id,
                            label: company.name
                        }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        :error="form.errors.company_id"
                        :disabled="!!props.payment"
                        required
                    />
                    
                    <AppSelect
                        v-model="form.branch_id"
                        :options="props.branches.map(branch => ({
                            value: branch.id,
                            label: branch.name
                        }))"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!!props.payment"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.payment_date"
                        type="date"
                        label="Tanggal Bayar:"
                        :error="form.errors.payment_date"
                        required
                    />
                    
                    <AppSelect
                        v-model="form.type"
                        :options="paymentTypeOptions"
                        label="Tipe Pembayaran:"
                        placeholder="Pilih Tipe"
                        :error="form.errors.type"
                        required
                    />
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <AppPopoverSearch
                        v-model="form.partner_id"
                        :label="form.type === 'sales' ? 'Pelanggan:' : 'Supplier:'"
                        :placeholder="form.type === 'sales' ? 'Pilih Pelanggan' : 'Pilih Supplier'"
                        :url="partnerUrl"
                        valueKey="id"
                        :displayKeys="['name']"
                        :tableHeaders="partnerTableHeaders"
                        :initialDisplayValue="partnerName"
                        :error="form.errors.partner_id"
                        :modalTitle="form.type === 'sales' ? 'Pilih Pelanggan Aset' : 'Pilih Supplier Aset'"
                        :disabled="!selectedCompany"
                        required
                    />
                    
                    <AppSelect
                        v-model="form.source_account_id"
                        :options="sourceAccountOptions"
                        label="Akun Sumber:"
                        placeholder="Pilih Akun Sumber"
                        :error="form.errors.source_account_id"
                        required
                    />
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.payment_method"
                        :options="paymentMethodOptions"
                        label="Metode Pembayaran:"
                        placeholder="Pilih Metode"
                        :error="form.errors.payment_method"
                        required
                    />
                    
                    <div v-if="requiresBankAccount">
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <AppSelect
                                    v-model="form.destination_bank_account_id"
                                    :options="bankAccountOptions"
                                    label="Rekening Tujuan:"
                                    placeholder="Pilih Rekening"
                                    :error="form.errors.destination_bank_account_id"
                                >
                                    <template #suffix>
                                        <button
                                            type="button"
                                            @click.stop="openBankAccountModal"
                                            class="text-main-600 hover:text-main-800 rounded hover:bg-main-50 transition-colors"
                                            title="Tambah Rekening Bank"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                            </svg>
                                        </button>
                                    </template>
                                </AppSelect>
                            </div>
                        </div>
                    </div>
                    <div v-else>
                        <AppInput
                            v-model="form.reference"
                            label="Referensi:"
                            :error="form.errors.reference"
                        />
                    </div>
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
                    <div>
                        <AppInput
                            v-model="form.amount"
                            :numberFormat="true"
                            :prefix="currentCurrencySymbol"
                            label="Jumlah Pembayaran:"
                            :error="form.errors.amount"
                            required
                        />
                        <div v-if="form.currency_id != null && form.currency_id != page.props.primaryCurrency?.id" class="text-gray-500 text-xs mt-1">
                            = {{ page.props.primaryCurrency?.symbol }} {{ formatNumber(primaryCurrencyAmount) }}
                        </div>
                    </div>
                    
                    <AppInput
                        v-if="requiresBankAccount"
                        v-model="form.reference"
                        label="Referensi:"
                        :error="form.errors.reference"
                    />
                    <div v-else></div>
                </div>
                
                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                />
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Pembayaran</h3>
                <p class="mb-2">Pembayaran ini akan dialokasikan ke faktur aset yang masih memiliki saldo terutang.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih tanggal pembayaran</li>
                    <li>Pilih tipe pembayaran sesuai jenis transaksi</li>
                    <li>Pilih perusahaan dan cabang terlebih dahulu</li>
                    <li>Partner dan akun sumber akan difilter berdasarkan perusahaan</li>
                    <li>Pilih partner yang melakukan pembayaran</li>
                    <li>Pilih akun sumber pembayaran</li>
                    <li>Untuk transfer bank, pilih rekening tujuan</li>
                    <li>Masukkan jumlah total pembayaran</li>
                    <li>Alokasikan pembayaran ke faktur yang sesuai</li>
                </ul>
                
                <div class="mt-4 p-3 bg-white rounded border">
                    <div class="text-sm font-semibold">Status Alokasi:</div>
                    <div class="text-lg font-bold" :class="allocationDifference === 0 ? 'text-green-600' : 'text-red-600'">
                        {{ currentCurrencySymbol }}{{ formatNumber(allocationDifference) }}
                    </div>
                    <div class="text-xs text-gray-600">
                        {{ allocationDifference === 0 ? 'Seimbang' : allocationDifference > 0 ? 'Kelebihan' : 'Kekurangan' }}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-lg font-semibold">Alokasi Pembayaran</h4>
                <div class="flex gap-2">
                    <AppUtilityButton type="button" @click="autoAllocate" :disabled="!form.amount || !availableInvoices.length">
                        Auto Alokasi
                    </AppUtilityButton>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 text-sm min-w-64 px-1.5 py-1.5">Faktur Aset</th>
                            <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">Total Faktur</th>
                            <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">Sisa Tagihan</th>
                            <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Jumlah Dibayar</th>
                            <th class="border border-gray-300 px-1.5 py-1.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(allocation, index) in form.allocations" :key="index">
                            <td class="border border-gray-300 px-1.5 py-1.5">
                                <AppSelect
                                    v-model="allocation.asset_invoice_id"
                                    :options="invoiceOptions"
                                    :error="form.errors[`allocations.${index}.asset_invoice_id`]"
                                    :maxRows="3"
                                    @update:modelValue="updateAllocationAmount(index)"
                                    required
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-right">
                                <template v-if="allocation.asset_invoice_id">
                                    <div class="text-sm">
                                        {{ getInvoiceDetails(allocation.asset_invoice_id).currency?.symbol }} {{ formatNumber(getInvoiceDetails(allocation.asset_invoice_id).total) }}
                                        <div v-if="form.currency_id != page.props.primaryCurrency?.id" class="text-gray-500 text-xs mt-1">
                                            = {{ page.props.primaryCurrency?.symbol }} {{ formatNumber(primaryCurrencyAmount) }}
                                        </div>
                                    </div>
                                </template>
                                <div v-else class="text-gray-400 text-sm">-</div>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-right">
                                <template v-if="allocation.asset_invoice_id">
                                    <div class="text-sm">
                                        {{ getInvoiceDetails(allocation.asset_invoice_id).currency?.symbol }} {{ formatNumber(getInvoiceDetails(allocation.asset_invoice_id).outstanding) }}
                                        <div v-if="form.currency_id != page.props.primaryCurrency?.id" class="text-gray-500 text-xs mt-1">
                                            = {{ page.props.primaryCurrency?.symbol }} {{ formatNumber(primaryCurrencyAmount) }}
                                        </div>
                                    </div>
                                </template>
                                <div v-else class="text-gray-400 text-sm">-</div>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5">
                                <AppInput
                                    v-model="allocation.allocated_amount"
                                    :numberFormat="true"
                                    :prefix="currentCurrencySymbol"
                                    :error="form.errors[`allocations.${index}.allocated_amount`]"
                                    required
                                    :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                />
                                <div v-if="form.currency_id != page.props.primaryCurrency?.id" class="text-gray-500 text-xs mt-1">
                                    = {{ page.props.primaryCurrency?.symbol }} {{ formatNumber(primaryCurrencyAmount) }}
                                </div>
                            </td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                                <button type="button" @click="removeAllocation(index)" class="text-red-500 hover:text-red-700">
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="text-sm bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2 text-right" colspan="3">Total Dialokasikan</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">
                                {{ currentCurrencySymbol }} {{ formatNumber(totalAllocatedAmount) }}
                                <div v-if="form.currency_id != page.props.primaryCurrency?.id" class="text-gray-500 text-xs mt-1">
                                    = {{ page.props.primaryCurrency?.symbol }} {{ formatNumber(primaryCurrencyAmount) }}
                                </div>
                            </th>
                            <th class="border border-gray-300"></th>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="mt-4 flex justify-start">
                    <button type="button" @click="addAllocation" class="flex items-center text-main-500 hover:text-main-700">
                        <PlusCircleIcon class="w-5 h-5 mr-1" /> Tambah Alokasi
                    </button>
                </div>
            </div>
        </div>
        
        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="allocationDifference !== 0">
                {{ props.payment ? 'Ubah' : 'Tambah' }} Pembayaran
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.payment" type="button" @click="submitForm(true)" class="mr-2" :disabled="allocationDifference !== 0">
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('asset-invoice-payments.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>

    <!-- Bank Account Modal -->
    <AppModal :show="showBankAccountModal" @close="showBankAccountModal = false">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">Tambah Rekening Bank</h3>
            
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="bankAccountForm.bank_name"
                        label="Nama Bank:"
                        :error="bankAccountForm.errors.bank_name"
                        required
                    />
                    
                    <AppInput
                        v-model="bankAccountForm.account_number"
                        label="Nomor Rekening:"
                        :error="bankAccountForm.errors.account_number"
                        required
                    />
                </div>
                
                <AppInput
                    v-model="bankAccountForm.account_holder_name"
                    label="Nama Pemilik Rekening:"
                    :error="bankAccountForm.errors.account_holder_name"
                    required
                />
                
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="bankAccountForm.branch_name"
                        label="Cabang Bank:"
                        :error="bankAccountForm.errors.branch_name"
                    />
                    
                    <AppInput
                        v-model="bankAccountForm.currency"
                        label="Mata Uang:"
                        :error="bankAccountForm.errors.currency"
                        placeholder="IDR"
                    />
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="bankAccountForm.swift_code"
                        label="Kode SWIFT:"
                        :error="bankAccountForm.errors.swift_code"
                    />
                    
                    <AppInput
                        v-model="bankAccountForm.iban"
                        label="IBAN:"
                        :error="bankAccountForm.errors.iban"
                    />
                </div>
                
                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        id="is_primary" 
                        v-model="bankAccountForm.is_primary" 
                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                    >
                    <label for="is_primary" class="ml-2 text-sm font-medium text-gray-900">Jadikan rekening utama</label>
                </div>
                
                <AppTextarea
                    v-model="bankAccountForm.notes"
                    label="Catatan:"
                    :error="bankAccountForm.errors.notes"
                />
            </div>
            
            <div class="mt-6 flex justify-end gap-2">
                <AppSecondaryButton @click="showBankAccountModal = false">
                    Batal
                </AppSecondaryButton>
                <AppPrimaryButton @click="saveBankAccount" :disabled="bankAccountForm.processing">
                    Simpan
                </AppPrimaryButton>
            </div>
        </div>
    </AppModal>
</template> 