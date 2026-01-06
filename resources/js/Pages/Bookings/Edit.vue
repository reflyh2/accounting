<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import BookingForm from './Partials/BookingForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    booking: Object,
    formOptions: Object,
    filters: Object,
});
</script>

<template>
    <Head title="Edit Booking" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Data Booking</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('bookings.index', filters)" text="Kembali ke Daftar Booking" />
                        </div>
                        <BookingForm :booking="booking" :formOptions="formOptions" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
