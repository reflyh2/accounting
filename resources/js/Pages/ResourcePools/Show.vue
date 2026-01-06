<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { PlusCircleIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    resourcePool: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

function deletePool() {
    form.delete(route('resource-pools.destroy', props.resourcePool.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
}

function deleteInstance(instanceId) {
    if (confirm('Yakin ingin menghapus instance ini?')) {
        router.delete(route('resource-instances.destroy', instanceId), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head :title="`Resource Pool: ${resourcePool.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Resource Pool</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('resource-pools.index', filters)" text="Kembali ke Daftar Resource Pool" />
                        </div>

                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ resourcePool.name }}</h3>
                            <div class="flex items-center gap-1">
                                <Link :href="route('resource-pools.edit', resourcePool.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <!-- Pool Info -->
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Produk:</p>
                                <p>{{ resourcePool.product?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ resourcePool.branch?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kapasitas Default:</p>
                                <p>{{ resourcePool.default_capacity || 0 }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p :class="resourcePool.is_active ? 'text-emerald-600' : 'text-gray-500'">
                                    {{ resourcePool.is_active ? 'Aktif' : 'Nonaktif' }}
                                </p>
                            </div>
                        </div>

                        <!-- Instances Table -->
                        <div class="mt-6">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="text-lg font-semibold">Resource Instances</h4>
                                <Link :href="route('resource-instances.create', { pool_id: resourcePool.id })">
                                    <AppPrimaryButton>
                                        <PlusCircleIcon class="w-4 h-4 mr-1" />
                                        Tambah Instance
                                    </AppPrimaryButton>
                                </Link>
                            </div>

                            <table v-if="resourcePool.instances?.length" class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Kode</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Asset</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Status</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="instance in resourcePool.instances" :key="instance.id" class="group">
                                        <td class="border border-gray-300 px-4 py-2 font-medium">{{ instance.code }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ instance.asset?.name || '-' }}</td>
                                        <td class="border border-gray-300 px-4 py-2">
                                            <span
                                                :class="{
                                                    'text-emerald-600': instance.status === 'active',
                                                    'text-amber-600': instance.status === 'maintenance',
                                                    'text-gray-500': instance.status === 'retired',
                                                }"
                                            >
                                                {{ instance.status === 'active' ? 'Aktif' : instance.status === 'maintenance' ? 'Maintenance' : 'Tidak Aktif' }}
                                            </span>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">
                                            <div class="flex justify-end gap-2">
                                                <Link :href="route('resource-instances.edit', instance.id)" class="text-blue-600 hover:text-blue-800">
                                                    <PencilIcon class="w-4 h-4" />
                                                </Link>
                                                <button @click="deleteInstance(instance.id)" class="text-red-600 hover:text-red-800">
                                                    <TrashIcon class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-else class="text-gray-500 text-sm">Belum ada instance dalam pool ini.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Resource Pool"
            @close="showDeleteConfirmation = false"
            @confirm="deletePool"
        />
    </AuthenticatedLayout>
</template>
