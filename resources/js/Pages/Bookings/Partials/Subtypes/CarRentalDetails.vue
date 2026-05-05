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
        <AppInput :modelValue="meta.vehicle_class" label="Kelas Kendaraan:" @update:modelValue="(v) => field('vehicle_class', v)" />
        <AppInput :modelValue="meta.plate_number" label="No. Polisi:" @update:modelValue="(v) => field('plate_number', v)" />
        <AppInput :modelValue="meta.driver_name" label="Nama Pengemudi:" @update:modelValue="(v) => field('driver_name', v)" />
        <AppInput :modelValue="meta.driver_license_number" label="No. SIM:" @update:modelValue="(v) => field('driver_license_number', v)" />
        <AppInput :modelValue="meta.pickup_datetime" type="datetime-local" label="Mulai:" @update:modelValue="(v) => field('pickup_datetime', v)" />
        <AppInput :modelValue="meta.return_datetime" type="datetime-local" label="Selesai:" @update:modelValue="(v) => field('return_datetime', v)" />
        <AppInput :modelValue="meta.odo_start" type="number" label="Odo Awal:" @update:modelValue="(v) => field('odo_start', v)" />
        <AppInput :modelValue="meta.odo_end" type="number" label="Odo Akhir:" @update:modelValue="(v) => field('odo_end', v)" />
    </div>
</template>
