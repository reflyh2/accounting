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
    customer: Object,
    statuses: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteCustomer = () => {
    form.delete(route('customers.destroy', props.customer.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Pelanggan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pelanggan</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('customers.index', filters)" text="Kembali ke Daftar Pelanggan" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold">{{ customer.name }}</h3>
                                <span :class="`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusClass(customer.status)}`">
                                    {{ statuses[customer.status] }}
                                </span>
                            </div>
                            <div class="flex items-center">
                                <Link :href="route('customers.edit', customer.id)">
                                    <AppEditButton title="Ubah" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Hapus" />
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div class="grid grid-cols-2 gap-6 mb-6">
                            <div>
                                <h4 class="font-semibold mb-2">Informasi Kontak</h4>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Email:</span> {{ customer.email || 'N/A' }}</p>
                                    <p><span class="font-medium">Telepon:</span> {{ customer.phone || 'N/A' }}</p>
                                    <p><span class="font-medium">Alamat:</span> {{ customer.address || 'N/A' }}</p>
                                    <p><span class="font-medium">Website:</span> {{ customer.website || 'N/A' }}</p>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-2">Informasi Bisnis</h4>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Perusahaan:</span> 
                                        {{ customer.companies.map(company => company.name).join(', ') }}
                                    </p>
                                    <p><span class="font-medium">NPWP:</span> {{ customer.tax_id || 'N/A' }}</p>
                                    <p><span class="font-medium">Nomor Pendaftaran:</span> {{ customer.registration_number || 'N/A' }}</p>
                                    <p><span class="font-medium">Industri:</span> {{ customer.industry || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Credit Terms -->
                        <div class="mb-6" v-if="customer.credit_terms">
                            <h4 class="font-semibold mb-2">Term Kredit</h4>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <p><span class="font-medium">Limit Kredit:</span> {{ customer.credit_terms.credit_limit }}</p>
                                    <p><span class="font-medium">Kredit Digunakan:</span> {{ customer.credit_terms.used_credit }}</p>
                                </div>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Term Pembayaran:</span> {{ customer.credit_terms.payment_term_type }} ({{ customer.credit_terms.payment_term_days }} hari)</p>
                                    <p><span class="font-medium">Catatan:</span> {{ customer.credit_terms.notes || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="mb-6" v-if="customer.tags && customer.tags.length">
                            <h4 class="font-semibold mb-2">Tag</h4>
                            <div class="flex flex-wrap gap-2">
                                <span 
                                    v-for="tag in customer.tags" 
                                    :key="tag.id"
                                    class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"
                                >
                                    {{ tag.tag_name }}
                                </span>
                            </div>
                        </div>

                        <!-- Custom Fields -->
                        <div v-if="customer.custom_fields && customer.custom_fields.length">
                            <h4 class="font-semibold mb-2">Data Tambahan</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div 
                                    v-for="field in customer.custom_fields" 
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
            title="Delete Customer"
            @close="showDeleteConfirmation = false"
            @confirm="deleteCustomer"
        />
    </AuthenticatedLayout>
</template> 