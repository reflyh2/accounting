<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    paymentTerm: Object,
    companies: Array,
    filters: Object,
});

const isEditMode = computed(() => !!props.paymentTerm);

const form = useForm({
    company_id: props.paymentTerm?.company_id || (props.companies?.length === 1 ? props.companies[0].id : null),
    code: props.paymentTerm?.code || '',
    name: props.paymentTerm?.name || '',
    days: props.paymentTerm?.days || 0,
    description: props.paymentTerm?.description || '',
    is_active: props.paymentTerm?.is_active ?? true,
    create_another: false,
});

const companyOptions = computed(() =>
    props.companies.map(c => ({ value: c.id, label: c.name }))
);

function submitForm(createAnother = false) {
    form.create_another = createAnother;

    if (isEditMode.value) {
        form.put(route('settings.payment-terms.update', props.paymentTerm.id));
    } else {
        form.post(route('settings.payment-terms.store'));
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
                placeholder="NET30, COD, etc."
                :error="form.errors.code"
                required
            />
        </div>

        <AppInput
            v-model="form.name"
            label="Nama:"
            placeholder="Nama Payment Term"
            :error="form.errors.name"
            required
        />

        <AppInput
            v-model.number="form.days"
            type="number"
            label="Jumlah Hari:"
            placeholder="0"
            :error="form.errors.days"
            min="0"
            required
        />

        <AppTextarea
            v-model="form.description"
            label="Deskripsi:"
            placeholder="Deskripsi payment term (opsional)"
            :error="form.errors.description"
            rows="3"
        />

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
            <AppSecondaryButton @click="$inertia.visit(route('settings.payment-terms.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
