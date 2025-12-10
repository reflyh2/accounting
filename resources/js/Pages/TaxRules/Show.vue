<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    rule: Object,
    filters: Object,
    rateTypes: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteRule = () => {
    form.delete(route('tax-rules.destroy', props.rule.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Aturan Pajak" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Aturan Pajak</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('tax-rules.index', filters)" text="Kembali ke Daftar Aturan" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ rule.tax_category?.name }} - {{ rule.component?.name }}</h3>
                            <div class="flex items-center">
                              <Link :href="route('tax-rules.edit', rule.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Kategori Pajak:</p>
                                <p>{{ rule.tax_category?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Yurisdiksi:</p>
                                <p>{{ rule.jurisdiction?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Komponen Pajak:</p>
                                <p>{{ rule.component?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tipe Tarif:</p>
                                <p>{{ rateTypes[rule.rate_type] || rule.rate_type }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tarif:</p>
                                <p>{{ rule.rate_type === 'percent' ? rule.rate_value + '%' : rule.rate_value }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Termasuk Pajak:</p>
                                <p>{{ rule.tax_inclusive ? 'Ya' : 'Tidak' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Berlaku B2B:</p>
                                <p>{{ rule.b2b_applicable === null ? '-' : (rule.b2b_applicable ? 'Ya' : 'Tidak') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Reverse Charge:</p>
                                <p>{{ rule.reverse_charge ? 'Ya' : 'Tidak' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Ekspor Tarif Nol:</p>
                                <p>{{ rule.export_zero_rate ? 'Ya' : 'Tidak' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Ambang Batas:</p>
                                <p>{{ rule.threshold_amount || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Berlaku Dari:</p>
                                <p>{{ rule.effective_from }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Berlaku Sampai:</p>
                                <p>{{ rule.effective_to || 'Tidak terbatas' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Prioritas:</p>
                                <p>{{ rule.priority }}</p>
                            </div>
                            <div v-if="rule.per_unit_uom">
                                <p class="font-semibold">UOM Per Unit:</p>
                                <p>{{ rule.per_unit_uom?.name }}</p>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-2 gap-4 *:py-1 text-sm text-gray-500">
                            <div v-if="rule.creator">
                                <p class="font-semibold">Dibuat oleh:</p>
                                <p>{{ rule.creator?.name }}</p>
                            </div>
                            <div v-if="rule.updater">
                                <p class="font-semibold">Diubah oleh:</p>
                                <p>{{ rule.updater?.name }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Aturan Pajak"
            @close="showDeleteConfirmation = false"
            @confirm="deleteRule"
        />
    </AuthenticatedLayout>
</template>
