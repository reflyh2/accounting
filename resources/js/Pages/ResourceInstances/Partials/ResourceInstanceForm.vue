<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    resourceInstance: { type: Object, default: null },
    formOptions: Object,
    preselectedPoolId: [String, Number],
});

const form = useForm({
    resource_pool_id: props.resourceInstance?.resource_pool_id || props.preselectedPoolId || null,
    code: props.resourceInstance?.code || '',
    asset_id: props.resourceInstance?.asset_id || null,
    status: props.resourceInstance?.status || 'active',
});

const submitted = ref(false);

const backRoute = computed(() => {
    if (props.resourceInstance?.resource_pool_id) {
        return route('resource-pools.show', props.resourceInstance.resource_pool_id);
    } else if (props.preselectedPoolId) {
        return route('resource-pools.show', props.preselectedPoolId);
    }
    return route('resource-instances.index');
});

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    if (props.resourceInstance) {
        form.put(route('resource-instances.update', props.resourceInstance.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; },
        });
    } else {
        form.post(route('resource-instances.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                    form.resource_pool_id = props.preselectedPoolId || null;
                }
            },
            onError: () => { submitted.value = false; },
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.resource_pool_id"
                        :options="formOptions.resourcePools"
                        label="Resource Pool:"
                        placeholder="Pilih Resource Pool"
                        :error="form.errors.resource_pool_id"
                        required
                    />

                    <AppInput
                        v-model="form.code"
                        label="Kode Instance:"
                        placeholder="contoh: ROOM-101, AVZ-001"
                        :error="form.errors.code"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.asset_id"
                        :options="[{ value: null, label: '— Tidak ada —' }, ...formOptions.assets]"
                        label="Asset Terkait (opsional):"
                        placeholder="Pilih Asset"
                        :error="form.errors.asset_id"
                    />

                    <AppSelect
                        v-model="form.status"
                        :options="formOptions.statusOptions"
                        label="Status:"
                        :error="form.errors.status"
                        required
                    />
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Resource Instance</h3>
                <p class="mb-2">Resource Instance adalah unit fisik yang dapat di-booking (kamar, kendaraan, dll).</p>
                <ul class="list-disc list-inside">
                    <li>Pilih pool tempat instance ini berada</li>
                    <li>Beri kode unik (contoh: ROOM-101, AVZ-001)</li>
                    <li>Hubungkan dengan asset jika ada (opsional)</li>
                    <li>Status "Aktif" berarti bisa di-booking</li>
                    <li>Status "Maintenance" berarti sedang diperbaiki</li>
                    <li>Status "Tidak Aktif" berarti tidak tersedia</li>
                </ul>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ props.resourceInstance ? 'Ubah' : 'Simpan' }} Instance
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.resourceInstance" type="button" @click="submitForm(true)" class="mr-2">
                Simpan & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(backRoute)">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
