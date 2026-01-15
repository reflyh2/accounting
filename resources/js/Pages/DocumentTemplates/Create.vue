<script setup>
import { ref, computed } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    companies: Array,
    documentTypes: Array,
    pageSizes: Array,
    pageOrientations: Array,
    defaultDocumentType: String,
    defaultCompanyId: [Number, String],
});

const form = useForm({
    company_id: props.defaultCompanyId || null,
    document_type: props.defaultDocumentType || 'sales_order',
    name: '',
    content: getDefaultTemplate('sales_order'),
    css_styles: getDefaultStyles(),
    is_default: false,
    is_active: true,
    page_size: 'A4',
    page_orientation: 'portrait',
});

const placeholderCategories = ref([
    {
        name: 'Dokumen',
        expanded: true,
        items: [
            { key: 'document_number', label: 'Nomor Dokumen' },
            { key: 'document_date', label: 'Tanggal (Default)' },
            { key: 'document_date_long', label: 'Tanggal (06 October 2025)' },
            { key: 'document_date_short', label: 'Tanggal (dd/mm/yyyy)' },
            { key: 'due_date', label: 'Jatuh Tempo' },
            { key: 'due_date_long', label: 'Jatuh Tempo (Long)' },
            { key: 'due_date_short', label: 'Jatuh Tempo (Short)' },
            { key: 'subtotal', label: 'Subtotal' },
            { key: 'tax_total', label: 'Total Pajak' },
            { key: 'total_amount', label: 'Grand Total' },
            { key: 'total_terbilang_id', label: 'Terbilang (ID)' },
            { key: 'total_terbilang_en', label: 'Terbilang (EN)' },
            { key: 'notes', label: 'Catatan' },
        ],
    },
    {
        name: 'Perusahaan',
        expanded: false,
        items: [
            { key: 'company.logo_url', label: 'Logo (URL/Base64)' },
            { key: 'company.name', label: 'Nama Perusahaan' },
            { key: 'company.legal_name', label: 'Nama Legal' },
            { key: 'company.tax_id', label: 'NPWP' },
            { key: 'company.address', label: 'Alamat' },
            { key: 'company.city', label: 'Kota' },
            { key: 'company.province', label: 'Provinsi' },
            { key: 'company.postal_code', label: 'Kode Pos' },
            { key: 'company.phone', label: 'Telepon' },
            { key: 'company.email', label: 'Email' },
            { key: 'company.website', label: 'Website' },
        ],
    },
    {
        name: 'Bank Perusahaan',
        expanded: false,
        items: [
            { key: 'bank.bank_name', label: 'Nama Bank' },
            { key: 'bank.account_number', label: 'No. Rekening' },
            { key: 'bank.account_holder_name', label: 'Atas Nama' },
            { key: 'bank.branch_name', label: 'Cabang Bank' },
            { key: 'bank.swift_code', label: 'SWIFT Code' },
        ],
    },
    {
        name: 'Cabang',
        expanded: false,
        items: [
            { key: 'branch.name', label: 'Nama Cabang' },
            { key: 'branch.address', label: 'Alamat Cabang' },
        ],
    },
    {
        name: 'Partner/Customer',
        expanded: false,
        items: [
            { key: 'partner.name', label: 'Nama' },
            { key: 'partner.code', label: 'Kode' },
            { key: 'partner.phone', label: 'Telepon' },
            { key: 'partner.email', label: 'Email' },
            { key: 'partner.address', label: 'Alamat' },
            { key: 'partner.city', label: 'Kota' },
            { key: 'partner.region', label: 'Provinsi/Wilayah' },
            { key: 'partner.postal_code', label: 'Kode Pos' },
            { key: 'partner.country', label: 'Negara' },
            { key: 'partner.tax_id', label: 'NPWP' },
            { key: 'partner.registration_number', label: 'No. Registrasi' },
        ],
    },
    {
        name: 'Loop Lines',
        expanded: false,
        items: [
            { key: '#lines', label: 'Mulai Loop' },
            { key: '/lines', label: 'Akhir Loop' },
            { key: 'index', label: 'Nomor Baris' },
            { key: 'product_name', label: 'Nama Produk' },
            { key: 'variant_sku', label: 'SKU Varian' },
            { key: 'description', label: 'Deskripsi' },
            { key: 'quantity', label: 'Qty' },
            { key: 'uom_code', label: 'Satuan' },
            { key: 'unit_price', label: 'Harga Satuan' },
            { key: 'discount_rate', label: 'Diskon (%)' },
            { key: 'tax_rate', label: 'Pajak (%)' },
            { key: 'tax_amount', label: 'Nilai Pajak' },
            { key: 'line_total', label: 'Total' },
        ],
    },
]);

function getDefaultTemplate(type) {
    return `<div class="document-header">
    <h1>{{document_number}}</h1>
    <p>Tanggal: {{document_date}}</p>
</div>

<div class="company-info">
    <img src="{{company.logo_url}}" alt="Logo" class="company-logo" />
    <h2>{{company.name}}</h2>
    <p>{{company.address}}</p>
    <p>Telp: {{company.phone}}</p>
</div>

<div class="partner-info">
    <strong>Kepada:</strong>
    <p>{{partner.name}}</p>
    <p>{{partner.address}}</p>
</div>

<table class="items-table">
    <thead>
        <tr>
            <th>No</th>
            <th>Deskripsi</th>
            <th>Qty</th>
            <th>Satuan</th>
            <th>Harga</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        {{#lines}}
        <tr>
            <td>{{index}}</td>
            <td>{{description}}</td>
            <td>{{quantity}}</td>
            <td>{{uom_code}}</td>
            <td>{{unit_price}}</td>
            <td>{{line_total}}</td>
        </tr>
        {{/lines}}
    </tbody>
</table>

<div class="totals">
    <p>Subtotal: {{subtotal}}</p>
    <p>Pajak: {{tax_total}}</p>
    <p><strong>Total: {{total_amount}}</strong></p>
</div>

<div class="notes">
    <p>{{notes}}</p>
</div>`;
}

function getDefaultStyles() {
    return `body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 12pt;
    line-height: 1.4;
    color: #333;
}

.document-header {
    text-align: center;
    margin-bottom: 20px;
}

.document-header h1 {
    font-size: 18pt;
    margin: 0;
}

.company-info {
    margin-bottom: 20px;
}

.company-logo {
    max-width: 150px;
    max-height: 80px;
    margin-bottom: 10px;
}

.company-info h2 {
    font-size: 14pt;
    margin: 0 0 5px 0;
}

.partner-info {
    margin-bottom: 20px;
    padding: 10px;
    background: #f5f5f5;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.items-table th,
.items-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

.items-table th {
    background: #f0f0f0;
    font-weight: 600;
}

.items-table td:nth-child(3),
.items-table td:nth-child(5),
.items-table td:nth-child(6) {
    text-align: right;
}

.totals {
    text-align: right;
    margin-bottom: 20px;
}

.notes {
    font-size: 10pt;
    color: #666;
    border-top: 1px solid #ddd;
    padding-top: 10px;
}

@page {
    size: A4;
    margin: 15mm;
}

@media print {
    body { margin: 0; }
}`;
}

function insertPlaceholder(key) {
    const placeholder = '{{' + key + '}}';
    form.content = form.content + placeholder;
}

function toggleCategory(cat) {
    cat.expanded = !cat.expanded;
}

function formatPlaceholderKey(key) {
    return '{{' + key + '}}';
}

const previewHtml = computed(() => {
    let html = form.content;
    html = html.replace(/\{\{([^}]+)\}\}/g, '<span class="placeholder-marker">$1</span>');
    return `<style>${form.css_styles} .placeholder-marker { background: #fef3c7; padding: 1px 4px; border-radius: 2px; font-size: 10px; }</style>${html}`;
});

function submit() {
    form.post(route('document-templates.store'));
}
</script>

<template>
    <Head title="Buat Template Dokumen" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2>Buat Template Dokumen</h2>
                <Link :href="route('document-templates.index')" class="text-sm text-gray-500 hover:text-gray-700">
                    &larr; Kembali
                </Link>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Basic Info -->
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <h3 class="text-lg font-medium mb-4">Informasi Dasar</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <AppSelect
                        v-model="form.document_type"
                        label="Tipe Dokumen"
                        :options="documentTypes"
                        :error="form.errors.document_type"
                        required
                    />
                    <AppSelect
                        v-model="form.company_id"
                        label="Perusahaan"
                        :options="companies.map(c => ({ value: c.id, label: c.name }))"
                        :error="form.errors.company_id"
                        placeholder="(Default Global)"
                    />
                    <AppInput
                        v-model="form.name"
                        label="Nama Template"
                        :error="form.errors.name"
                        required
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                    <AppSelect
                        v-model="form.page_size"
                        label="Ukuran Halaman"
                        :options="pageSizes"
                    />
                    <AppSelect
                        v-model="form.page_orientation"
                        label="Orientasi"
                        :options="pageOrientations"
                    />
                    <div class="flex items-center space-x-4 mt-6">
                        <label class="inline-flex items-center">
                            <input type="checkbox" v-model="form.is_default" class="form-checkbox">
                            <span class="ml-2 text-sm">Default</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" v-model="form.is_active" class="form-checkbox">
                            <span class="ml-2 text-sm">Aktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Template Editor -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Placeholder Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4">
                        <h3 class="text-lg font-medium mb-4">Placeholder</h3>
                        <div class="space-y-2 max-h-[600px] overflow-y-auto">
                            <div v-for="cat in placeholderCategories" :key="cat.name" class="border-b pb-2">
                                <button
                                    type="button"
                                    @click="toggleCategory(cat)"
                                    class="w-full flex justify-between items-center text-left py-1 text-sm font-medium"
                                >
                                    {{ cat.name }}
                                    <span>{{ cat.expanded ? '−' : '+' }}</span>
                                </button>
                                <div v-if="cat.expanded" class="pl-2 space-y-1 mt-1">
                                    <button
                                        v-for="item in cat.items"
                                        :key="item.key"
                                        type="button"
                                        @click="insertPlaceholder(item.key)"
                                        class="block w-full text-left text-xs py-1 px-2 hover:bg-gray-100 rounded"
                                    >
                                        {{ item.label }}
                                        <span class="text-gray-400">{{ formatPlaceholderKey(item.key) }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- HTML/CSS Editor -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4">
                        <h3 class="text-lg font-medium mb-4">Template HTML</h3>
                        <textarea
                            v-model="form.content"
                            class="w-full h-96 font-mono text-sm border rounded p-2"
                            placeholder="Masukkan template HTML..."
                        ></textarea>
                        <p v-if="form.errors.content" class="text-red-500 text-sm mt-1">{{ form.errors.content }}</p>
                    </div>

                    <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4 mt-4">
                        <h3 class="text-lg font-medium mb-4">CSS Styles</h3>
                        <textarea
                            v-model="form.css_styles"
                            class="w-full h-48 font-mono text-sm border rounded p-2"
                            placeholder="Masukkan CSS..."
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Preview (Full Width) -->
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4">
                <h3 class="text-lg font-medium mb-4">Preview</h3>
                <div class="border rounded p-4 bg-white min-h-[400px] max-h-[800px] overflow-y-auto">
                    <div v-html="previewHtml"></div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-end space-x-3">
                <Link :href="route('document-templates.index')">
                    <AppSecondaryButton type="button">Batal</AppSecondaryButton>
                </Link>
                <AppPrimaryButton type="submit" :disabled="form.processing">
                    Simpan Template
                </AppPrimaryButton>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
