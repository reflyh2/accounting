<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    conversion: Object,
    uoms: Array,
    filters: Object,
});

const form = useForm({
    from_uom_id: props.conversion?.from_uom_id ?? null,
    to_uom_id: props.conversion?.to_uom_id ?? null,
    numerator: props.conversion?.numerator ?? 1,
    denominator: props.conversion?.denominator ?? 1,
    create_reverse: props.conversion ? false : true,
    create_another: false,
});

const fromUom = computed(() => props.uoms.find((u) => u.id === form.from_uom_id) ?? null);
const toUom = computed(() => props.uoms.find((u) => u.id === form.to_uom_id) ?? null);

// From-UoM options: all UoMs.
const fromOptions = computed(() => props.uoms.map((u) => ({
    value: u.id,
    label: `${u.code} — ${u.name} (${u.kind})`,
})));

// To-UoM options: only UoMs of the same kind as the selected from_uom.
const toOptions = computed(() => {
    if (!fromUom.value) return [];
    return props.uoms
        .filter((u) => u.id !== fromUom.value.id && u.kind === fromUom.value.kind)
        .map((u) => ({ value: u.id, label: `${u.code} — ${u.name}` }));
});

const previewText = computed(() => {
    const num = Number(form.numerator) || 0;
    const den = Number(form.denominator) || 0;
    if (!fromUom.value || !toUom.value || den === 0) return '';
    const factor = num / den;
    return `1 ${fromUom.value.code} = ${factor.toLocaleString('id-ID', { maximumFractionDigits: 6 })} ${toUom.value.code}`;
});

function resetForm() {
    form.reset();
    form.clearErrors();
}

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    if (props.conversion) {
        form.put(route('uom-conversions.update', props.conversion.id), { preserveScroll: true });
    } else {
        form.post(route('uom-conversions.store'), {
            preserveScroll: true,
            onSuccess: () => { if (createAnother) resetForm(); },
        });
    }
}
</script>

<template>
    <div class="flex justify-between">
        <form @submit.prevent="submitForm(false)" class="w-2/3 max-w-2xl mr-8">
            <AppSelect
                v-model="form.from_uom_id"
                label="Dari Satuan:"
                :options="fromOptions"
                placeholder="Pilih satuan asal"
                :error="form.errors.from_uom_id"
                required
            />
            <AppSelect
                v-model="form.to_uom_id"
                label="Ke Satuan:"
                :options="toOptions"
                :placeholder="fromUom ? 'Pilih satuan tujuan' : 'Pilih satuan asal terlebih dahulu'"
                :disabled="!fromUom"
                :error="form.errors.to_uom_id"
                required
            />

            <div class="grid grid-cols-2 gap-4">
                <AppInput
                    v-model="form.numerator"
                    label="Pembilang:"
                    type="number"
                    step="any"
                    :error="form.errors.numerator"
                    required
                />
                <AppInput
                    v-model="form.denominator"
                    label="Penyebut:"
                    type="number"
                    step="any"
                    :error="form.errors.denominator"
                    required
                />
            </div>

            <p v-if="previewText" class="mt-2 text-sm text-gray-700 bg-blue-50 border border-blue-200 px-3 py-2 rounded">
                {{ previewText }}
            </p>

            <div v-if="!conversion" class="mt-4">
                <AppCheckbox
                    v-model="form.create_reverse"
                    label="Buat juga konversi kebalikan secara otomatis"
                />
            </div>
            <div v-else class="mt-4">
                <AppCheckbox
                    v-model="form.create_reverse"
                    label="Sinkronkan konversi kebalikan (jika ada)"
                />
            </div>

            <div class="mt-4 flex items-center">
                <AppPrimaryButton type="submit" class="mr-2">
                    {{ conversion ? 'Ubah' : 'Tambah' }} Konversi
                </AppPrimaryButton>
                <AppUtilityButton v-if="!conversion" type="button" @click="submitForm(true)" class="mr-2">
                    Tambah & Buat Lagi
                </AppUtilityButton>
                <AppSecondaryButton @click="$inertia.visit(route('uom-conversions.index', filters))">
                    Batal
                </AppSecondaryButton>
            </div>
        </form>

        <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Konversi Satuan</h3>
            <p class="mb-2">Konversi adalah aturan untuk mengubah satu satuan ke satuan lain dengan jenis yang sama.</p>
            <ul class="list-disc list-inside">
                <li>Contoh: 1 lusin = 12 pcs → pembilang 12, penyebut 1</li>
                <li>Hanya satuan dengan jenis yang sama yang dapat dikonversi</li>
                <li>Faktor dihitung otomatis dari pembilang ÷ penyebut</li>
                <li>Konversi kebalikan dibuat otomatis kecuali dimatikan</li>
            </ul>
        </div>
    </div>
</template>
