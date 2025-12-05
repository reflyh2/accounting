<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    currency: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteCurrency = () => {
    form.delete(route('currencies.destroy', props.currency.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Mata Uang" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Mata Uang</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('currencies.index', filters)" text="Kembali ke Daftar Mata Uang" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ currency.code }} - {{ currency.name }}</h3>
                            <div class="flex items-center">
                                <Link :href="route('currencies.edit', currency.id)">
                                    <AppEditButton title="Ubah" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Hapus" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="font-semibold">Kode Mata Uang:</p>
                                <p>{{ currency.code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nama Mata Uang:</p>
                                <p>{{ currency.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Simbol Mata Uang:</p>
                                <p>{{ currency.symbol }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Mata Uang Utama:</p>
                                <p>{{ currency.is_primary ? 'Ya' : 'Tidak' }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="font-semibold mb-2">Nilai Tukar:</h4>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr>
                                        <th class="text-left">Perusahaan</th>
                                        <th class="text-left">Nilai Tukar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="rate in currency.company_rates" :key="rate.id">
                                        <td>{{ rate.company.name }}</td>
                                        <td>{{ rate.exchange_rate }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Mata Uang"
            @close="showDeleteConfirmation = false"
            @confirm="deleteCurrency"
        />
    </AuthenticatedLayout>
</template>