<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import UomConversionRuleForm from './Partials/UomConversionRuleForm.vue';

defineProps({
    uoms: Array,
    companies: Array,
    methods: Array,
    contexts: Array,
    roundingModes: Array,
    filters: Object,
});
</script>

<template>
    <Head title="Tambah Aturan Konversi Satuan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Tambah Aturan Konversi Satuan</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('uom-conversion-rules.index', filters)" text="Kembali ke Daftar Aturan" />
                    </div>
                    <UomConversionRuleForm
                        :uoms="uoms"
                        :companies="companies"
                        :methods="methods"
                        :contexts="contexts"
                        :roundingModes="roundingModes"
                        :filters="filters"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
