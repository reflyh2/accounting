<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch, ref } from 'vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    costItem: Object,
    companies: Array,
    accounts: Array,
    filters: Object,
});

const isEditMode = computed(() => !!props.costItem);

const form = useForm({
    company_id: props.costItem?.company_id || (props.companies?.length === 1 ? props.companies[0].id : null),
    code: props.costItem?.code || '',
    name: props.costItem?.name || '',
    debit_account_id: props.costItem?.debit_account_id || null,
    credit_account_id: props.costItem?.credit_account_id || null,
    is_active: props.costItem?.is_active ?? true,
    create_another: false,
});

const companyOptions = computed(() => 
    props.companies.map(c => ({ value: c.id, label: c.name }))
);

const debitAccountOptions = computed(() => {
    if (!form.company_id) return [];
    return props.accounts
        .filter(a => a.company_ids?.includes(form.company_id))
        .map(a => ({ value: a.id, label: a.label }));
});

const creditAccountOptions = computed(() => {
    if (!form.company_id) return [];
    return props.accounts
        .filter(a => a.company_ids?.includes(form.company_id))
        .map(a => ({ value: a.id, label: a.label }));
});

// Reset accounts when company changes
watch(() => form.company_id, () => {
    if (!isEditMode.value) {
        form.debit_account_id = null;
        form.credit_account_id = null;
    }
});

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    
    if (isEditMode.value) {
        form.put(route('costing.cost-items.update', props.costItem.id));
    } else {
        form.post(route('costing.cost-items.store'));
    }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4 max-w-2xl">
        <div class="grid grid-cols-2 gap-4">
            <AppSelect
                v-model="form.company_id"
                :options="companyOptions"
                label="Perusahaan:"
                placeholder="Pilih Perusahaan"
                :error="form.errors.company_id"
                :disabled="isEditMode"
                required
            />
            
            <AppInput
                v-model="form.code"
                label="Kode:"
                placeholder="SHIP, PACK, etc."
                :error="form.errors.code"
                required
            />
        </div>

        <AppInput
            v-model="form.name"
            label="Nama:"
            placeholder="Nama Cost Item"
            :error="form.errors.name"
            required
        />

        <div class="grid grid-cols-2 gap-4">
            <AppSelect
                v-model="form.debit_account_id"
                :options="debitAccountOptions"
                label="Akun Debit (Expense):"
                placeholder="Pilih Akun"
                :error="form.errors.debit_account_id"
                :disabled="!form.company_id"
                required
            />
            
            <AppSelect
                v-model="form.credit_account_id"
                :options="creditAccountOptions"
                label="Akun Kredit (Offset):"
                placeholder="Pilih Akun"
                :error="form.errors.credit_account_id"
                :disabled="!form.company_id"
                required
            />
        </div>

        <AppCheckbox
            v-model="form.is_active"
            label="Aktif"
        />

        <div class="flex items-center pt-4">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="form.processing">
                {{ isEditMode ? 'Simpan' : 'Buat' }}
            </AppPrimaryButton>
            <AppSecondaryButton v-if="!isEditMode" type="button" @click="submitForm(true)" class="mr-2" :disabled="form.processing">
                Buat & Buat Lagi
            </AppSecondaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('costing.cost-items.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
