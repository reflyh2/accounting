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
    template: Object,
    companies: Array,
    documentTypes: Array,
    pageSizes: Array,
    pageOrientations: Array,
    placeholders: Object,
});

const form = useForm({
    company_id: props.template.company_id,
    document_type: props.template.document_type,
    name: props.template.name,
    content: props.template.content,
    css_styles: props.template.css_styles || '',
    is_default: props.template.is_default,
    is_active: props.template.is_active,
    page_size: props.template.page_size,
    page_orientation: props.template.page_orientation,
});

const placeholderCategories = ref([
    {
        name: 'Dokumen',
        expanded: true,
        items: Object.entries(props.placeholders?.document || {}).map(([key, label]) => ({ key, label })),
    },
    {
        name: 'Perusahaan',
        expanded: false,
        items: Object.entries(props.placeholders?.common?.company || {}).map(([key, label]) => ({ key: `company.${key}`, label })),
    },
    {
        name: 'Bank Perusahaan',
        expanded: false,
        items: Object.entries(props.placeholders?.common?.bank || {}).map(([key, label]) => ({ key: `bank.${key}`, label })),
    },
    {
        name: 'Cabang',
        expanded: false,
        items: Object.entries(props.placeholders?.common?.branch || {}).map(([key, label]) => ({ key: `branch.${key}`, label })),
    },
    {
        name: 'Partner/Customer',
        expanded: false,
        items: Object.entries(props.placeholders?.common?.partner || {}).map(([key, label]) => ({ key: `partner.${key}`, label })),
    },
    {
        name: 'Loop Lines',
        expanded: false,
        items: [
            { key: '#lines', label: 'Mulai Loop' },
            { key: '/lines', label: 'Akhir Loop' },
            ...Object.entries(props.placeholders?.lines || {}).map(([key, label]) => ({ key, label })),
        ],
    },
]);

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
    form.put(route('document-templates.update', props.template.id));
}
</script>

<template>
    <Head :title="`Edit Template: ${template.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2>Edit Template: {{ template.name }}</h2>
                <Link :href="route('document-templates.show', template.id)" class="text-sm text-gray-500 hover:text-gray-700">
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
                                        <span class="text-gray-400 ml-1">{{ formatPlaceholderKey(item.key) }}</span>
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
                <Link :href="route('document-templates.show', template.id)">
                    <AppSecondaryButton type="button">Batal</AppSecondaryButton>
                </Link>
                <AppPrimaryButton type="submit" :disabled="form.processing">
                    Simpan Perubahan
                </AppPrimaryButton>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
