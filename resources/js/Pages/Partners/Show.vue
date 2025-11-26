<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    partner: Object,
    filters: Object,
    availableRoles: Object,
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
    <Head title="Detail Partner Bisnis" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Partner Bisnis</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('partners.index', filters)" text="Kembali ke Daftar Partner" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ partner.name }} ({{ partner.code }})</h3>
                            <div class="flex items-center">                              
                              <Link :href="route('partners.edit', partner.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <h4 class="text-lg font-semibold mb-3">Informasi Umum</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:py-1 text-sm">
                                <div>
                                    <p class="font-semibold">Status:</p>
                                    <p>{{ partner.status === 'active' ? 'Aktif' : 'Tidak Aktif' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Telepon:</p>
                                    <p>{{ partner.phone || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Email:</p>
                                    <p>{{ partner.email || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Alamat:</p>
                                    <p>{{ partner.address || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Kota:</p>
                                    <p>{{ partner.city || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Propinsi/Wilayah:</p>
                                    <p>{{ partner.region || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Negara:</p>
                                    <p>{{ partner.country || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Kode Pos:</p>
                                    <p>{{ partner.postal_code || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">NPWP:</p>
                                    <p>{{ partner.tax_id || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Nomor Registrasi:</p>
                                    <p>{{ partner.registration_number || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Industri:</p>
                                    <p>{{ partner.industry || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Website:</p>
                                    <p>{{ partner.website || '-' }}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <p class="font-semibold">Catatan:</p>
                                    <p>{{ partner.notes || '-' }}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <p class="font-semibold">Perusahaan:</p>
                                    <p>{{ partner.companies.map(company => company.name).join(', ') || '-' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <h4 class="text-lg font-semibold mb-3">Peran</h4>
                            <div v-if="partner.roles.length > 0" class="grid grid-cols-1 gap-4">
                                <div class="mb-4">
                                    <div class="flex flex-wrap gap-2 mb-2">
                                        <div v-for="role in partner.roles" :key="role.id" class="bg-blue-100 text-blue-800 text-xs font-medium px-3 py-1 rounded-full">
                                            {{ availableRoles[role.role] }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Display supplier settings if exists -->
                                <div v-if="partner.roles.some(r => r.role === 'supplier')" class="border border-gray-200 rounded p-4 mb-4">
                                    <h5 class="font-semibold mb-2">Pengaturan Supplier</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:py-1 text-sm">
                                        <div>
                                            <p class="font-semibold">Batas Kredit:</p>
                                            <p>{{ partner.roles.find(r => r.role === 'supplier')?.credit_limit || 0 }} </p>
                                        </div>
                                        <div>
                                            <p class="font-semibold">Kredit Terpakai:</p>
                                            <p>{{ partner.roles.find(r => r.role === 'supplier')?.used_credit || 0 }}</p>
                                        </div>
                                        <div>
                                            <p class="font-semibold">Jangka Waktu Pembayaran:</p>
                                            <p>{{ partner.roles.find(r => r.role === 'supplier')?.payment_term_days || 0 }} hari</p>
                                        </div>
                                        <div class="md:col-span-2">
                                            <p class="font-semibold">Catatan:</p>
                                            <p>{{ partner.roles.find(r => r.role === 'supplier')?.notes || '-' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Display customer settings if exists -->
                                <div v-if="partner.roles.some(r => r.role === 'customer')" class="border border-gray-200 rounded p-4">
                                    <h5 class="font-semibold mb-2">Pengaturan Customer</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 *:py-1 text-sm">
                                        <div>
                                            <p class="font-semibold">Batas Kredit:</p>
                                            <p>{{ partner.roles.find(r => r.role === 'customer')?.credit_limit || 0 }} </p>
                                        </div>
                                        <div>
                                            <p class="font-semibold">Kredit Terpakai:</p>
                                            <p>{{ partner.roles.find(r => r.role === 'customer')?.used_credit || 0 }}</p>
                                        </div>
                                        <div>
                                            <p class="font-semibold">Jangka Waktu Pembayaran:</p>
                                            <p>{{ partner.roles.find(r => r.role === 'customer')?.payment_term_days || 0 }} hari</p>
                                        </div>
                                        <div class="md:col-span-2">
                                            <p class="font-semibold">Catatan:</p>
                                            <p>{{ partner.roles.find(r => r.role === 'customer')?.notes || '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 italic">Tidak ada peran terdaftar</p>
                        </div>

                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <h4 class="text-lg font-semibold mb-3">Kontak</h4>
                            <div v-if="partner.contacts.length > 0" class="overflow-x-auto">
                                <table class="min-w-full bg-white border border-gray-300 text-sm">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="border border-gray-300 px-2 py-2 text-left">Nama</th>
                                            <th class="border border-gray-300 px-2 py-2 text-left">Jabatan</th>
                                            <th class="border border-gray-300 px-2 py-2 text-left">Email</th>
                                            <th class="border border-gray-300 px-2 py-2 text-left">Telepon</th>
                                            <th class="border border-gray-300 px-2 py-2 text-left">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(contact, index) in partner.contacts" :key="index">
                                            <td class="border border-gray-300 px-2 py-2">{{ contact.name }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ contact.position || '-' }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ contact.email || '-' }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ contact.phone || '-' }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ contact.notes || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="text-gray-500 italic">Tidak ada kontak terdaftar</p>
                        </div>

                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <h4 class="text-lg font-semibold mb-3">Rekening Bank</h4>
                            <div v-if="partner.bank_accounts && partner.bank_accounts.length > 0" class="overflow-x-auto">
                                <table class="min-w-full bg-white border border-gray-300 text-sm">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="border border-gray-300 px-2 py-2 text-left">Bank</th>
                                            <th class="border border-gray-300 px-2 py-2 text-left">No. Rekening</th>
                                            <th class="border border-gray-300 px-2 py-2 text-left">Atas Nama</th>
                                            <th class="border border-gray-300 px-2 py-2 text-left">Cabang</th>
                                            <th class="border border-gray-300 px-2 py-2 text-left">Mata Uang</th>
                                            <th class="border border-gray-300 px-2 py-2 text-left">Utama</th>
                                            <th class="border border-gray-300 px-2 py-2 text-left">Aktif</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="acc in partner.bank_accounts" :key="acc.id">
                                            <td class="border border-gray-300 px-2 py-2">{{ acc.bank_name }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ acc.account_number }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ acc.account_holder_name }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ acc.branch_name || '-' }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ acc.currency || '-' }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ acc.is_primary ? 'Ya' : 'Tidak' }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ acc.is_active ? 'Ya' : 'Tidak' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="text-gray-500 italic">Tidak ada rekening bank terdaftar</p>
                        </div>

                        <div class="mt-6 border-t border-gray-200 pt-4 text-sm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="font-semibold">Dibuat Oleh:</p>
                                    <p>{{ partner.created_by?.name || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Tanggal Pembuatan:</p>
                                    <p>{{ partner.created_at ? new Date(partner.created_at).toLocaleString() : '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Diubah Oleh:</p>
                                    <p>{{ partner.updated_by?.name || '-' }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Tanggal Pengubahan:</p>
                                    <p>{{ partner.updated_at ? new Date(partner.updated_at).toLocaleString() : '-' }}</p>
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