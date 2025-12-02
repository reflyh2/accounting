<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    targets: Object,
    filters: Object,
});

const deleteForm = useForm({});
const showConfirm = ref(null);

const rows = computed(() => props.targets?.data ?? []);

function destroy(id) {
    deleteForm.delete(route('catalog.price-list-targets.destroy', id), {
        preserveScroll: true,
        onFinish: () => (showConfirm.value = null),
    });
}
</script>

<template>
    <Head title="Price List Targets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2>Price List Targets</h2>
                <AppPrimaryButton :href="route('catalog.price-list-targets.create')" as="a">
                    Add Target
                </AppPrimaryButton>
            </div>
        </template>

        <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 uppercase text-xs border-b">
                            <th class="py-2">Price List</th>
                            <th class="py-2">Scope</th>
                            <th class="py-2">Channel</th>
                            <th class="py-2">Priority</th>
                            <th class="py-2">Validity</th>
                            <th class="py-2">Status</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="target in rows" :key="target.id" class="border-b last:border-b-0">
                            <td class="py-3">
                                <div class="font-semibold">{{ target.price_list?.name ?? '-' }}</div>
                                <div class="text-xs text-gray-500">{{ target.price_list?.code }}</div>
                            </td>
                            <td class="py-3">
                                <div v-if="target.partner">
                                    Partner: {{ target.partner.name }}
                                </div>
                                <div v-else-if="target.partner_group">
                                    Group: {{ target.partner_group.name }}
                                </div>
                                <div v-else-if="target.company">
                                    Company: {{ target.company.name }}
                                </div>
                                <div v-else>
                                    Default
                                </div>
                            </td>
                            <td class="py-3">{{ target.channel ?? '—' }}</td>
                            <td class="py-3">{{ target.priority }}</td>
                            <td class="py-3">
                                <div>{{ target.valid_from ?? '—' }} → {{ target.valid_to ?? '—' }}</div>
                            </td>
                            <td class="py-3">
                                <span
                                    class="px-2 py-0.5 rounded text-xs"
                                    :class="target.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ target.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-3 text-right">
                                <Link
                                    :href="route('catalog.price-list-targets.edit', target.id)"
                                    class="text-main-600 hover:underline text-sm mr-2"
                                >
                                    Edit
                                </Link>
                                <AppDeleteButton @click="showConfirm = target.id" title="Delete" />
                                <div
                                    v-if="showConfirm === target.id"
                                    class="mt-2 text-xs text-gray-600 flex items-center gap-2 justify-end"
                                >
                                    <span>Delete this target?</span>
                                    <button class="text-red-600" @click="destroy(target.id)">Yes</button>
                                    <button class="text-gray-500" @click="showConfirm = null">No</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="7" class="py-6 text-center text-gray-500">
                                No targets configured yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-end">
                <Pagination v-if="targets" :links="targets.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

