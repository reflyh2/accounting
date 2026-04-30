<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import UomConversionForm from './Partials/UomConversionForm.vue';

defineProps({
    conversion: Object,
    uoms: Array,
    filters: Object,
});
</script>

<template>
    <Head title="Ubah Konversi Satuan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Konversi Satuan</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('uom-conversions.index', filters)" text="Kembali ke Daftar Konversi" />
                    </div>
                    <UomConversionForm :conversion="conversion" :uoms="uoms" :filters="filters" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
