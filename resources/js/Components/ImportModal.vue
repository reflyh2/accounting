<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppModal from '@/Components/AppModal.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { ArrowUpTrayIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Import Data',
    },
    description: {
        type: String,
        default: '',
    },
    importRoute: {
        type: String,
        required: true,
    },
    templateRoute: {
        type: String,
        default: null,
    },
    /**
     * Optional single extra input sent alongside the file (e.g. a transaction date).
     * Shape: { name: string, label: string, type?: 'date'|'text', default?: string, required?: boolean }
     */
    dateField: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'imported']);

const file = ref(null);
const processing = ref(false);
const errors = ref({});
const fileError = ref('');
const dateValue = ref(props.dateField?.default ?? new Date().toISOString().split('T')[0]);

const rowErrors = computed(() =>
    Object.keys(errors.value)
        .filter((key) => key.startsWith('rows.'))
        .sort((a, b) => Number(a.slice(5)) - Number(b.slice(5)))
        .map((key) => errors.value[key])
);

watch(() => props.show, (shown) => {
    if (shown) {
        file.value = null;
        errors.value = {};
        fileError.value = '';
        dateValue.value = props.dateField?.default ?? new Date().toISOString().split('T')[0];
    }
});

function onFileChange(event) {
    file.value = event.target.files?.[0] ?? null;
    fileError.value = '';
}

function downloadTemplate() {
    if (!props.templateRoute) return;
    window.open(route(props.templateRoute), '_blank');
}

function submit() {
    if (!file.value) {
        fileError.value = 'Silakan pilih berkas terlebih dahulu.';
        return;
    }

    processing.value = true;
    errors.value = {};

    const payload = { file: file.value };
    if (props.dateField) {
        payload[props.dateField.name] = dateValue.value;
    }

    router.post(
        route(props.importRoute),
        payload,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                processing.value = false;
                emit('imported');
                emit('close');
            },
            onError: (formErrors) => {
                processing.value = false;
                errors.value = formErrors;
            },
            onFinish: () => {
                processing.value = false;
            },
        }
    );
}

function close() {
    if (processing.value) return;
    emit('close');
}
</script>

<template>
    <AppModal :show="show" max-width="lg" :closeable="!processing" @close="close">
        <template #title>{{ title }}</template>

        <template #content>
            <p v-if="description" class="text-sm text-gray-600 mb-4">{{ description }}</p>

            <div v-if="templateRoute" class="mb-4">
                <button
                    type="button"
                    class="inline-flex items-center text-sm text-main-700 hover:underline"
                    @click="downloadTemplate"
                >
                    <DocumentArrowDownIcon class="w-4 h-4 mr-1" />
                    Unduh template
                </button>
            </div>

            <div v-if="dateField" class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ dateField.label }}
                </label>
                <input
                    v-model="dateValue"
                    type="date"
                    class="block w-full text-sm border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-main-500"
                    :disabled="processing"
                />
                <InputError :message="errors[dateField.name]" class="mt-1" />
            </div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Berkas (CSV / XLSX)
            </label>
            <input
                type="file"
                accept=".csv,.xlsx,.xls"
                class="block w-full text-sm text-gray-900 border border-gray-300 rounded cursor-pointer bg-gray-50 focus:outline-none file:mr-4 file:py-2 file:px-4 file:border-0 file:bg-gray-100 file:text-sm file:text-gray-700 hover:file:bg-gray-200"
                :disabled="processing"
                @change="onFileChange"
            />
            <InputError :message="fileError || errors.file" class="mt-2" />
            <InputError v-if="errors.message" :message="errors.message" class="mt-2" />

            <div v-if="rowErrors.length" class="mt-4">
                <p class="text-sm font-medium text-red-700 mb-2">Rincian kesalahan:</p>
                <ul class="text-xs text-red-600 space-y-1 max-h-48 overflow-y-auto pl-4 list-disc">
                    <li v-for="(msg, idx) in rowErrors" :key="idx">{{ msg }}</li>
                </ul>
            </div>
        </template>

        <template #footer>
            <div class="flex items-center justify-end gap-2">
                <AppSecondaryButton :disabled="processing" @click="close">
                    Batal
                </AppSecondaryButton>
                <AppPrimaryButton :disabled="processing || !file" @click="submit">
                    <ArrowUpTrayIcon class="w-4 h-4 mr-1" />
                    {{ processing ? 'Mengimpor…' : 'Impor' }}
                </AppPrimaryButton>
            </div>
        </template>
    </AppModal>
</template>
