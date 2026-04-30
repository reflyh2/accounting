<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    uom: Object,
    companies: Array,
    kinds: Array,
    filters: Object,
});

const defaultCompanyId = props.uom?.company_id ?? (props.companies?.length === 1 ? props.companies[0]?.id : null);

const form = useForm({
    company_id: defaultCompanyId,
    code: props.uom?.code ?? '',
    name: props.uom?.name ?? '',
    kind: props.uom?.kind ?? null,
    create_another: false,
});

const companyOptions = props.companies?.map((c) => ({ value: c.id, label: c.name })) ?? [];

function resetForm() {
    form.reset();
    form.clearErrors();
    form.company_id = defaultCompanyId;
}

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    if (props.uom) {
        form.put(route('uoms.update', props.uom.id), { preserveScroll: true });
    } else {
        form.post(route('uoms.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) resetForm();
            },
        });
    }
}
</script>

<template>
    <div class="flex justify-between">
        <form @submit.prevent="submitForm(false)" class="w-2/3 max-w-2xl mr-8">
            <AppSelect
                v-if="companyOptions.length > 1"
                v-model="form.company_id"
                label="Perusahaan:"
                :options="companyOptions"
                placeholder="Pilih perusahaan"
                :error="form.errors.company_id"
                required
            />
            <AppInput
                v-model="form.code"
                label="Kode:"
                placeholder="mis. pcs, kg, l"
                :error="form.errors.code"
                hint="Gunakan huruf kecil. Disimpan otomatis sebagai lowercase."
                autofocus
                required
            />
            <AppInput
                v-model="form.name"
                label="Nama:"
                placeholder="mis. Pieces, Kilogram, Liter"
                :error="form.errors.name"
                required
            />
            <AppSelect
                v-model="form.kind"
                label="Jenis:"
                :options="kinds"
                placeholder="Pilih jenis"
                :error="form.errors.kind"
                required
            />

            <div class="mt-4 flex items-center">
                <AppPrimaryButton type="submit" class="mr-2">
                    {{ uom ? 'Ubah' : 'Tambah' }} Satuan
                </AppPrimaryButton>
                <AppUtilityButton v-if="!uom" type="button" @click="submitForm(true)" class="mr-2">
                    Tambah & Buat Lagi
                </AppUtilityButton>
                <AppSecondaryButton @click="$inertia.visit(route('uoms.index', filters))">
                    Batal
                </AppSecondaryButton>
            </div>
        </form>

        <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Satuan</h3>
            <p class="mb-2">Satuan (UoM) adalah unit pengukuran untuk produk.</p>
            <ul class="list-disc list-inside">
                <li>Kode satuan harus unik di seluruh tenant</li>
                <li>Pilih jenis yang sesuai (each, weight, length, area, volume, time)</li>
                <li>Konversi antar satuan diatur di menu "Konversi Satuan"</li>
            </ul>
        </div>
    </div>
</template>
