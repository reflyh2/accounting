<script setup>
import { Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import { StarIcon, PencilIcon, TrashIcon, DocumentDuplicateIcon, EyeIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    template: Object,
    placeholders: Object,
});

const documentTypeLabels = {
    'sales_order': 'Sales Order',
    'sales_delivery': 'Surat Jalan',
    'sales_invoice': 'Faktur Penjualan',
};

function setAsDefault() {
    router.post(route('document-templates.set-default', props.template.id));
}

function duplicate() {
    router.post(route('document-templates.duplicate', props.template.id));
}

function deleteTemplate() {
    if (confirm('Hapus template ini?')) {
        router.delete(route('document-templates.destroy', props.template.id));
    }
}

function formatContentPreview(content, cssStyles) {
    const formattedContent = content.replace(/\{\{([^}]+)\}\}/g, '<span style="background:#fef3c7;padding:1px 4px;border-radius:2px;font-size:10px;">$1</span>');
    if (cssStyles) {
        return `<style>${cssStyles}</style>${formattedContent}`;
    }
    return formattedContent;
}
</script>

<template>
    <Head :title="`Template: ${template.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <h2>{{ template.name }}</h2>
                    <span v-if="template.is_default" class="px-2 py-1 text-xs font-medium text-yellow-700 bg-yellow-100 rounded-full flex items-center">
                        <StarIcon class="w-3 h-3 mr-1" />
                        Default
                    </span>
                    <span v-if="template.is_active" class="px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full">Aktif</span>
                    <span v-else class="px-2 py-1 text-xs font-medium text-gray-500 bg-gray-100 rounded-full">Nonaktif</span>
                </div>
                <Link :href="route('document-templates.index')" class="text-sm text-gray-500 hover:text-gray-700">
                    &larr; Kembali
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Actions -->
            <div class="flex justify-end space-x-3">
                <Link :href="route('document-templates.preview', template.id)">
                    <AppSecondaryButton>
                        <EyeIcon class="w-4 h-4 mr-1" />
                        Preview
                    </AppSecondaryButton>
                </Link>
                <AppSecondaryButton v-if="!template.is_default" @click="setAsDefault">
                    <StarIcon class="w-4 h-4 mr-1" />
                    Jadikan Default
                </AppSecondaryButton>
                <AppSecondaryButton @click="duplicate">
                    <DocumentDuplicateIcon class="w-4 h-4 mr-1" />
                    Duplikasi
                </AppSecondaryButton>
                <Link :href="route('document-templates.edit', template.id)">
                    <AppPrimaryButton>
                        <PencilIcon class="w-4 h-4 mr-1" />
                        Edit
                    </AppPrimaryButton>
                </Link>
                <AppDangerButton v-if="!template.is_default" @click="deleteTemplate">
                    <TrashIcon class="w-4 h-4 mr-1" />
                    Hapus
                </AppDangerButton>
            </div>

            <!-- Info -->
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <h3 class="text-lg font-medium mb-4">Informasi Template</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Tipe Dokumen</p>
                        <p class="font-medium">{{ documentTypeLabels[template.document_type] || template.document_type }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Perusahaan</p>
                        <p class="font-medium">{{ template.company?.name || '(Default Global)' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Ukuran Halaman</p>
                        <p class="font-medium">{{ template.page_size }} ({{ template.page_orientation }})</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Terakhir Diubah</p>
                        <p class="font-medium">{{ template.updated_by?.name }} {{ new Date(template.updated_at).toLocaleDateString('id-ID') }}</p>
                    </div>
                </div>
            </div>

            <!-- Template Preview -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                    <h3 class="text-lg font-medium mb-4">Template HTML</h3>
                    <pre class="bg-gray-50 p-4 rounded text-xs font-mono overflow-auto max-h-96 whitespace-pre-wrap">{{ template.content }}</pre>
                </div>

                <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                    <h3 class="text-lg font-medium mb-4">Rendered Preview</h3>
                    <div class="border rounded p-4 bg-white max-h-96 overflow-auto">
                        <div v-html="formatContentPreview(template.content, template.css_styles)"></div>
                    </div>
                </div>
            </div>

            <!-- CSS Styles -->
            <div v-if="template.css_styles" class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <h3 class="text-lg font-medium mb-4">CSS Styles</h3>
                <pre class="bg-gray-50 p-4 rounded text-xs font-mono overflow-auto max-h-64 whitespace-pre-wrap">{{ template.css_styles }}</pre>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
