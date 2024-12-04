<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    partner: Object,
    companies: Array,
    statuses: Object,
    paymentTermTypes: Object,
    filters: Object,
});

const form = useForm({
    name: props.partner?.name || '',
    email: props.partner?.email || '',
    phone: props.partner?.phone || '',
    address: props.partner?.address || '',
    tax_id: props.partner?.tax_id || '',
    registration_number: props.partner?.registration_number || '',
    industry: props.partner?.industry || '',
    website: props.partner?.website || '',
    status: props.partner?.status || 'active',
    company_ids: props.partner?.companies?.map(company => company.id) || [],
    credit_terms: props.partner?.credit_terms || {
        credit_limit: 0,
        used_credit: 0,
        payment_term_days: 0,
        payment_term_type: 'net',
        notes: '',
    },
    tags: props.partner?.tags?.map(tag => tag.tag_name) || [],
    custom_fields: props.partner?.custom_fields || [],
    create_another: false,
});

const newTag = ref('');
const newCustomField = ref({ field_name: '', field_value: '' });

const statusOptions = computed(() => 
    Object.entries(props.statuses).map(([value, label]) => ({ value, label }))
);

const paymentTermOptions = computed(() => 
    Object.entries(props.paymentTermTypes).map(([value, label]) => ({ value, label }))
);

function addTag() {
    if (newTag.value && !form.tags.includes(newTag.value)) {
        form.tags.push(newTag.value);
        newTag.value = '';
    }
}

function removeTag(tag) {
    const index = form.tags.indexOf(tag);
    if (index > -1) {
        form.tags.splice(index, 1);
    }
}

function addCustomField() {
    if (newCustomField.value.field_name && newCustomField.value.field_value) {
        form.custom_fields.push({ ...newCustomField.value });
        newCustomField.value = { field_name: '', field_value: '' };
    }
}

function removeCustomField(index) {
    form.custom_fields.splice(index, 1);
}

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    
    if (props.partner) {
        form.put(route('partners.update', props.partner.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('partners.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                }
            },
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Basic Information -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Informasi Dasar</h3>
                
                <AppSelect
                    v-model="form.company_ids"
                    label="Companies:"
                    :options="companies.map(company => ({ value: company.id, label: company.name }))"
                    placeholder="Select companies"
                    :error="form.errors.company_ids"
                    multiple
                    required
                />

                <AppInput
                    v-model="form.name"
                    label="Name:"
                    placeholder="Enter partner name"
                    :error="form.errors.name"
                    required
                />

                <AppInput
                    v-model="form.email"
                    type="email"
                    label="Email:"
                    placeholder="Enter email address"
                    :error="form.errors.email"
                />

                <AppInput
                    v-model="form.phone"
                    label="Phone:"
                    placeholder="Enter phone number"
                    :error="form.errors.phone"
                />

                <AppTextarea
                    v-model="form.address"
                    label="Address:"
                    placeholder="Enter address"
                    :error="form.errors.address"
                />
            </div>

            <!-- Business Information -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Informasi Bisnis</h3>

                <AppInput
                    v-model="form.tax_id"
                    label="Tax ID:"
                    placeholder="Enter tax ID"
                    :error="form.errors.tax_id"
                />

                <AppInput
                    v-model="form.registration_number"
                    label="Registration Number:"
                    placeholder="Enter registration number"
                    :error="form.errors.registration_number"
                />

                <AppInput
                    v-model="form.industry"
                    label="Industry:"
                    placeholder="Enter industry"
                    :error="form.errors.industry"
                />

                <AppInput
                    v-model="form.website"
                    label="Website:"
                    placeholder="Enter website"
                    :error="form.errors.website"
                />

                <AppSelect
                    v-model="form.status"
                    label="Status:"
                    :options="statusOptions"
                    :error="form.errors.status"
                    required
                />
            </div>

            <!-- Credit Terms -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Term Kredit</h3>

                <AppInput
                    v-model="form.credit_terms.credit_limit"
                    type="number"
                    label="Credit Limit:"
                    placeholder="Enter credit limit"
                    :error="form.errors['credit_terms.credit_limit']"
                />

                <AppInput
                    v-model="form.credit_terms.used_credit"
                    type="number"
                    label="Used Credit:"
                    placeholder="Enter used credit"
                    :error="form.errors['credit_terms.used_credit']"
                />

                <AppSelect
                    v-model="form.credit_terms.payment_term_type"
                    label="Payment Term Type:"
                    :options="paymentTermOptions"
                    :error="form.errors['credit_terms.payment_term_type']"
                />

                <AppInput
                    v-model="form.credit_terms.payment_term_days"
                    type="number"
                    label="Payment Term Days:"
                    placeholder="Enter payment term days"
                    :error="form.errors['credit_terms.payment_term_days']"
                />

                <AppTextarea
                    v-model="form.credit_terms.notes"
                    label="Notes:"
                    placeholder="Enter notes"
                    :error="form.errors['credit_terms.notes']"
                />
            </div>

            <!-- Tags and Custom Fields -->
            <div class="space-y-4">
                <div>
                    <h3 class="text-lg font-semibold mb-4">Tag</h3>
                    <div class="flex items-center gap-2">
                        <AppInput
                            v-model="newTag"
                            placeholder="Enter tag"
                            class="flex-grow"
                            @keyup.enter.prevent="addTag"
                        />
                        <AppUtilityButton type="button" class="mb-4" @click="addTag">Tambah Tag</AppUtilityButton>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span 
                            v-for="tag in form.tags" 
                            :key="tag"
                            class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm flex items-center"
                        >
                            {{ tag }}
                            <button 
                                type="button"
                                class="ml-1 text-blue-600 hover:text-blue-800"
                                @click="removeTag(tag)"
                            >
                                ×
                            </button>
                        </span>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">Data Tambahan</h3>
                    <div class="flex items-center gap-2">
                        <AppInput
                            v-model="newCustomField.field_name"
                            placeholder="Field name"
                            class="flex-grow"
                        />
                        <AppInput
                            v-model="newCustomField.field_value"
                            placeholder="Field value"
                            class="flex-grow"
                        />
                        <AppUtilityButton type="button" class="mb-4" @click="addCustomField">Tambah Data</AppUtilityButton>
                    </div>
                    <div class="space-y-2">
                        <div 
                            v-for="(field, index) in form.custom_fields" 
                            :key="index"
                            class="flex items-center gap-2"
                        >
                            <span class="flex-grow">
                                <strong>{{ field.field_name }}:</strong> {{ field.field_value }}
                            </span>
                            <button 
                                type="button"
                                class="text-red-600 hover:text-red-800"
                                @click="removeCustomField(index)"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-start gap-2">
            <AppPrimaryButton type="submit" :disabled="form.processing">
                {{ partner ? 'Ubah' : 'Buat' }} Partner
            </AppPrimaryButton>
            <AppUtilityButton 
                v-if="!partner"
                type="button" 
                @click="submitForm(true)"
                :disabled="form.processing"
            >
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton 
                type="button"
                @click="$inertia.visit(route('partners.index', filters))"
            >
                Cancel
            </AppSecondaryButton>
        </div>
    </form>
</template> 