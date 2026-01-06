<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
    resourcePool: { type: Object, default: null },
    formOptions: Object,
});

const form = useForm({
    product_id: props.resourcePool?.product_id || null,
    branch_id: props.resourcePool?.branch_id || null,
    name: props.resourcePool?.name || '',
    default_capacity: props.resourcePool?.default_capacity ?? 1,
    is_active: props.resourcePool?.is_active ?? true,
});

const submitted = ref(false);

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    if (props.resourcePool) {
        form.put(route('resource-pools.update', props.resourcePool.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; },
        });
    } else {
        form.post(route('resource-pools.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
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
                        v-model="form.product_id"
                        :options="formOptions.products"
                        label="Produk:"
                        placeholder="Pilih Bookable Produk"
                        :error="form.errors.product_id"
                        required
                    />

                    <AppSelect
                        v-model="form.branch_id"
                        :options="formOptions.branches.map(b => ({ value: b.id, label: b.name }))"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        required
                    />
                </div>

                <AppInput
                    v-model="form.name"
                    label="Nama Pool:"
                    placeholder="contoh: Deluxe Room, Toyota Avanza"
                    :error="form.errors.name"
                    required
                />

                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.default_capacity"
                        type="number"
                        label="Kapasitas Default:"
                        min="0"
                        :error="form.errors.default_capacity"
                    />

                    <AppSelect
                        v-model="form.is_active"
                        :options="[{ value: true, label: 'Aktif' }, { value: false, label: 'Nonaktif' }]"
                        label="Status:"
                        :error="form.errors.is_active"
                    />
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Resource Pool</h3>
                <p class="mb-2">Resource Pool adalah kelompok resource sejenis yang dapat di-booking.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih produk yang memiliki capability "bookable"</li>
                    <li>Pilih cabang tempat pool ini berada</li>
                    <li>Beri nama yang deskriptif (contoh: "Deluxe Room", "Toyota Avanza")</li>
                    <li>Kapasitas default adalah jumlah maksimal yang bisa di-booking dalam satu waktu</li>
                    <li>Pool yang tidak aktif tidak akan muncul saat membuat booking</li>
                </ul>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ props.resourcePool ? 'Ubah' : 'Simpan' }} Pool
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.resourcePool" type="button" @click="submitForm(true)" class="mr-2">
                Simpan & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('resource-pools.index'))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
