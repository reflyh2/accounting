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
        <AppInput :modelValue="meta.room_type" label="Tipe Kamar:" @update:modelValue="(v) => field('room_type', v)" />
        <AppInput :modelValue="meta.check_in" type="datetime-local" label="Check-in:" @update:modelValue="(v) => field('check_in', v)" />
        <AppInput :modelValue="meta.check_out" type="datetime-local" label="Check-out:" @update:modelValue="(v) => field('check_out', v)" />
        <AppInput :modelValue="meta.guest_count" type="number" label="Jumlah Tamu:" @update:modelValue="(v) => field('guest_count', v)" />
        <AppInput :modelValue="meta.board_basis" label="Paket Makan:" placeholder="RO, BB, HB, FB" @update:modelValue="(v) => field('board_basis', v)" />
        <AppInput :modelValue="meta.guest_name" label="Nama Tamu Utama:" @update:modelValue="(v) => field('guest_name', v)" />
    </div>
</template>
