<script setup>
import { computed, watch } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import AppHint from '@/Components/AppHint.vue';
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
const formOptions = props.formOptions;

const supplierTableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama Supplier' },
    { key: 'actions', label: '' }
];

const supplierSearchUrl = computed(() => route('api.partners', {
    company_id: form.company_id || undefined,
    roles: ['supplier'],
}));

const selectedSupplierLabel = computed(() => {
    const supplier = formOptions.suppliers.find((candidate) => candidate.id === form.partner_id);
    if (!supplier) {
        return '';
    }

    return `${supplier.code} — ${supplier.name}`;
});

const filteredBranches = computed(() => {
    if (!form.company_id) {
        return formOptions.branches;
    }

    return formOptions.branches.filter((branch) => branch.company_id === form.company_id);
});

const filteredSuppliers = computed(() => {
    if (!form.company_id) {
        return formOptions.suppliers;
    }

    return formOptions.suppliers.filter((supplier) =>
        Array.isArray(supplier.company_ids) && supplier.company_ids.includes(form.company_id)
    );
});

const filteredProducts = computed(() => {
    if (!form.company_id) {
        return formOptions.products;
    }

    return formOptions.products.filter((product) =>
        Array.isArray(product.company_ids) && product.company_ids.includes(form.company_id)
    );
});

const variantLookup = computed(() => {
    const lookup = {};

    formOptions.products.forEach((product) => {
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
            productName: product.name,
            uom: variant.uom,
        }))
    )
);

const uomOptions = computed(() => {
    if (!form.company_id) {
        return formOptions.uoms;
    }

    return formOptions.uoms.filter((uom) => uom.company_id === form.company_id);
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

        const branch = formOptions.branches.find((candidate) => candidate.id === branchId);
        if (branch?.company_id) {
            form.company_id = branch.company_id;
        }
    }
);

watch(
    () => form.company_id,
    () => {
        if (form.branch_id && !filteredBranches.value.some((branch) => branch.id === form.branch_id)) {
            form.branch_id = '';
        }

        if (form.partner_id && !filteredSuppliers.value.some((supplier) => supplier.id === form.partner_id)) {
            form.partner_id = '';
        }

        form.lines.forEach((line) => {
            if (!line.product_variant_id) {
                return;
            }

            if (!variantOptions.value.some((option) => option.value === line.product_variant_id)) {
                resetLine(line);
            }
        });
    }
);

function lineUomOptions(line) {
    const variant = variantLookup.value[line.product_variant_id];
    if (!variant?.uom?.kind) {
        return uomOptions.value;
    }

    return uomOptions.value.filter((uom) => uom.kind === variant.uom.kind);
}

function addLine() {
    form.lines.push(createEmptyLine());
}

function removeLine(index) {
    if (form.lines.length === 1) {
        return;
    }

    form.lines.splice(index, 1);
}

function createEmptyLine() {
    return {
        product_variant_id: '',
        uom_id: '',
        quantity: 1,
        unit_price: 0,
        tax_rate: 0,
        description: '',
        expected_date: '',
    };
}

function resetLine(line) {
    line.product_variant_id = '';
    line.uom_id = '';
    line.quantity = 1;
    line.unit_price = 0;
    line.tax_rate = 0;
    line.description = '';
    line.expected_date = '';
}

function syncVariant(line) {
    const variant = variantLookup.value[line.product_variant_id];

    if (!variant) {
        return;
    }

    line.uom_id = variant.uom?.id || line.uom_id;

    if (!line.description) {
        line.description = variant.product_name;
    }
}

function lineSubtotal(line) {
    const quantity = Number(line.quantity) || 0;
    const price = Number(line.unit_price) || 0;
    return quantity * price;
}

function lineTax(line) {
    const subtotal = lineSubtotal(line);
    const taxRate = Number(line.tax_rate) || 0;
    return subtotal * (taxRate / 100);
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

                <div>
                    <AppPopoverSearch
                        v-model="form.partner_id"
                        label="Supplier"
                        placeholder="Pilih Supplier"
                        hint="Gunakan pencarian untuk menemukan supplier lintas perusahaan."
                        :url="supplierSearchUrl"
                        :tableHeaders="supplierTableHeaders"
                        :displayKeys="['code', 'name']"
                        :initialDisplayValue="selectedSupplierLabel"
                        :disabled="!form.company_id"
                        modalTitle="Pilih Supplier"
                        required
                        :error="form.errors?.partner_id"
                    />
                </div>

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
                    label="Tanggal PO"
                    required
                />

                <AppInput
                    v-model="form.expected_date"
                    type="date"
                    label="Estimasi Kedatangan"
                />

                <AppInput
                    v-model="form.supplier_reference"
                    label="Referensi Supplier"
                    placeholder="Nomor referensi (opsional)"
                />

                <AppInput
                    v-model="form.payment_terms"
                    label="Syarat Pembayaran"
                    placeholder="Net 30, COD, dll"
                />
            </div>
        </div>

        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">Baris Purchase Order</h3>
                <AppSecondaryButton type="button" @click="addLine">
                    Tambah Baris
                </AppSecondaryButton>
            </div>
            <p class="text-sm text-gray-500">Lengkapi detail item termasuk kuantitas, satuan, harga, dan pajak.</p>
        </div>

        <div class="overflow-auto border border-gray-200 rounded">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Produk / Varian</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Satuan</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Qty</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Harga Satuan</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">
                            Pajak (%)
                            <AppHint text="Isi 11 untuk PPN 11%. Kosongkan jika non taxable." />
                        </th>
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Tgl Kedatangan</th>
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
                                @update:modelValue="syncVariant(line)"
                            />
                            <AppInput
                                v-model="line.description"
                                placeholder="Deskripsi baris"
                                class="mt-2"
                            />
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
                                required
                            />
                        </td>
                        <td class="px-4 py-3 min-w-[120px]">
                            <AppInput
                                v-model="line.tax_rate"
                                hint="Isi 11 untuk PPN 11%. Kosongkan jika non taxable."
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0"
                            />
                        </td>
                        <td class="px-4 py-3 min-w-[150px]">
                            <AppInput
                                v-model="line.expected_date"
                                type="date"
                            />
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap min-w-[140px]">
                            <div>Subtotal: {{ formatNumber(lineSubtotal(line)) }}</div>
                            <div>Pajak: {{ formatNumber(lineTax(line)) }}</div>
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
                placeholder="Instruksi tambahan yang ingin disampaikan ke supplier."
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
            <AppSecondaryButton type="button" @click="$inertia.visit(route('purchase-orders.index'))">
                Batal
            </AppSecondaryButton>
            <AppPrimaryButton type="submit" :disabled="form.processing">
                {{ submitLabel }}
            </AppPrimaryButton>
        </div>
    </form>
</template>

