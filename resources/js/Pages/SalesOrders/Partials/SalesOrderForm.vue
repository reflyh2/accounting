<script setup>
import { computed, reactive, watch } from 'vue';
import axios from 'axios';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import AppHint from '@/Components/AppHint.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    formOptions: {
        type: Object,
        required: true,
    },
    mode: {
        type: String,
        default: 'create',
    },
    submitLabel: {
        type: String,
        default: 'Simpan',
    },
    onSubmit: {
        type: Function,
        required: true,
    },
});

const form = props.form;
const availabilityState = reactive({});

const customerSearchUrl = computed(() =>
    route('api.partners', {
        company_id: form.company_id || undefined,
        roles: ['customer'],
    })
);

const selectedCustomerLabel = computed(() => {
    const customer = props.formOptions.customers.find((candidate) => candidate.id === form.partner_id);
    if (!customer) {
        return '';
    }

    return `${customer.code} — ${customer.name}`;
});

const filteredBranches = computed(() => {
    if (!form.company_id) {
        return props.formOptions.branches;
    }

    return props.formOptions.branches.filter((branch) => branch.company_id === form.company_id);
});

const filteredCustomers = computed(() => {
    if (!form.company_id) {
        return props.formOptions.customers;
    }

    return props.formOptions.customers.filter((customer) =>
        Array.isArray(customer.company_ids) && customer.company_ids.includes(form.company_id)
    );
});

const filteredProducts = computed(() => {
    if (!form.company_id) {
        return props.formOptions.products;
    }

    return props.formOptions.products.filter((product) =>
        Array.isArray(product.company_ids) && product.company_ids.includes(form.company_id)
    );
});

const filteredPriceLists = computed(() => {
    if (!form.company_id) {
        return props.formOptions.priceLists;
    }

    return props.formOptions.priceLists.filter(
        (priceList) => !priceList.company_id || priceList.company_id === form.company_id
    );
});

const filteredLocations = computed(() => {
    if (!form.branch_id) {
        return props.formOptions.locations;
    }

    return props.formOptions.locations.filter((location) => location.branch_id === form.branch_id);
});

const variantLookup = computed(() => {
    const lookup = {};

    props.formOptions.products.forEach((product) => {
        product.variants.forEach((variant) => {
            lookup[variant.id] = {
                ...variant,
                product_name: product.name,
                company_ids: product.company_ids,
            };
        });
    });

    return lookup;
});

const variantOptions = computed(() =>
    filteredProducts.value.flatMap((product) =>
        product.variants.map((variant) => ({
            value: variant.id,
            label: `${product.name} — ${variant.sku}`,
            uom: variant.uom,
        }))
    )
);

const uomOptions = computed(() => {
    if (!form.company_id) {
        return props.formOptions.uoms;
    }

    return props.formOptions.uoms.filter((uom) => uom.company_id === form.company_id);
});

const totals = computed(() => {
    return form.lines.reduce(
        (carry, line) => {
            const quantity = Number(line.quantity) || 0;
            const price = Number(line.unit_price) || 0;
            const taxRate = Number(line.tax_rate) || 0;

            const lineSubtotal = quantity * price;
            const lineTax = lineSubtotal * (taxRate / 100);

            carry.subtotal += lineSubtotal;
            carry.tax += lineTax;

            return carry;
        },
        { subtotal: 0, tax: 0 }
    );
});

const grandTotal = computed(() => totals.value.subtotal + totals.value.tax);

watch(
    () => form.branch_id,
    (branchId) => {
        if (!branchId) {
            return;
        }

        const branch = props.formOptions.branches.find((candidate) => candidate.id === branchId);
        if (branch?.company_id) {
            form.company_id = branch.company_id;
        }

        form.lines.forEach((line, index) => {
            if (line.product_variant_id) {
                fetchAvailability(index);
            }
        });
    }
);

watch(
    () => form.company_id,
    () => {
        if (form.branch_id && !filteredBranches.value.some((branch) => branch.id === form.branch_id)) {
            form.branch_id = '';
        }

        if (form.partner_id && !filteredCustomers.value.some((customer) => customer.id === form.partner_id)) {
            form.partner_id = '';
        }

        if (form.price_list_id && !filteredPriceLists.value.some((priceList) => priceList.id === form.price_list_id)) {
            form.price_list_id = '';
        }

        form.lines.forEach((line, index) => {
            if (!line.product_variant_id) {
                return;
            }

            if (!variantOptions.value.some((option) => option.value === line.product_variant_id)) {
                resetLine(line);
                availabilityState[index] = null;
            }
        });
    }
);

watch(
    () => form.price_list_id,
    (priceListId) => {
        if (!priceListId) {
            return;
        }

        const priceList = props.formOptions.priceLists.find((candidate) => candidate.id === priceListId);
        if (priceList?.currency?.id) {
            form.currency_id = priceList.currency.id;
        }
    }
);

function lineUomOptions(line) {
    const variant = variantLookup.value[line.product_variant_id];
    if (!variant?.uom?.kind) {
        return uomOptions.value;
    }

    return uomOptions.value.filter((uom) => uom.kind === variant.uom.kind);
}

function createEmptyLine() {
    return {
        product_variant_id: '',
        uom_id: '',
        quantity: 1,
        unit_price: '',
        tax_rate: '',
        description: '',
        requested_delivery_date: '',
        reservation_location_id: '',
    };
}

function addLine() {
    form.lines.push(createEmptyLine());
}

function removeLine(index) {
    if (form.lines.length === 1) {
        return;
    }

    form.lines.splice(index, 1);
    delete availabilityState[index];
}

function resetLine(line) {
    line.product_variant_id = '';
    line.uom_id = '';
    line.quantity = 1;
    line.unit_price = '';
    line.tax_rate = '';
    line.description = '';
    line.requested_delivery_date = '';
    line.reservation_location_id = '';
}

function syncVariant(index) {
    const line = form.lines[index];
    const variant = variantLookup.value[line.product_variant_id];

    if (!variant) {
        availabilityState[index] = null;
        return;
    }

    line.uom_id = variant.uom?.id || line.uom_id;

    if (!line.description) {
        line.description = variant.product_name;
    }

    fetchAvailability(index);
}

async function fetchAvailability(index) {
    const line = form.lines[index];
    if (!line.product_variant_id || !form.branch_id) {
        availabilityState[index] = null;
        return;
    }

    try {
        const { data } = await axios.get(route('api.inventory.availability'), {
            params: {
                product_variant_id: line.product_variant_id,
                branch_id: form.branch_id,
            },
        });
        availabilityState[index] = data;
    } catch (error) {
        availabilityState[index] = null;
    }
}

function availabilityLabel(index) {
    const snapshot = availabilityState[index];
    if (!snapshot) {
        return '—';
    }

    return `On hand: ${formatNumber(snapshot.on_hand)} • Reserved: ${formatNumber(snapshot.reserved)} • Tersedia: ${formatNumber(snapshot.available)}`;
}
</script>

<template>
    <form @submit.prevent="onSubmit" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <AppSelect
                    v-model="form.company_id"
                    :options="formOptions.companies.map((company) => ({ value: company.id, label: company.name }))"
                    label="Perusahaan"
                    required
                    placeholder="Pilih Perusahaan"
                />

                <AppSelect
                    v-model="form.branch_id"
                    :options="filteredBranches.map((branch) => ({ value: branch.id, label: branch.name }))"
                    label="Cabang"
                    required
                    placeholder="Pilih Cabang"
                />

                <AppPopoverSearch
                    v-model="form.partner_id"
                    label="Pelanggan"
                    placeholder="Pilih Pelanggan"
                    hint="Gunakan pencarian untuk menemukan pelanggan lintas perusahaan."
                    :url="customerSearchUrl"
                    :tableHeaders="[
                        { key: 'code', label: 'Kode' },
                        { key: 'name', label: 'Nama' },
                        { key: 'actions', label: '' },
                    ]"
                    :displayKeys="['code', 'name']"
                    :initialDisplayValue="selectedCustomerLabel"
                    :disabled="!form.company_id"
                    modalTitle="Pilih Pelanggan"
                    required
                    :error="form.errors?.partner_id"
                />

                <AppSelect
                    v-model="form.price_list_id"
                    :options="filteredPriceLists.map((priceList) => ({
                        value: priceList.id,
                        label: priceList.currency?.code ? `${priceList.name} — ${priceList.currency.code}` : priceList.name,
                    }))"
                    label="Daftar Harga"
                    placeholder="Pilih Daftar Harga"
                />

                <AppSelect
                    v-model="form.currency_id"
                    :options="formOptions.currencies.map((currency) => ({ value: currency.id, label: `${currency.code} — ${currency.name}` }))"
                    label="Mata Uang"
                    required
                />

                <AppInput
                    v-model="form.exchange_rate"
                    type="number"
                    step="0.0001"
                    label="Kurs"
                    required
                    min="0.0001"
                />
            </div>

            <div class="space-y-4">
                <AppInput
                    v-model="form.order_date"
                    type="date"
                    label="Tanggal Order"
                    required
                />

                <AppInput
                    v-model="form.expected_delivery_date"
                    type="date"
                    label="Estimasi Kirim"
                />

                <AppInput
                    v-model="form.quote_valid_until"
                    type="date"
                    label="Berlaku Sampai"
                />

                <AppInput
                    v-model="form.customer_reference"
                    label="Referensi Pelanggan"
                    placeholder="Nomor referensi (opsional)"
                />

                <AppInput
                    v-model="form.sales_channel"
                    label="Channel Penjualan"
                    placeholder="Online, Direct, dsb"
                />

                <AppInput
                    v-model="form.payment_terms"
                    label="Syarat Pembayaran"
                    placeholder="Net 30, COD, dll"
                />
            </div>
        </div>

        <div class="bg-gray-50 border border-gray-200 p-4 rounded-lg">
            <AppCheckbox v-model="form.reserve_stock" label="Reservasi stok ketika order dikonfirmasi" />
            <p class="text-sm text-gray-500 mt-1">
                Saat diaktifkan, stok akan otomatis disisihkan setelah Sales Order dikonfirmasi.
            </p>
        </div>

        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">Baris Sales Order</h3>
                <AppSecondaryButton type="button" @click="addLine">
                    Tambah Baris
                </AppSecondaryButton>
            </div>
            <p class="text-sm text-gray-500">Isi detail produk, kuantitas, harga, pajak, dan lokasi pengiriman.</p>
        </div>

        <div class="overflow-auto border border-gray-200 rounded">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Produk / Varian</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Satuan</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Qty</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Harga Satuan</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Pajak (%)</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Tgl Kirim</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Lokasi & Reservasi</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Subtotal</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="(line, index) in form.lines" :key="index">
                        <td class="px-4 py-3 min-w-[220px]">
                            <AppSelect
                                v-model="line.product_variant_id"
                                :options="variantOptions"
                                placeholder="Pilih produk"
                                required
                                @update:modelValue="() => syncVariant(index)"
                            />
                            <AppInput
                                v-model="line.description"
                                placeholder="Deskripsi baris"
                                class="mt-2"
                            />
                            <p class="text-xs text-gray-500 mt-1">
                                {{ availabilityLabel(index) }}
                            </p>
                        </td>
                        <td class="px-4 py-3 min-w-[140px]">
                            <AppSelect
                                v-model="line.uom_id"
                                :options="lineUomOptions(line).map((uom) => ({ value: uom.id, label: `${uom.code} — ${uom.name}` }))"
                                placeholder="Satuan"
                                required
                            />
                        </td>
                        <td class="px-4 py-3 min-w-[120px]">
                            <AppInput
                                v-model="line.quantity"
                                type="number"
                                min="0.0001"
                                step="0.0001"
                                required
                            />
                        </td>
                        <td class="px-4 py-3 min-w-[140px]">
                            <AppInput
                                v-model="line.unit_price"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Kosongkan untuk otomatis"
                            />
                            <AppHint text="Biarkan kosong untuk menggunakan harga dari daftar harga." />
                        </td>
                        <td class="px-4 py-3 min-w-[120px]">
                            <AppInput
                                v-model="line.tax_rate"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0"
                            />
                        </td>
                        <td class="px-4 py-3 min-w-[150px]">
                            <AppInput
                                v-model="line.requested_delivery_date"
                                type="date"
                            />
                        </td>
                        <td class="px-4 py-3 min-w-[180px] space-y-2">
                            <AppSelect
                                v-model="line.reservation_location_id"
                                :options="filteredLocations.map((location) => ({ value: location.id, label: `${location.code} — ${location.name}` }))"
                                placeholder="Pilih lokasi"
                                :required="form.reserve_stock"
                            />
                            <span v-if="form.reserve_stock" class="text-xs text-gray-500">
                                Lokasi wajib diisi saat reservasi stok aktif.
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap min-w-[140px]">
                            <div>Subtotal: {{ formatNumber((Number(line.quantity) || 0) * (Number(line.unit_price) || 0)) }}</div>
                            <div>Pajak: {{ formatNumber((Number(line.quantity) || 0) * (Number(line.unit_price) || 0) * ((Number(line.tax_rate) || 0) / 100)) }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <AppDangerButton
                                type="button"
                                @click="removeLine(index)"
                                :disabled="form.lines.length === 1"
                            >
                                Hapus
                            </AppDangerButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <AppTextarea
                v-model="form.notes"
                label="Catatan"
                rows="5"
                placeholder="Instruksi tambahan untuk tim fulfillment atau pelanggan."
            />

            <div class="border border-gray-200 rounded p-4 space-y-2 bg-gray-50">
                <div class="flex items-center justify-between text-sm">
                    <span>Subtotal</span>
                    <span>{{ formatNumber(totals.subtotal) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span>Total Pajak</span>
                    <span>{{ formatNumber(totals.tax) }}</span>
                </div>
                <div class="flex items-center justify-between text-base font-semibold">
                    <span>Grand Total</span>
                    <span>{{ formatNumber(grandTotal) }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <AppSecondaryButton type="button" @click="$inertia.visit(route('sales-orders.index'))">
                Batal
            </AppSecondaryButton>
            <AppPrimaryButton type="submit" :disabled="form.processing">
                {{ submitLabel }}
            </AppPrimaryButton>
        </div>
    </form>
</template>

