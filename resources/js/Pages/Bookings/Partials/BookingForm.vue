<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const props = defineProps({
    booking: { type: Object, default: null },
    formOptions: Object,
});

const form = useForm({
    company_id: props.booking?.company_id || null,
    branch_id: props.booking?.branch_id || null,
    partner_id: props.booking?.partner_id || null,
    currency_id: props.booking?.currency_id || (page.props.primaryCurrency?.id || null),
    booking_type: props.booking?.booking_type || 'accommodation',
    held_until: props.booking?.held_until ? new Date(props.booking.held_until).toISOString().split('T')[0] : '',
    deposit_amount: props.booking?.deposit_amount || 0,
    source_channel: props.booking?.source_channel || null,
    notes: props.booking?.notes || '',
    lines: props.booking?.lines?.map((line) => ({
        product_id: line.product_id,
        product_variant_id: line.product_variant_id,
        resource_pool_id: line.resource_pool_id,
        start_datetime: line.start_datetime ? new Date(line.start_datetime).toISOString().slice(0, 16) : '',
        end_datetime: line.end_datetime ? new Date(line.end_datetime).toISOString().slice(0, 16) : '',
        qty: line.qty,
        unit_price: line.unit_price,
        tax_amount: line.tax_amount || 0,
        deposit_required: line.deposit_required || 0,
    })) || [createEmptyLine()],
    create_another: false,
});

const submitted = ref(false);

// Company selection
const selectedCompany = ref(
    form.company_id || (props.formOptions.companies.length > 1 ? null : props.formOptions.companies[0]?.id)
);

// Watch company change
watch(selectedCompany, (newCompanyId) => {
    form.company_id = newCompanyId;
    form.branch_id = null;
    form.partner_id = null;
});

// Auto-set company on mount
onMounted(() => {
    if (!form.company_id && props.formOptions.companies.length === 1) {
        selectedCompany.value = props.formOptions.companies[0].id;
    }
});

// Filtered branches by company
const filteredBranches = computed(() => {
    if (!selectedCompany.value) return [];
    return props.formOptions.branches.filter((b) => b.company_id === selectedCompany.value);
});

// Auto-select branch when only one exists
watch(filteredBranches, (branches) => {
    if (branches.length === 1 && !form.branch_id) {
        form.branch_id = branches[0].id;
    }
}, { immediate: true });

// Filtered customers by company
const filteredCustomers = computed(() => {
    if (!selectedCompany.value) return props.formOptions.customers;
    return props.formOptions.customers.filter((c) =>
        Array.isArray(c.company_ids) && c.company_ids.includes(selectedCompany.value)
    );
});

// Customer search for popover
const customerTableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'actions', label: '' },
];

const customerSearchUrl = computed(() => route('api.partners', {
    company_id: form.company_id,
    roles: ['customer'],
}));

const selectedCustomerLabel = computed(() => {
    const customer = props.formOptions.customers.find((c) => c.id === form.partner_id);
    return customer ? `${customer.code} — ${customer.name}` : '';
});

// Channel options
const channelOptions = computed(() => [
    { value: null, label: 'Pilih Channel' },
    ...Object.entries(props.formOptions.channels).map(([value, label]) => ({ value, label })),
]);

const totals = computed(() => {
    return form.lines.reduce(
        (carry, line) => {
            const qty = Number(line.qty) || 0;
            const price = Number(line.unit_price) || 0;
            const tax = Number(line.tax_amount) || 0;
            const lineTotal = qty * price;
            carry.subtotal += lineTotal;
            carry.tax += tax;
            return carry;
        },
        { subtotal: 0, tax: 0 }
    );
});

const grandTotal = computed(() => totals.value.subtotal + totals.value.tax);

function createEmptyLine() {
    return {
        product_id: null,
        product_variant_id: null,
        resource_pool_id: null,
        start_datetime: '',
        end_datetime: '',
        qty: 1,
        unit_price: 0,
        tax_amount: 0,
        deposit_required: 0,
    };
}

function addEntry() {
    form.lines.push(createEmptyLine());
}

function removeEntry(index) {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1);
    }
}

function getPoolsForProduct(productId) {
    if (!productId) return [];
    const product = props.formOptions.products.find((p) => p.id === productId);
    // Filter by selected branch if available
    let pools = product?.resource_pools || [];
    if (form.branch_id) {
        pools = pools.filter((p) => p.branch_id === form.branch_id);
    }
    return pools;
}

function handleProductChange(line) {
    line.product_variant_id = null;
    line.resource_pool_id = null;
    const pools = getPoolsForProduct(line.product_id);
    if (pools.length === 1) {
        line.resource_pool_id = pools[0].id;
    }
}

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    if (props.booking) {
        form.put(route('bookings.update', props.booking.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; },
        });
    } else {
        form.post(route('bookings.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            },
            onError: () => { submitted.value = false; },
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <!-- Company & Branch -->
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCompany"
                        :options="formOptions.companies.map((c) => ({ value: c.id, label: c.name }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        required
                        :error="form.errors.company_id"
                        :disabled="!!booking"
                    />

                    <AppSelect
                        v-model="form.branch_id"
                        :options="filteredBranches.map((b) => ({ value: b.id, label: b.name }))"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        required
                        :error="form.errors.branch_id"
                        :disabled="!selectedCompany || !!booking"
                    />
                </div>

                <!-- Customer & Currency -->
                <div class="grid grid-cols-2 gap-4">
                    <AppPopoverSearch
                        v-model="form.partner_id"
                        label="Pelanggan:"
                        placeholder="Pilih Pelanggan"
                        :url="customerSearchUrl"
                        :tableHeaders="customerTableHeaders"
                        :displayKeys="['code', 'name']"
                        :initialDisplayValue="selectedCustomerLabel"
                        modalTitle="Pilih Pelanggan"
                        required
                        :disabled="!selectedCompany"
                        :error="form.errors.partner_id"
                    />

                    <AppSelect
                        v-model="form.currency_id"
                        :options="formOptions.currencies.map((c) => ({ value: c.id, label: `${c.code} — ${c.name}` }))"
                        label="Mata Uang:"
                        required
                        :error="form.errors.currency_id"
                    />
                </div>

                <!-- Booking Type, Hold Until, Deposit -->
                <div class="grid grid-cols-3 gap-4">
                    <AppSelect
                        v-model="form.booking_type"
                        :options="formOptions.bookingTypeOptions"
                        label="Tipe Booking:"
                        required
                        :error="form.errors.booking_type"
                    />

                    <AppInput
                        v-model="form.held_until"
                        type="date"
                        label="Hold Sampai:"
                        :error="form.errors.held_until"
                    />

                    <AppInput
                        v-model="form.deposit_amount"
                        :numberFormat="true"
                        label="Deposit:"
                        :error="form.errors.deposit_amount"
                    />
                </div>

                <!-- Source Channel -->
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.source_channel"
                        :options="channelOptions"
                        label="Channel Sumber:"
                        :error="form.errors.source_channel"
                    />
                </div>

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    rows="2"
                    :error="form.errors.notes"
                />
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Booking</h3>
                <p class="mb-2">Booking adalah reservasi resource (kamar, kendaraan, ruangan, dll) untuk waktu tertentu.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan dan cabang</li>
                    <li>Pilih pelanggan yang akan booking</li>
                    <li>Pilih tipe booking (Akomodasi atau Rental)</li>
                    <li>Masukkan baris booking dengan produk, pool, waktu, dan harga</li>
                    <li>Deposit adalah uang muka yang harus dibayar customer</li>
                    <li>Hold sampai adalah batas waktu untuk konfirmasi booking</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto">
            <h2 class="text-lg font-semibold">Baris Booking</h2>
            <p class="text-sm text-gray-500 mb-4">Masukkan produk, resource pool, waktu mulai/selesai, jumlah, dan harga.</p>

            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-48 px-1.5 py-1.5">Produk</th>
                        <th class="border border-gray-300 text-sm min-w-40 px-1.5 py-1.5">Pool</th>
                        <th class="border border-gray-300 text-sm min-w-40 px-1.5 py-1.5">Mulai</th>
                        <th class="border border-gray-300 text-sm min-w-40 px-1.5 py-1.5">Selesai</th>
                        <th class="border border-gray-300 text-sm min-w-20 px-1.5 py-1.5">Qty</th>
                        <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">Harga</th>
                        <th class="border border-gray-300 px-1.5 py-1.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(line, index) in form.lines" :key="index">
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppSelect
                                v-model="line.product_id"
                                :options="formOptions.products.map((p) => ({ value: p.id, label: p.name }))"
                                placeholder="Pilih Produk"
                                :error="form.errors[`lines.${index}.product_id`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                @update:modelValue="handleProductChange(line)"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppSelect
                                v-model="line.resource_pool_id"
                                :options="getPoolsForProduct(line.product_id).map((p) => ({ value: p.id, label: p.name }))"
                                placeholder="Pilih Pool"
                                :disabled="!line.product_id"
                                :error="form.errors[`lines.${index}.resource_pool_id`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="line.start_datetime"
                                type="datetime-local"
                                :error="form.errors[`lines.${index}.start_datetime`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="line.end_datetime"
                                type="datetime-local"
                                :error="form.errors[`lines.${index}.end_datetime`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="line.qty"
                                type="number"
                                min="1"
                                :error="form.errors[`lines.${index}.qty`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="line.unit_price"
                                :numberFormat="true"
                                :error="form.errors[`lines.${index}.unit_price`]"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                            <button
                                v-if="form.lines.length > 1"
                                type="button"
                                @click="removeEntry(index)"
                                class="text-red-500 hover:text-red-700"
                            >
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>

                <tfoot>
                    <tr class="text-sm">
                        <th colspan="5" class="border border-gray-300 px-4 py-2 text-right">Total</th>
                        <th class="border border-gray-300 px-4 py-2 text-left">{{ formatNumber(grandTotal) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>

            <div class="flex mt-2 mb-4">
                <button type="button" @click="addEntry" class="flex items-center text-main-500 hover:text-main-700">
                    <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Baris
                </button>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ props.booking ? 'Ubah' : 'Simpan' }} Booking
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.booking" type="button" @click="submitForm(true)" class="mr-2">
                Simpan & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('bookings.index'))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
