<script setup>
import { computed } from 'vue';
import AppInput from '@/Components/AppInput.vue';

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue']);

const meta = computed({
    get: () => props.modelValue || {},
    set: (val) => emit('update:modelValue', val),
});

function field(key, value) {
    emit('update:modelValue', { ...meta.value, [key]: value });
}
</script>

<template>
    <div class="grid grid-cols-3 gap-3">
        <AppInput :modelValue="meta.flight_number" label="Nomor Penerbangan:" @update:modelValue="(v) => field('flight_number', v)" />
        <AppInput :modelValue="meta.carrier_code" label="Kode Maskapai:" @update:modelValue="(v) => field('carrier_code', v)" />
        <AppInput :modelValue="meta.fare_class" label="Kelas:" @update:modelValue="(v) => field('fare_class', v)" />
        <AppInput :modelValue="meta.origin" label="Asal (IATA):" @update:modelValue="(v) => field('origin', v)" />
        <AppInput :modelValue="meta.destination" label="Tujuan (IATA):" @update:modelValue="(v) => field('destination', v)" />
        <AppInput :modelValue="meta.pnr" label="PNR:" @update:modelValue="(v) => field('pnr', v)" />
        <AppInput :modelValue="meta.departure_datetime" type="datetime-local" label="Take-off:" @update:modelValue="(v) => field('departure_datetime', v)" />
        <AppInput :modelValue="meta.arrival_datetime" type="datetime-local" label="Landing:" @update:modelValue="(v) => field('arrival_datetime', v)" />
        <AppInput :modelValue="meta.passenger_name" label="Nama Penumpang:" @update:modelValue="(v) => field('passenger_name', v)" />
        <AppInput :modelValue="meta.passenger_doc_number" label="No. Dokumen:" @update:modelValue="(v) => field('passenger_doc_number', v)" />
    </div>
</template>
