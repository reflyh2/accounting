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
    employee: Object,
    statuses: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteEmployee = () => {
    form.delete(route('employees.destroy', props.employee.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Karyawan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Karyawan</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('employees.index', filters)" text="Kembali ke Daftar Karyawan" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold">{{ employee.name }}</h3>
                                <span :class="`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusClass(employee.status)}`">
                                    {{ statuses[employee.status] }}
                                </span>
                            </div>
                            <div class="flex items-center">
                                <Link :href="route('employees.edit', employee.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Hapus" />
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div class="grid grid-cols-2 gap-6 mb-6">
                            <div>
                                <h4 class="font-semibold mb-2">Informasi Kontak</h4>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Email:</span> {{ employee.email || 'N/A' }}</p>
                                    <p><span class="font-medium">Telepon:</span> {{ employee.phone || 'N/A' }}</p>
                                    <p><span class="font-medium">Alamat:</span> {{ employee.address || 'N/A' }}</p>
                                    <p><span class="font-medium">Website:</span> {{ employee.website || 'N/A' }}</p>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-2">Informasi Kepegawaian</h4>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Perusahaan:</span> 
                                        {{ employee.companies.map(company => company.name).join(', ') }}
                                    </p>
                                    <p><span class="font-medium">ID Karyawan:</span> {{ employee.registration_number || 'N/A' }}</p>
                                    <p><span class="font-medium">Departemen:</span> {{ employee.industry || 'N/A' }}</p>
                                    <p><span class="font-medium">NPWP:</span> {{ employee.tax_id || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Salary Information -->
                        <div class="mb-6" v-if="employee.credit_terms">
                            <h4 class="font-semibold mb-2">Informasi Gaji</h4>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <p><span class="font-medium">Gaji Pokok:</span> {{ employee.credit_terms.credit_limit }}</p>
                                    <p><span class="font-medium">Uang Muka:</span> {{ employee.credit_terms.used_credit }}</p>
                                </div>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Term Pembayaran:</span> {{ employee.credit_terms.payment_term_type }} ({{ employee.credit_terms.payment_term_days }} hari)</p>
                                    <p><span class="font-medium">Catatan:</span> {{ employee.credit_terms.notes || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="mb-6" v-if="employee.tags && employee.tags.length">
                            <h4 class="font-semibold mb-2">Tag</h4>
                            <div class="flex flex-wrap gap-2">
                                <span 
                                    v-for="tag in employee.tags" 
                                    :key="tag.id"
                                    class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"
                                >
                                    {{ tag.tag_name }}
                                </span>
                            </div>
                        </div>

                        <!-- Custom Fields -->
                        <div v-if="employee.custom_fields && employee.custom_fields.length">
                            <h4 class="font-semibold mb-2">Data Tambahan</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div 
                                    v-for="field in employee.custom_fields" 
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
            title="Hapus Karyawan"
            @close="showDeleteConfirmation = false"
            @confirm="deleteEmployee"
        />
    </AuthenticatedLayout>
</template> 