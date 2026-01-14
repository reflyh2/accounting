<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';

const props = defineProps({
    costItem: Object,
    filters: Object,
});

function deleteCostItem() {
    router.delete(route('costing.cost-items.destroy', props.costItem.id));
}
</script>

<template>
    <Head :title="`Cost Item: ${costItem.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2>Detail Cost Item</h2>
                <div class="flex gap-2">
                    <AppEditButton :href="route('costing.cost-items.edit', costItem.id)" />
                    <AppDeleteButton @delete="deleteCostItem" confirmMessage="Hapus cost item ini?" />
                </div>
            </div>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('costing.cost-items.index', filters)" text="Kembali ke Daftar Cost Items" />
                        </div>

                        <dl class="grid grid-cols-2 gap-4 max-w-2xl">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Kode</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ costItem.code }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nama</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ costItem.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Perusahaan</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ costItem.company?.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <span :class="costItem.is_active ? 'text-green-600' : 'text-red-600'">
                                        {{ costItem.is_active ? 'Aktif' : 'Non-aktif' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Akun Debit (Expense)</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ costItem.debit_account?.code }} - {{ costItem.debit_account?.name }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Akun Kredit (Offset)</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ costItem.credit_account?.code }} - {{ costItem.credit_account?.name }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
