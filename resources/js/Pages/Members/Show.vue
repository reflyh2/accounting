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
    member: Object,
    statuses: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteMember = () => {
    form.delete(route('members.destroy', props.member.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Anggota" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Anggota</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('members.index', filters)" text="Kembali ke Daftar Anggota" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold">{{ member.name }}</h3>
                                <span :class="`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusClass(member.status)}`">
                                    {{ statuses[member.status] }}
                                </span>
                            </div>
                            <div class="flex items-center">
                                <Link :href="route('members.edit', member.id)">
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
                                    <p><span class="font-medium">Email:</span> {{ member.email || 'N/A' }}</p>
                                    <p><span class="font-medium">Telepon:</span> {{ member.phone || 'N/A' }}</p>
                                    <p><span class="font-medium">Alamat:</span> {{ member.address || 'N/A' }}</p>
                                    <p><span class="font-medium">Website:</span> {{ member.website || 'N/A' }}</p>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-2">Informasi Bisnis</h4>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Perusahaan:</span> 
                                        {{ member.companies.map(company => company.name).join(', ') }}
                                    </p>
                                    <p><span class="font-medium">NPWP:</span> {{ member.tax_id || 'N/A' }}</p>
                                    <p><span class="font-medium">Nomor Pendaftaran:</span> {{ member.registration_number || 'N/A' }}</p>
                                    <p><span class="font-medium">Industri:</span> {{ member.industry || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Credit Terms -->
                        <div class="mb-6" v-if="member.credit_terms">
                            <h4 class="font-semibold mb-2">Term Kredit</h4>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <p><span class="font-medium">Limit Kredit:</span> {{ member.credit_terms.credit_limit }}</p>
                                    <p><span class="font-medium">Kredit Digunakan:</span> {{ member.credit_terms.used_credit }}</p>
                                </div>
                                <div class="space-y-2">
                                    <p><span class="font-medium">Term Pembayaran:</span> {{ member.credit_terms.payment_term_type }} ({{ member.credit_terms.payment_term_days }} hari)</p>
                                    <p><span class="font-medium">Catatan:</span> {{ member.credit_terms.notes || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="mb-6" v-if="member.tags && member.tags.length">
                            <h4 class="font-semibold mb-2">Tag</h4>
                            <div class="flex flex-wrap gap-2">
                                <span 
                                    v-for="tag in member.tags" 
                                    :key="tag.id"
                                    class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"
                                >
                                    {{ tag.tag_name }}
                                </span>
                            </div>
                        </div>

                        <!-- Custom Fields -->
                        <div v-if="member.custom_fields && member.custom_fields.length">
                            <h4 class="font-semibold mb-2">Data Tambahan</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div 
                                    v-for="field in member.custom_fields" 
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
            title="Delete Member"
            @close="showDeleteConfirmation = false"
            @confirm="deleteMember"
        />
    </AuthenticatedLayout>
</template> 