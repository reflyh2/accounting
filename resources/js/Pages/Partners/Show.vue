<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';
import { getStatusClass } from '@/constants/businessRelationStatus';

const props = defineProps({
    partner: Object,
    statuses: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deletePartner = () => {
    form.delete(route('partners.destroy', props.partner.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Partner" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Partner</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('partners.index', filters)" text="Kembali ke Daftar Partner" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold">{{ partner.name }}</h3>
                                <span :class="`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusClass(partner.status)}`">
                                    {{ statuses[partner.status] }}
                                </span>
                            </div>
                            <div class="flex items-center">
                                <Link :href="route('partners.edit', partner.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div class="grid grid-cols-2 gap-6 mb-6">
                            <div>
                                <h4 class="font-semibold mb-2">Informasi Kontak</h4>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Email:</span> {{ partner.email || 'N/A' }}</p>
                                    <p><span class="font-medium">Telepon:</span> {{ partner.phone || 'N/A' }}</p>
                                    <p><span class="font-medium">Alamat:</span> {{ partner.address || 'N/A' }}</p>
                                    <p><span class="font-medium">Website:</span> {{ partner.website || 'N/A' }}</p>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-2">Informasi Bisnis</h4>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Perusahaan:</span> 
                                        {{ partner.companies.map(company => company.name).join(', ') }}
                                    </p>
                                    <p><span class="font-medium">NPWP:</span> {{ partner.tax_id || 'N/A' }}</p>
                                    <p><span class="font-medium">Nomor Pendaftaran:</span> {{ partner.registration_number || 'N/A' }}</p>
                                    <p><span class="font-medium">Industri:</span> {{ partner.industry || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Credit Terms -->
                        <div class="mb-6" v-if="partner.credit_terms">
                            <h4 class="font-semibold mb-2">Term Kredit</h4>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <p><span class="font-medium">Limit Kredit:</span> {{ partner.credit_terms.credit_limit }}</p>
                                    <p><span class="font-medium">Kredit Digunakan:</span> {{ partner.credit_terms.used_credit }}</p>
                                </div>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Term Pembayaran:</span> {{ partner.credit_terms.payment_term_type }} ({{ partner.credit_terms.payment_term_days }} hari)</p>
                                    <p><span class="font-medium">Catatan:</span> {{ partner.credit_terms.notes || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="mb-6" v-if="partner.tags && partner.tags.length">
                            <h4 class="font-semibold mb-2">Tag</h4>
                            <div class="flex flex-wrap gap-2">
                                <span 
                                    v-for="tag in partner.tags" 
                                    :key="tag.id"
                                    class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"
                                >
                                    {{ tag.tag_name }}
                                </span>
                            </div>
                        </div>

                        <!-- Custom Fields -->
                        <div v-if="partner.custom_fields && partner.custom_fields.length">
                            <h4 class="font-semibold mb-2">Data Tambahan</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div 
                                    v-for="field in partner.custom_fields" 
                                    :key="field.id"
                                    class="space-y-1"
                                >
                                    <p class="font-medium">{{ field.field_name }}:</p>
                                    <p>{{ field.field_value || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Partner"
            @close="showDeleteConfirmation = false"
            @confirm="deletePartner"
        />
    </AuthenticatedLayout>
</template> 