<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { ref, watch, onMounted } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    partner: Object,
    companies: Array,
    filters: Object,
    availableRoles: Object,
});

const form = useForm({
    name: props.partner?.name || '',
    phone: props.partner?.phone || '',
    email: props.partner?.email || '',
    address: props.partner?.address || '',
    city: props.partner?.city || '',
    region: props.partner?.region || '',
    country: props.partner?.country || '',
    postal_code: props.partner?.postal_code || '',
    tax_id: props.partner?.tax_id || '',
    registration_number: props.partner?.registration_number || '',
    industry: props.partner?.industry || '',
    website: props.partner?.website || '',
    notes: props.partner?.notes || '',
    status: props.partner?.status || 'active',
    company_ids: props.partner?.companies?.map(co => co.id) || [],
    selectedRoles: {
        supplier: props.partner?.roles?.some(r => r.role === 'supplier') || false,
        customer: props.partner?.roles?.some(r => r.role === 'customer') || false,
        asset_supplier: props.partner?.roles?.some(r => r.role === 'asset_supplier') || false,
        asset_customer: props.partner?.roles?.some(r => r.role === 'asset_customer') || false,
        creditor: props.partner?.roles?.some(r => r.role === 'creditor') || false,
        others: props.partner?.roles?.some(r => r.role === 'others') || false,
    },
    supplier_settings: {
        credit_limit: props.partner?.roles?.find(r => r.role === 'supplier')?.credit_limit || 0,
        payment_term_days: props.partner?.roles?.find(r => r.role === 'supplier')?.payment_term_days || 0,
        notes: props.partner?.roles?.find(r => r.role === 'supplier')?.notes || '',
    },
    customer_settings: {
        credit_limit: props.partner?.roles?.find(r => r.role === 'customer')?.credit_limit || 0,
        payment_term_days: props.partner?.roles?.find(r => r.role === 'customer')?.payment_term_days || 0,
        notes: props.partner?.roles?.find(r => r.role === 'customer')?.notes || '',
    },
    contacts: props.partner?.contacts?.map(contact => ({
        id: contact.id,
        name: contact.name,
        email: contact.email,
        phone: contact.phone,
        position: contact.position,
        notes: contact.notes
    })) || [],
    create_another: false,
});

const submitted = ref(false);
const activeTab = ref('general');

function addContact() {
    form.contacts.push({ name: '', email: '', phone: '', position: '', notes: '' });
}

function removeContact(index) {
    form.contacts.splice(index, 1);
}

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;

    const roles = [];
    
    if (form.selectedRoles.supplier) {
        roles.push({
            role: 'supplier',
            credit_limit: form.supplier_settings.credit_limit,
            payment_term_days: form.supplier_settings.payment_term_days,
            notes: form.supplier_settings.notes,
        });
    }
    
    if (form.selectedRoles.customer) {
        roles.push({
            role: 'customer',
            credit_limit: form.customer_settings.credit_limit,
            payment_term_days: form.customer_settings.payment_term_days,
            notes: form.customer_settings.notes,
        });
    }
    
    if (form.selectedRoles.asset_supplier) {
        roles.push({
            role: 'asset_supplier',
            credit_limit: 0,
            payment_term_days: 0,
            notes: '',
        });
    }
    
    if (form.selectedRoles.asset_customer) {
        roles.push({
            role: 'asset_customer',
            credit_limit: 0,
            payment_term_days: 0,
            notes: '',
        });
    }
    
    if (form.selectedRoles.creditor) {
        roles.push({
            role: 'creditor',
            credit_limit: 0,
            payment_term_days: 0,
            notes: '',
        });
    }
    
    if (form.selectedRoles.others) {
        roles.push({
            role: 'others',
            credit_limit: 0,
            payment_term_days: 0,
            notes: '',
        });
    }

    const filteredContacts = form.contacts.filter(contact => contact.name.trim() !== '');

    const formData = {...form};
    formData.roles = roles;
    formData.contacts = filteredContacts;
    delete formData.selectedRoles;
    delete formData.supplier_settings;
    delete formData.customer_settings;

    if (props.partner) {
        form.transform((data) => ({
            ...data,
            roles,
            contacts: filteredContacts,
            selectedRoles: undefined,
            supplier_settings: undefined,
            customer_settings: undefined,
        })).put(route('partners.update', props.partner.id), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
            },
            onError: () => {
                submitted.value = false;
            }
        });
    } else {
        form.transform((data) => ({
            ...data,
            roles,
            contacts: filteredContacts,
            selectedRoles: undefined,
            supplier_settings: undefined,
            customer_settings: undefined,
        })).post(route('partners.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            },
            onError: () => {
                submitted.value = false;
            }
        });
    }
}
</script>

<template>
    <div class="w-full">
        <div class="mb-4 border-b border-gray-200">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                <li class="mr-2" role="presentation">
                    <button 
                        :class="[
                            'inline-block p-4 rounded-t-lg border-b-2', 
                            activeTab === 'general' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'
                        ]"
                        @click.prevent="activeTab = 'general'"
                    >
                        Informasi Umum
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <button 
                        :class="[
                            'inline-block p-4 rounded-t-lg border-b-2', 
                            activeTab === 'roles' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'
                        ]"
                        @click.prevent="activeTab = 'roles'"
                    >
                        Peran
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <button 
                        :class="[
                            'inline-block p-4 rounded-t-lg border-b-2', 
                            activeTab === 'contacts' ? 'border-blue-600 text-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'
                        ]"
                        @click.prevent="activeTab = 'contacts'"
                    >
                        Kontak
                    </button>
                </li>
            </ul>
        </div>

        <form @submit.prevent="submitForm(false)" class="space-y-4">
            <!-- General Information -->
            <div v-show="activeTab === 'general'">
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <AppInput
                            v-model="form.name"
                            label="Nama:"
                            :error="form.errors.name"
                            required
                        />
                    </div>
                    <div>
                        <AppSelect
                            v-model="form.status"
                            :options="[
                                { value: 'active', label: 'Aktif' },
                                { value: 'inactive', label: 'Tidak Aktif' }
                            ]"
                            label="Status:"
                            :error="form.errors.status"
                            required
                        />
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <AppInput
                            v-model="form.phone"
                            label="Telepon:"
                            :error="form.errors.phone"
                        />
                    </div>
                    <div>
                        <AppInput
                            v-model="form.email"
                            label="Email:"
                            type="email"
                            :error="form.errors.email"
                        />
                    </div>
                </div>

                <div class="mb-4">
                    <AppTextarea
                        v-model="form.address"
                        label="Alamat:"
                        :error="form.errors.address"
                    />
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <AppInput
                            v-model="form.city"
                            label="Kota:"
                            :error="form.errors.city"
                        />
                    </div>
                    <div>
                        <AppInput
                            v-model="form.region"
                            label="Propinsi/Wilayah:"
                            :error="form.errors.region"
                        />
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <AppInput
                            v-model="form.country"
                            label="Negara:"
                            :error="form.errors.country"
                        />
                    </div>
                    <div>
                        <AppInput
                            v-model="form.postal_code"
                            label="Kode Pos:"
                            :error="form.errors.postal_code"
                        />
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <AppInput
                            v-model="form.tax_id"
                            label="NPWP:"
                            :error="form.errors.tax_id"
                        />
                    </div>
                    <div>
                        <AppInput
                            v-model="form.registration_number"
                            label="Nomor Registrasi Perusahaan:"
                            :error="form.errors.registration_number"
                        />
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <AppInput
                            v-model="form.industry"
                            label="Industri:"
                            :error="form.errors.industry"
                        />
                    </div>
                    <div>
                        <AppInput
                            v-model="form.website"
                            label="Website:"
                            :error="form.errors.website"
                        />
                    </div>
                </div>

                <div class="mb-4">
                    <AppTextarea
                        v-model="form.notes"
                        label="Catatan:"
                        :error="form.errors.notes"
                    />
                </div>

                <div class="mb-4">
                    <AppSelect
                        v-model="form.company_ids"
                        :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        multiple
                        :error="form.errors.company_ids"
                        required
                    />
                    <div v-if="form.errors['company_ids.0']" class="mt-1 text-red-600 text-sm">
                        {{ form.errors['company_ids.0'] }}
                    </div>
                </div>
            </div>

            <!-- Roles Tab -->
            <div v-show="activeTab === 'roles'">
                <div class="rounded-lg border border-gray-200 p-4 mb-6">
                    <h3 class="text-lg font-medium mb-4">Pilihan Peran</h3>
                    <p class="text-sm text-gray-600 mb-4">Pilih peran untuk partner bisnis ini. Satu partner dapat memiliki beberapa peran.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div v-for="(label, role) in availableRoles" :key="role" class="flex items-center">
                            <input 
                                type="checkbox" 
                                :id="'role-' + role" 
                                v-model="form.selectedRoles[role]" 
                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                            >
                            <label :for="'role-' + role" class="ml-2 text-sm font-medium text-gray-900">{{ label }}</label>
                        </div>
                    </div>
                </div>
                
                <!-- Settings for Supplier role -->
                <div v-if="form.selectedRoles.supplier" class="rounded-lg border border-gray-200 p-4 mb-6">
                    <h3 class="text-lg font-medium mb-4">Pengaturan Peran Pemasok</h3>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <AppInput
                                v-model="form.supplier_settings.credit_limit"
                                label="Batas Kredit:"
                                type="number"
                                min="0"
                                step="0.01"
                                :error="form.errors['supplier_settings.credit_limit']"
                            />
                        </div>
                        <div>
                            <AppInput
                                v-model="form.supplier_settings.payment_term_days"
                                label="Jangka Waktu Pembayaran (hari):"
                                type="number"
                                min="0"
                                :error="form.errors['supplier_settings.payment_term_days']"
                            />
                        </div>
                    </div>

                    <div class="mb-4">
                        <AppTextarea
                            v-model="form.supplier_settings.notes"
                            label="Catatan Supplier:"
                            :error="form.errors['supplier_settings.notes']"
                        />
                    </div>
                </div>
                
                <!-- Settings for Customer role -->
                <div v-if="form.selectedRoles.customer" class="rounded-lg border border-gray-200 p-4 mb-6">
                    <h3 class="text-lg font-medium mb-4">Pengaturan Peran Pelanggan</h3>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <AppInput
                                v-model="form.customer_settings.credit_limit"
                                label="Batas Kredit:"
                                type="number"
                                min="0"
                                step="0.01"
                                :error="form.errors['customer_settings.credit_limit']"
                            />
                        </div>
                        <div>
                            <AppInput
                                v-model="form.customer_settings.payment_term_days"
                                label="Jangka Waktu Pembayaran (hari):"
                                type="number"
                                min="0"
                                :error="form.errors['customer_settings.payment_term_days']"
                            />
                        </div>
                    </div>

                    <div class="mb-4">
                        <AppTextarea
                            v-model="form.customer_settings.notes"
                            label="Catatan Customer:"
                            :error="form.errors['customer_settings.notes']"
                        />
                    </div>
                </div>
                
                <div v-if="!Object.values(form.selectedRoles).some(v => v)" class="text-center py-10 text-gray-500">
                    Silakan pilih minimal satu peran untuk partner ini
                </div>
            </div>

            <!-- Contacts Tab -->
            <div v-show="activeTab === 'contacts'">
                <div v-if="form.contacts.length === 0" class="text-center py-10 border border-gray-200 rounded-lg mb-6">
                    <p class="text-gray-600 mb-4">Belum ada kontak yang ditambahkan.</p>
                    <button 
                        type="button" 
                        @click="addContact" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center mx-auto"
                    >
                        <PlusCircleIcon class="w-5 h-5 mr-2" /> Tambah Kontak
                    </button>
                </div>

                <div v-for="(contact, index) in form.contacts" :key="index" class="mb-6 p-4 rounded-lg border border-gray-200">
                    <div class="flex justify-between mb-2">
                        <h3 class="text-lg font-medium">Kontak #{{ index + 1 }}</h3>
                        <button type="button" @click="removeContact(index)" class="text-red-500 hover:text-red-700">
                            <TrashIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <AppInput
                                v-model="contact.name"
                                label="Nama:"
                                :error="form.errors[`contacts.${index}.name`]"
                                required
                            />
                        </div>
                        <div>                            
                            <AppInput
                                v-model="contact.position"
                                label="Jabatan:"
                                :error="form.errors[`contacts.${index}.position`]"
                            />
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <AppInput
                                v-model="contact.email"
                                label="Email:"
                                type="email"
                                :error="form.errors[`contacts.${index}.email`]"
                            />
                        </div>
                        <div>
                            <AppInput
                                v-model="contact.phone"
                                label="Telepon:"
                                :error="form.errors[`contacts.${index}.phone`]"
                            />
                        </div>
                    </div>

                    <div class="mb-4">
                        <AppTextarea
                            v-model="contact.notes"
                            label="Catatan:"
                            :error="form.errors[`contacts.${index}.notes`]"
                        />
                    </div>
                </div>

                <div v-if="form.contacts.length > 0" class="flex mt-2 mb-4">
                    <button type="button" @click="addContact" class="flex items-center text-blue-600 hover:text-blue-800">
                        <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Kontak
                    </button>
                </div>
            </div>

            <div class="mt-6 flex items-center">
                <AppPrimaryButton type="submit" class="mr-2" :disabled="submitted">
                    {{ props.partner ? 'Ubah' : 'Tambah' }} Partner
                </AppPrimaryButton>
                <AppUtilityButton v-if="!props.partner" type="button" @click="submitForm(true)" class="mr-2" :disabled="submitted">
                    Tambah & Buat Lagi
                </AppUtilityButton>
                <AppSecondaryButton @click="$inertia.visit(route('partners.index', filters))">
                    Batal
                </AppSecondaryButton>
            </div>
        </form>
    </div>
</template> 